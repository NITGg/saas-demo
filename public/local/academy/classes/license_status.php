<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the admin/owner "licence status" payload returned by the custom API's
 * get_license_status function (and usable by any web view). Kept as a pure
 * data-builder — no capability check, no output, no exit — so it is unit-testable;
 * the API dispatcher (api.php) owns the token/capability gate and the envelope.
 *
 * @package local_academy
 */
class license_status {

    /**
     * Assemble the licence + usage + subscription data for the current academy.
     *
     * Price and the authoritative subscription/billing history are NOT here: they
     * are held in the control plane (nit2), which is why `billing_in_nit2` is true.
     * The subscription block mirrors what nit2 pushed to local_license (expiry +
     * subscribed-at); values are null when no term has been pushed.
     *
     * @return array the `data` payload (caller wraps it in the success envelope)
     */
    public static function build(): array {
        $lexpiry   = \local_license\license::expiry();
        $lsubat    = \local_license\license::subscribed_at();
        $lenforced = \local_license\license::is_enforced();
        $ldef      = \local_license\license::tierdef();
        $lsusp     = \local_license\license::is_suspended();
        $lexp      = \local_license\license::is_expired();

        return [
            'package' => [
                'enforced'    => $lenforced,
                'tier'        => \local_license\license::tier(),
                'name'        => \local_license\license::tiername(),
                'videosource' => \local_license\license::video_source(),
                'features'    => array_values((array) ($ldef['features'] ?? [])),
                'storagegb'   => (int) ($ldef['storagegb'] ?? -1), // GB moodledata quota
                'limits'      => [ // -1 = unlimited
                    'maxcourses'  => \local_license\license::max_courses(),
                    'maxteachers' => \local_license\license::max_teachers(),
                    'quiz'        => \local_license\license::bucket_limit('quiz'),
                    'video'       => \local_license\license::bucket_limit('video'),
                    'pdf'         => \local_license\license::bucket_limit('pdf'),
                ],
                // price + "subscribed at" are billing values held in nit2, not
                // enforced in Moodle. For the FULL billing view (price, purchase
                // date, invoices) + upgrade/renew, the app calls nit2:
                //   GET  /api/academies/<slug>/plan   (full details)
                //   POST /api/payments/kashier/create {purpose:"upgrade"|"renew"}
                'billing_in_nit2' => true,
            ],
            'usage' => [
                'courses'  => \local_license\enforcer::count_courses(),
                'teachers' => \local_license\enforcer::count_teachers(),
                'quiz'     => \local_license\enforcer::count_bucket('quiz'),
                'video'    => \local_license\enforcer::count_bucket('video'),
                'pdf'      => \local_license\enforcer::count_bucket('pdf'),
            ],
            'subscription' => [
                // "subscribed at" — mirror of nit2's Academy.subscribedAt (pushed
                // at create / renewal / plan change). null when unknown.
                'subscribedat'   => $lsubat ?: null,                        // unix ts
                'subscribeddate' => $lsubat ? date('c', $lsubat) : null,    // ISO-8601
                'expiry'         => $lexpiry ?: null,                        // unix ts; null = never expires
                'expirydate'     => $lexpiry ? date('c', $lexpiry) : null,   // ISO-8601
                'daysleft'       => ($lexpiry && $lenforced) ? max(0, \local_license\license::days_left()) : null,
                'is_expired'     => $lexp,
                'is_suspended'   => $lsusp,
                'status'         => $lsusp ? 'suspended' : ($lexp ? 'expired' : 'active'),
            ],
        ];
    }
}
