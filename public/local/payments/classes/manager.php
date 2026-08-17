<?php
namespace local_payments;

use local_payments\provider\provider_interface;
use local_payments\provider\payment_request;
use local_payments\provider\checkout_response;
use local_payments\provider\webhook_result;

defined('MOODLE_INTERNAL') || die();

class manager {

    /**
     * Get the best provider for a given country and currency.
     *
     * @param string $country ISO 3166-1 alpha-2
     * @param string $currency ISO 4217
     * @return provider_interface
     * @throws \moodle_exception if no suitable provider found.
     */
    public static function get_provider(string $country, string $currency): provider_interface {
        global $DB;

        $providers = $DB->get_records('local_payments_providers', ['enabled' => 1], 'priority ASC');

        foreach ($providers as $provider_record) {
            // Check country support.
            $countries = $provider_record->supported_countries;
            if ($countries !== '*' && !empty($countries)) {
                $supported = json_decode($countries, true) ?? [];
                if (!empty($supported) && !in_array($country, $supported)) {
                    continue;
                }
            }

            // Check currency support.
            $currencies = $provider_record->supported_currencies;
            if ($currencies !== '*' && !empty($currencies)) {
                $supported = json_decode($currencies, true) ?? [];
                if (!empty($supported) && !in_array($currency, $supported)) {
                    continue;
                }
            }

            return self::instantiate_provider($provider_record);
        }

        throw new \moodle_exception('noproviderfound', 'local_payments', '', null,
            "No enabled payment provider for country={$country}, currency={$currency}");
    }

    /**
     * Get a provider by name.
     */
    public static function get_provider_by_name(string $name): provider_interface {
        global $DB;
        $record = $DB->get_record('local_payments_providers', ['name' => $name, 'enabled' => 1], '*', MUST_EXIST);
        return self::instantiate_provider($record);
    }

    /**
     * Get a provider by its DB record ID.
     */
    public static function get_provider_by_id(int $id): provider_interface {
        global $DB;
        $record = $DB->get_record('local_payments_providers', ['id' => $id], '*', MUST_EXIST);
        return self::instantiate_provider($record);
    }

    private static function instantiate_provider(\stdClass $record): provider_interface {
        $class = "\\{$record->plugin_name}\\gateway";
        if (!class_exists($class)) {
            throw new \coding_exception("Provider class {$class} not found for plugin {$record->plugin_name}");
        }
        return new $class($record);
    }

    /**
     * Create a payment checkout.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param string|null $app_country
     * @param string $display_lang
     * @return object {order_id, checkout_url, expires_at, provider, transaction_id}
     */
    public static function create_checkout(int $courseid, ?int $userid = null, ?string $app_country = null,
            string $display_lang = 'en', string $coupon_code = ''): object {
        global $DB, $USER, $CFG;

        $userid = $userid ?? $USER->id;
        $user = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, country', MUST_EXIST);

        // Resolve price.
        $pricing = price_resolver::resolve($courseid, $userid, $app_country);

        // Apply NIT commerce discount (auto offer + optional coupon code) on the resolved price.
        $disc = self::apply_nit_discount('course', $courseid, $userid, (float) $pricing->price, $coupon_code);
        $amount = $disc['amount'];
        $discountmeta = $disc['discount'];

        // Check for duplicate pending payment.
        $existing = $DB->get_record_select(
            'local_payments_transactions',
            'userid = :userid AND courseid = :courseid AND status = :status AND expires_at > :now',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'status' => status_machine::PENDING,
                'now' => time(),
            ],
            '*',
            IGNORE_MULTIPLE
        );

        if ($existing && !empty($existing->checkout_url)) {
            // Reuse the pending gateway session ONLY if it was created for the same price. If a
            // coupon/offer now makes the price different, the old session still shows the OLD amount
            // on the gateway screen — so retire it (freeing any coupon reservation) and fall through
            // to create a fresh session at the correct amount.
            if (abs((float) $existing->amount - $amount) < 0.01) {
                return (object) [
                    'order_id' => $existing->order_id,
                    'checkout_url' => $existing->checkout_url,
                    'expires_at' => (int) $existing->expires_at,
                    'provider' => $DB->get_field('local_payments_providers', 'name', ['id' => $existing->provider_id]),
                    'transaction_id' => (int) $existing->id,
                    'amount' => (float) $existing->amount,
                    'original_amount' => (float) ($existing->original_amount ?? $existing->amount),
                    'currency' => $existing->currency,
                ];
            }
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $existing->id,
                'status' => status_machine::EXPIRED,
                'reject_reason' => 'Superseded by a new checkout at a different price',
                'timemodified' => time(),
            ]);
            self::release_nit_discount((int) $existing->id);
            self::audit_log($existing->id, $userid, 'status_changed', status_machine::PENDING, status_machine::EXPIRED);
        }

        // Check already purchased.
        if (price_resolver::is_purchased($courseid, $userid)) {
            throw new \moodle_exception('alreadypurchased', 'local_payments');
        }

        // Check already enrolled.
        if (enrollment_handler::is_enrolled($userid, $courseid)) {
            throw new \moodle_exception('alreadyenrolled', 'local_payments');
        }

        // Select provider.
        $provider = self::get_provider($pricing->country, $pricing->currency);
        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider->get_name()]);

        // Generate order ID and idempotency key.
        $order_id = self::generate_order_id();
        $idempotency_key = self::generate_idempotency_key($userid, $courseid);

        $ttl = (int) get_config('local_payments', 'payment_ttl') ?: 1800; // 30 min default.
        $expires_at = time() + $ttl;

        // Create transaction record.
        $transaction = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'provider_id' => $provider_record->id,
            'price_id' => $pricing->price_id,
            'order_id' => $order_id,
            'idempotency_key' => $idempotency_key,
            'amount' => $amount,
            'original_amount' => $pricing->original_price,
            'currency' => $pricing->currency,
            'status' => status_machine::PENDING,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'country' => $pricing->country,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'metadata' => json_encode([
                'pricing' => $pricing,
                'course_name' => $DB->get_field('course', 'fullname', ['id' => $courseid]),
                'item_type' => 'course',
                'item_id' => $courseid,
                'discount' => $discountmeta,
                'coupon_code' => $coupon_code,
            ]),
            'expires_at' => $expires_at,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $transaction_id = $DB->insert_record('local_payments_transactions', $transaction);

        // Audit log.
        self::audit_log($transaction_id, $userid, 'payment_created', '', status_machine::PENDING);

        // Build webhook URL.
        $webhook_url = $CFG->wwwroot . '/local/payments/webhook.php?provider=' . $provider->get_name();
        $success_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id);
        $failure_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id) . '&status=failed';

        // Initialize payment with provider.
        $request = new payment_request([
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $pricing->currency,
            'description' => get_string('paymentfor', 'local_payments',
                $DB->get_field('course', 'fullname', ['id' => $courseid])),
            'userid' => $userid,
            'courseid' => $courseid,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'webhook_url' => $webhook_url,
            'success_url' => $success_url,
            'failure_url' => $failure_url,
            'metadata' => ['transaction_id' => $transaction_id, 'courseid' => $courseid],
            'transaction_id' => $transaction_id,
        ]);

        $response = $provider->initialize_payment($request);

        if (!$response->success) {
            // Mark transaction as failed.
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction_id,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($response->error_message, 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transaction_id, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);

            throw new \moodle_exception('paymentinitiationfailed', 'local_payments', '', $response->error_message);
        }

        // Update transaction with provider session info.
        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction_id,
            'provider_session_id' => $response->provider_session_id,
            'checkout_url' => $response->checkout_url,
            'timemodified' => time(),
        ]);

        // Reserve coupon/offer usage for this pending checkout so a capped coupon
        // can't be over-redeemed by concurrent checkouts. Released on failure /
        // abandonment (callback + cleanup task); confirmed as-is at fulfilment.
        self::reserve_nit_discount($discountmeta, $userid, $transaction_id, 'course', $courseid);

        return (object) [
            'order_id' => $order_id,
            'checkout_url' => $response->checkout_url,
            'expires_at' => $expires_at,
            'provider' => $provider->get_name(),
            'transaction_id' => $transaction_id,
            'amount' => (float) $amount,
            'original_amount' => (float) $pricing->original_price,
            'currency' => $pricing->currency,
        ];
    }

    /**
     * Create a Kashier checkout for a NIT subscription (item_type=subscription, courseid sentinel 0).
     *
     * The transaction is fulfilled on payment success by {@see self::fulfil_subscription()} — it
     * creates a subscription purchase (local_nit_subscriptions) which grants live course access.
     *
     * @param int $subscriptionid
     * @param int|null $userid
     * @param string|null $app_country
     * @param string $display_lang
     * @param string $type normal | b2b
     * @param int $seats B2B seat capacity
     * @param string $coupon_code optional coupon entered at checkout
     * @param string $return_url page the checkout was launched from
     * @return object {order_id, checkout_url, expires_at, provider, transaction_id}
     */
    public static function create_subscription_checkout(int $subscriptionid, ?int $userid = null,
            ?string $app_country = null, string $display_lang = 'en', string $type = 'normal',
            int $seats = 0, string $coupon_code = '', string $return_url = ''): object {
        global $DB, $USER, $CFG;

        $userid = $userid ?? $USER->id;
        $user = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, country', MUST_EXIST);
        $sub = $DB->get_record('nit_subscription', ['id' => $subscriptionid], '*', MUST_EXIST);
        // The plan must be active. The public block only lists active plans, but
        // this endpoint accepts any id, so guard against buying a
        // deactivated/discontinued plan by supplying its id directly.
        if (($sub->status ?? '') !== 'active') {
            throw new \moodle_exception('error', 'moodle', '', null, 'This subscription plan is not available');
        }

        $isb2b = ($type === 'b2b');
        $b2bseats = 0;
        $amount = (float) $sub->price;
        $discountmeta = null;

        if ($isb2b) {
            if (empty($sub->b2b_enabled)) {
                throw new \moodle_exception('error', 'moodle', '', null, 'This subscription is not available for B2B purchase');
            }
            $option = $DB->get_record('nit_sub_seat_option', ['subscriptionid' => $sub->id, 'seats' => (int) $seats]);
            if (!$option) {
                throw new \moodle_exception('error', 'moodle', '', null, 'The selected capacity is not available');
            }
            $b2bseats = (int) $seats;
            if (class_exists('\local_nit_subscriptions\subscription_manager')) {
                $price = \local_nit_subscriptions\subscription_manager::b2b_price($sub->price, $b2bseats, $option->discount_percent);
                $amount = (float) $price['final'];
            }
        } else {
            // A user may hold only one active NORMAL subscription at a time.
            if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')
                    && \local_nit_subscriptions\subscription_purchase_manager::has_active_normal($userid)) {
                throw new \moodle_exception('error', 'moodle', '', null, 'You already have an active subscription');
            }
            // Apply coupon/offer (normal purchase only).
            $disc = self::apply_nit_discount('subscription', $subscriptionid, $userid, (float) $sub->price, $coupon_code);
            $amount = $disc['amount'];
            $discountmeta = $disc['discount'];
        }

        $originalamount = (float) $sub->price;
        $currency = 'EGP';
        $country = $app_country ?: ($user->country ?: 'EG');

        $provider = self::get_provider($country, $currency);
        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider->get_name()]);

        $order_id = self::generate_order_id();
        $idempotency_key = self::generate_idempotency_key($userid, $subscriptionid + 2000000);

        $ttl = (int) get_config('local_payments', 'payment_ttl') ?: 1800;
        $expires_at = time() + $ttl;

        $transaction = (object) [
            'userid' => $userid,
            'courseid' => 0, // Sentinel: subscription transactions are not tied to a course.
            'provider_id' => $provider_record->id,
            'price_id' => null,
            'order_id' => $order_id,
            'idempotency_key' => $idempotency_key,
            'amount' => $amount,
            'original_amount' => $originalamount,
            'currency' => $currency,
            'status' => status_machine::PENDING,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'country' => $country,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'metadata' => json_encode([
                'item_type' => 'subscription',
                'item_id' => $subscriptionid,
                'subscription_name' => $sub->name,
                'sub_type' => $isb2b ? 'b2b' : 'normal',
                'seats' => $b2bseats,
                'discount' => $discountmeta,
                'coupon_code' => $coupon_code,
                'return_url' => $return_url,
            ]),
            'expires_at' => $expires_at,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $transaction_id = $DB->insert_record('local_payments_transactions', $transaction);
        self::audit_log($transaction_id, $userid, 'payment_created', '', status_machine::PENDING);

        $webhook_url = $CFG->wwwroot . '/local/payments/webhook.php?provider=' . $provider->get_name();
        $success_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id);
        $failure_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id) . '&status=failed';

        $request = new payment_request([
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Subscription: ' . format_string($sub->name),
            'userid' => $userid,
            'courseid' => 0,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'webhook_url' => $webhook_url,
            'success_url' => $success_url,
            'failure_url' => $failure_url,
            'metadata' => ['transaction_id' => $transaction_id],
            'transaction_id' => $transaction_id,
        ]);

        $response = $provider->initialize_payment($request);

        if (!$response->success) {
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction_id,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($response->error_message, 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transaction_id, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);
            throw new \moodle_exception('paymentinitiationfailed', 'local_payments', '', $response->error_message);
        }

        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction_id,
            'provider_session_id' => $response->provider_session_id,
            'checkout_url' => $response->checkout_url,
            'timemodified' => time(),
        ]);

        // Reserve coupon/offer usage for this pending subscription checkout (see
        // create_checkout). Fails the checkout cleanly if a capped coupon is now used up.
        self::reserve_nit_discount($discountmeta, $userid, $transaction_id, 'subscription', $subscriptionid);

        return (object) [
            'order_id' => $order_id,
            'checkout_url' => $response->checkout_url,
            'expires_at' => $expires_at,
            'provider' => $provider->get_name(),
            'transaction_id' => $transaction_id,
            // The actual charged (post-discount) amount, so the app can display the correct price
            // even if it renders its own summary/gateway screen instead of opening checkout_url.
            'amount' => (float) $amount,
            'original_amount' => (float) $originalamount,
            'currency' => $currency,
        ];
    }

    /**
     * Resolve the charged amount for a NIT commerce discount (coupon/offer), for checkout.
     *
     * @param string $item_type course | package | subscription
     * @param int $item_id
     * @param int $userid
     * @param float $base base price before discount
     * @param string $coupon_code
     * @return array {amount: float, discount: array|null}
     */
    private static function apply_nit_discount(string $item_type, int $item_id, int $userid,
            float $base, string $coupon_code): array {
        if (!class_exists('\local_nit_commerce\discount_manager')) {
            return ['amount' => $base, 'discount' => null];
        }
        $resolved = \local_nit_commerce\discount_manager::resolve($item_type, $item_id, $userid, $coupon_code, $base);
        return [
            'amount' => (float) $resolved['final'],
            'discount' => [
                'original'        => $resolved['original'],
                'offers'          => $resolved['offers'] ?? [],
                'coupon_id'       => $resolved['coupon_id'] ?? 0,
                'coupon_code'     => $resolved['coupon_code'] ?? '',
                'coupon_discount' => $resolved['coupon_discount'] ?? 0,
                'offer_discount'  => $resolved['offer_discount'] ?? 0,
                'discount'        => $resolved['discount'] ?? 0,
                'final'           => $resolved['final'],
            ],
        ];
    }

    /**
     * Fulfil a completed subscription transaction: create the purchase (grants live course access) and
     * record coupon/offer usage. Safe to call more than once (fulfilment is idempotent by order id).
     *
     * @param \stdClass $transaction
     * @param \stdClass $meta decoded transaction metadata
     * @return void
     */
    private static function fulfil_subscription(\stdClass $transaction, \stdClass $meta): void {
        try {
            if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
                \local_nit_subscriptions\subscription_purchase_manager::fulfil_from_gateway(
                    (int) $transaction->userid,
                    (int) ($meta->item_id ?? 0),
                    (float) $transaction->amount,
                    (string) $transaction->order_id,
                    $meta->sub_type ?? 'normal',
                    (int) ($meta->seats ?? 0)
                );
                self::audit_log($transaction->id, $transaction->userid, 'subscription_purchased', '',
                    (string) ($meta->item_id ?? 0));
            }
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error',
                'Subscription fulfilment failed: ' . $e->getMessage());
        }

        // Record coupon/offer usage (idempotent by transaction id).
        self::record_nit_discount($transaction, $meta, 'subscription', (int) ($meta->item_id ?? 0));
    }

    /**
     * Record NIT commerce coupon/offer usage for a fulfilled transaction (idempotent by transaction).
     *
     * @param \stdClass $transaction
     * @param \stdClass|null $meta decoded transaction metadata (may carry ->discount)
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @return void
     */
    private static function record_nit_discount(\stdClass $transaction, $meta, string $itemtype, int $itemid): void {
        if (!isset($meta->discount) || !$meta->discount || !class_exists('\local_nit_commerce\discount_manager')) {
            return;
        }
        try {
            \local_nit_commerce\discount_manager::record_usage(
                json_decode(json_encode($meta->discount), true),
                (int) $transaction->userid,
                (int) $transaction->id,
                $itemtype,
                $itemid
            );
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Discount usage record failed: ' . $e->getMessage());
        }
    }

    /**
     * Reserve NIT commerce coupon/offer usage for a pending checkout. If the
     * coupon's limit is reached during reservation, this fails the checkout
     * cleanly (marks the transaction FAILED and rethrows) so a capped coupon is
     * never over-redeemed by concurrent checkouts.
     *
     * @param array|null $discount stored discount metadata (from apply_nit_discount)
     * @param int $userid
     * @param int $transactionid
     * @param string $itemtype
     * @param int $itemid
     * @return void
     */
    private static function reserve_nit_discount($discount, int $userid, int $transactionid,
            string $itemtype, int $itemid): void {
        if (empty($discount) || !is_array($discount) || !class_exists('\local_nit_commerce\discount_manager')) {
            return;
        }
        try {
            \local_nit_commerce\discount_manager::reserve_usage($discount, $userid, $transactionid, $itemtype, $itemid);
        } catch (\moodle_exception $e) {
            global $DB;
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transactionid,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($e->getMessage(), 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transactionid, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);
            throw $e;
        }
    }

    /**
     * Release any coupon/offer reservation held by a transaction whose payment
     * failed or was abandoned, freeing a capped coupon for others.
     *
     * @param int $transactionid
     * @return void
     */
    private static function release_nit_discount(int $transactionid): void {
        if ($transactionid > 0 && class_exists('\local_nit_commerce\discount_manager')) {
            \local_nit_commerce\discount_manager::release_usage($transactionid);
        }
    }

    /**
     * Process a webhook from a payment provider.
     */
    public static function process_webhook(string $provider_name, string $payload, array $headers): bool {
        global $DB;

        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider_name]);
        if (!$provider_record) {
            return false;
        }

        $provider = self::instantiate_provider($provider_record);
        $result = $provider->handle_webhook($payload, $headers);

        // Store webhook record.
        $webhook_id = $DB->insert_record('local_payments_webhooks', (object) [
            'provider_id' => $provider_record->id,
            'event_type' => $result->event_type,
            'merchant_order_id' => $result->merchant_order_id,
            'provider_order_id' => $result->provider_order_id,
            'order_reference' => $result->order_reference,
            'payment_method' => $result->payment_method,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'payload' => $payload,
            'headers' => json_encode($headers),
            'card_info' => !empty($result->card_info) ? json_encode($result->card_info) : null,
            'source_of_funds' => !empty($result->source_of_funds) ? json_encode($result->source_of_funds) : null,
            'channel' => $result->channel,
            'signature_keys' => !empty($result->signature_keys) ? json_encode($result->signature_keys) : null,
            'signature_valid' => $result->signature_valid ? 1 : 0,
            'status' => 'received',
            'timecreated' => time(),
        ]);

        if (!$result->signature_valid) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'failed',
                'processed_at' => time(),
            ]);
            return false;
        }

        // Find matching transaction by merchant_order_id (our order_id).
        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $result->merchant_order_id]);

        if (!$transaction) {
            // Try metadata-based lookup for providers that embed transaction_id.
            $txn_id = $result->metadata['transaction_id'] ?? null;
            if ($txn_id) {
                $transaction = $DB->get_record('local_payments_transactions', ['id' => $txn_id]);
            }
        }

        if (!$transaction) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'failed',
                'processed_at' => time(),
            ]);
            return false;
        }

        // Link webhook to transaction.
        $DB->update_record('local_payments_webhooks', (object) [
            'id' => $webhook_id,
            'transaction_id' => $transaction->id,
        ]);

        // Idempotency: skip if already completed.
        if ($transaction->status === status_machine::COMPLETED) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'processed',
                'processed_at' => time(),
            ]);
            return true;
        }

        // Process based on event type.
        $success = false;
        if (in_array($result->event_type, ['pay', 'capture'])) {
            $success = self::process_payment_webhook($transaction, $result, $webhook_id);
        } else if ($result->event_type === 'refund') {
            $success = self::process_refund_webhook($transaction, $result, $webhook_id);
        } else if ($result->event_type === 'void') {
            $success = self::process_void_webhook($transaction, $result, $webhook_id);
        }

        $DB->update_record('local_payments_webhooks', (object) [
            'id' => $webhook_id,
            'status' => $success ? 'processed' : 'failed',
            'processed_at' => time(),
        ]);

        return $success;
    }

    private static function process_payment_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        $provider_status = strtoupper($result->status);

        if ($provider_status !== 'SUCCESS') {
            // Payment failed.
            if (status_machine::can_transition($transaction->status, status_machine::FAILED)) {
                $DB->update_record('local_payments_transactions', (object) [
                    'id' => $transaction->id,
                    'status' => status_machine::FAILED,
                    'provider_order_id' => $result->provider_order_id,
                    'provider_txn_id' => $result->provider_txn_id,
                    'payment_method_type' => $result->payment_method,
                    'reject_reason' => substr("Provider status: {$result->status}", 0, 255),
                    'provider_response_code' => $result->response_code,
                    'provider_response_message' => $result->response_message,
                    'timemodified' => time(),
                ]);
                self::audit_log($transaction->id, $transaction->userid, 'status_changed',
                    $transaction->status, status_machine::FAILED);
            }
            return true;
        }

        // Verify amount matches.
        $expected = (float) $transaction->amount;
        $received = $result->amount;
        // Reject if amount OR currency mismatches. Currency is only enforced when
        // the gateway result carries one (guarded so it can't break payments where
        // the field is absent) — a right-number, wrong-currency message is rejected.
        $currencymismatch = !empty($result->currency)
            && strcasecmp((string) $result->currency, (string) $transaction->currency) !== 0;
        if (abs($expected - $received) > 0.01 || $currencymismatch) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error',
                "Amount/currency mismatch: expected={$expected} {$transaction->currency}, received={$received}");
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction->id,
                'status' => status_machine::FAILED,
                'reject_reason' => "Amount mismatch: expected {$expected}, got {$received}",
                'timemodified' => time(),
            ]);
            return false;
        }

        if (!status_machine::can_transition($transaction->status, status_machine::COMPLETED)) {
            return true; // Already in a terminal state.
        }

        // Update transaction to completed.
        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => status_machine::COMPLETED,
            'provider_order_id' => $result->provider_order_id,
            'provider_txn_id' => $result->provider_txn_id,
            'payment_method_type' => $result->payment_method,
            'provider_response_code' => $result->response_code,
            'provider_response_message' => $result->response_message,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed',
            $transaction->status, status_machine::COMPLETED);

        // Subscriptions are fulfilled differently (no course enrolment / course invoice / course event).
        $meta = json_decode($transaction->metadata ?? '{}');
        if (($meta->item_type ?? 'course') === 'subscription') {
            self::fulfil_subscription($transaction, $meta);
            return true;
        }

        // Fulfilment: enrol the student in the purchased course.
        try {
            $enrolled = enrollment_handler::enrol_user((int) $transaction->userid, (int) $transaction->courseid);
            if ($enrolled) {
                self::audit_log($transaction->id, $transaction->userid, 'student_enrolled', '', (string) $transaction->courseid);
            } else {
                self::log_entry($transaction->provider_id, $transaction->id, 'error',
                    'Enrolment call completed without throwing but user is not enrolled.');
            }
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error',
                'Fulfillment failed: ' . $e->getMessage());
        }

        // Record coupon/offer usage for the course purchase (idempotent).
        self::record_nit_discount($transaction, $meta, 'course', (int) $transaction->courseid);

        // Generate invoice.
        try {
            invoice_generator::create((int) $transaction->id);
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Invoice generation failed: ' . $e->getMessage());
        }

        // Send confirmation message.
        self::send_confirmation($transaction);

        // Fire event.
        $event = \local_payments\event\payment_completed::create([
            'context' => \context_course::instance($transaction->courseid),
            'objectid' => $transaction->id,
            'userid' => $transaction->userid,
            'other' => [
                'courseid' => $transaction->courseid,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'provider' => $DB->get_field('local_payments_providers', 'name', ['id' => $transaction->provider_id]),
            ],
        ]);
        $event->trigger();

        return true;
    }

    private static function process_refund_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        $new_status = ($result->amount >= (float) $transaction->amount)
            ? status_machine::REFUNDED
            : status_machine::PARTIALLY_REFUNDED;

        if (!status_machine::can_transition($transaction->status, $new_status)) {
            return true;
        }

        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => $new_status,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed', $transaction->status, $new_status);
        return true;
    }

    private static function process_void_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        if (!status_machine::can_transition($transaction->status, status_machine::VOIDED)) {
            return true;
        }

        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => status_machine::VOIDED,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed', $transaction->status, status_machine::VOIDED);
        return true;
    }

    /**
     * Verify a payment after the user is redirected back from the provider.
     */
    public static function verify_callback(string $order_id): object {
        global $DB;

        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
        if (!$transaction) {
            throw new \moodle_exception('transactionnotfound', 'local_payments');
        }

        $meta = json_decode($transaction->metadata ?? '{}');
        $item_type = $meta->item_type ?? 'course';

        $issubscription = ($item_type === 'subscription');

        // If already completed (by webhook), return success immediately.
        if ($transaction->status === status_machine::COMPLETED) {
            return (object) [
                'success' => true,
                'status' => $transaction->status,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => $issubscription ? false
                    : enrollment_handler::is_enrolled((int) $transaction->userid, (int) $transaction->courseid),
            ];
        }

        // Otherwise verify with provider.
        if (empty($transaction->provider_session_id)) {
            return (object) [
                'success' => false,
                'status' => $transaction->status,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => false,
            ];
        }

        $provider = self::get_provider_by_id((int) $transaction->provider_id);
        $result = $provider->verify_payment($transaction->provider_session_id);

        if ($result->verified) {
            // Double-check amount and (when present) currency.
            $currencymismatch = !empty($result->currency)
                && strcasecmp((string) $result->currency, (string) $transaction->currency) !== 0;
            if (abs((float) $transaction->amount - $result->amount) > 0.01 || $currencymismatch) {
                return (object) [
                    'success' => false,
                    'status' => 'amount_mismatch',
                    'courseid' => (int) $transaction->courseid,
                    'item_type' => $item_type,
                    'enrolled' => false,
                ];
            }

            if (status_machine::can_transition($transaction->status, status_machine::COMPLETED)) {
                $DB->update_record('local_payments_transactions', (object) [
                    'id' => $transaction->id,
                    'status' => status_machine::COMPLETED,
                    'provider_order_id' => $result->provider_order_id,
                    'provider_txn_id' => $result->provider_txn_id,
                    'payment_method_type' => $result->payment_method_type,
                    'timemodified' => time(),
                ]);
                self::audit_log($transaction->id, $transaction->userid, 'status_changed',
                    $transaction->status, status_machine::COMPLETED);

                if ($issubscription) {
                    // Subscription: create the purchase (grants live course access); no course enrolment.
                    self::fulfil_subscription($transaction, $meta);
                    return (object) [
                        'success' => true,
                        'status' => status_machine::COMPLETED,
                        'courseid' => 0,
                        'item_type' => $item_type,
                        'enrolled' => false,
                    ];
                }

                $enrolled = false;
                try {
                    $enrolled = enrollment_handler::enrol_user((int) $transaction->userid, (int) $transaction->courseid);
                    if ($enrolled) {
                        self::audit_log($transaction->id, $transaction->userid, 'student_enrolled', '', (string) $transaction->courseid);
                    } else {
                        self::log_entry($transaction->provider_id, $transaction->id, 'error',
                            'Enrolment call completed without throwing but user is not enrolled.');
                    }
                } catch (\Exception $e) {
                    self::log_entry($transaction->provider_id, $transaction->id, 'error',
                        'Fulfillment failed: ' . $e->getMessage());
                }

                // Record coupon/offer usage for the course purchase (idempotent).
                self::record_nit_discount($transaction, $meta, 'course', (int) $transaction->courseid);

                invoice_generator::create((int) $transaction->id);
                self::send_confirmation($transaction);
            } else {
                $enrolled = $issubscription ? false
                    : enrollment_handler::is_enrolled((int) $transaction->userid, (int) $transaction->courseid);
            }

            return (object) [
                'success' => true,
                'status' => status_machine::COMPLETED,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => $enrolled,
            ];
        }

        return (object) [
            'success' => false,
            'status' => $transaction->status,
            'courseid' => (int) $transaction->courseid,
            'item_type' => $item_type,
            'enrolled' => false,
        ];
    }

    /**
     * Get all available providers for a country and currency.
     */
    public static function get_available_providers(string $country, string $currency): array {
        global $DB;
        $providers = $DB->get_records('local_payments_providers', ['enabled' => 1], 'priority ASC');
        $available = [];

        foreach ($providers as $p) {
            $countries = ($p->supported_countries === '*' || empty($p->supported_countries))
                ? [] : (json_decode($p->supported_countries, true) ?? []);
            $currencies = ($p->supported_currencies === '*' || empty($p->supported_currencies))
                ? [] : (json_decode($p->supported_currencies, true) ?? []);

            if (!empty($countries) && !in_array($country, $countries)) {
                continue;
            }
            if (!empty($currencies) && !in_array($currency, $currencies)) {
                continue;
            }

            $available[] = [
                'name' => $p->name,
                'display_name' => $p->display_name,
                'priority' => (int) $p->priority,
            ];
        }

        return $available;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private static function generate_order_id(): string {
        return 'PAY-' . date('Y') . '-' . str_pad(random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private static function generate_idempotency_key(int $userid, int $courseid): string {
        return hash('sha256', $userid . '-' . $courseid . '-' . time() . '-' . random_int(1, 999999));
    }

    private static function audit_log(?int $transaction_id, ?int $userid, string $action,
            string $old_value = '', string $new_value = ''): void {
        global $DB;
        $DB->insert_record('local_payments_audit_logs', (object) [
            'transaction_id' => $transaction_id,
            'userid' => $userid,
            'action' => $action,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'timecreated' => time(),
        ]);
    }

    private static function log_entry(?int $provider_id, ?int $transaction_id, string $level, string $message): void {
        global $DB;
        $DB->insert_record('local_payments_logs', (object) [
            'provider_id' => $provider_id,
            'transaction_id' => $transaction_id,
            'level' => $level,
            'message' => substr($message, 0, 500),
            'timecreated' => time(),
        ]);
    }

    private static function send_confirmation(\stdClass $transaction): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $transaction->userid]);
        $course = $DB->get_record('course', ['id' => $transaction->courseid]);

        if (!$user || !$course) {
            return;
        }

        $message = new \core\message\message();
        $message->component = 'local_payments';
        $message->name = 'payment_confirmation';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('payment_confirmation_subject', 'local_payments', $course->fullname);
        $message->fullmessage = get_string('payment_confirmation_body', 'local_payments', (object) [
            'coursename' => $course->fullname,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'order_id' => $transaction->order_id,
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('payment_confirmation_html', 'local_payments', (object) [
            'coursename' => $course->fullname,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'order_id' => $transaction->order_id,
        ]);
        $message->smallmessage = get_string('payment_confirmation_small', 'local_payments', $course->fullname);
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Confirmation message failed: ' . $e->getMessage());
        }
    }
}
