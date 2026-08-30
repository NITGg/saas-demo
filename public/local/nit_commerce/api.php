<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JSON API for NIT Commerce (coupons + offers). Mirrors the reference api.php protocol:
 *   ?function=<name> ... → {"status":"success","data":...} | {"status":"error","error":...}
 *
 * Auth is the logged-in session; state-changing calls require POST + sesskey. The get_available_*
 * reads are public so a home-page marketing block can render them for guests.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

use local_nit_commerce\coupon_manager;
use local_nit_commerce\offer_manager;

$function = optional_param('function', '', PARAM_ALPHANUMEXT);

$publicfns = ['get_available_coupons', 'get_available_offers'];

if (!in_array($function, $publicfns, true)) {
    require_login(null, false);
}

$context = context_system::instance();
$PAGE->set_context($context);

// Licence gate: refuse coupon/offer calls when the tier doesn't include them.
// Public listings return empty; management calls return an error. Nothing runs.
$nitreqfeat = (strpos($function, 'coupon') !== false) ? 'coupons'
    : ((strpos($function, 'offer') !== false) ? 'offers' : null);
if ($nitreqfeat !== null && !local_nit_commerce_feature($nitreqfeat)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'feature_unavailable', 'coupons' => [], 'offers' => []]);
    exit;
}

$alang = optional_param('alang', '', PARAM_LANG);
if ($alang === '') {
    $alang = optional_param('lang', '', PARAM_LANG);
}
if ($alang !== '') {
    force_current_language($alang);
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON envelope and stop.
 *
 * @param array $payload
 * @return void
 */
function nit_commerce_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

/**
 * Decode a JSON array request parameter into a PHP array.
 *
 * @param string $name
 * @return array
 */
function nit_commerce_json_array(string $name): array {
    $decoded = json_decode(optional_param($name, '[]', PARAM_RAW), true);
    return is_array($decoded) ? $decoded : [];
}

try {
    $ispost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    $writes = ['create_coupon', 'update_coupon', 'activate_coupon', 'deactivate_coupon', 'delete_coupon',
        'create_offer', 'update_offer', 'activate_offer', 'deactivate_offer', 'delete_offer'];
    if (in_array($function, $writes, true)) {
        if (!$ispost) {
            throw new \moodle_exception('err_postrequired', 'local_nit_commerce');
        }
        require_sesskey();
    }

    $couponfns = ['get_coupons', 'create_coupon', 'update_coupon', 'activate_coupon',
        'deactivate_coupon', 'delete_coupon'];
    $offerfns  = ['get_offers', 'create_offer', 'update_offer', 'activate_offer',
        'deactivate_offer', 'delete_offer'];
    if (in_array($function, $couponfns, true)) {
        require_capability('local/nit_commerce:managecoupons', $context);
    }
    if (in_array($function, $offerfns, true)) {
        require_capability('local/nit_commerce:manageoffers', $context);
    }
    if ($function === 'get_discount_targets'
            && !has_capability('local/nit_commerce:managecoupons', $context)
            && !has_capability('local/nit_commerce:manageoffers', $context)) {
        throw new \moodle_exception('err_permissiondenied', 'local_nit_commerce');
    }

    global $USER, $DB;

    switch ($function) {
        // ── Coupons ──
        case 'get_coupons':
            nit_commerce_respond(['status' => 'success', 'data' => coupon_manager::get_coupons()]);
            break;

        case 'create_coupon':
            $id = coupon_manager::create_coupon([
                'code'           => required_param('code', PARAM_TEXT),
                'discount_type'  => optional_param('discount_type', 'percent', PARAM_ALPHA),
                'discount_value' => optional_param('discount_value', 0, PARAM_FLOAT),
                'max_discount'   => optional_param('max_discount', '', PARAM_RAW_TRIMMED),
                'usage_type'     => optional_param('usage_type', 'multiple', PARAM_ALPHA),
                'usage_limit'    => optional_param('usage_limit', 0, PARAM_INT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'active'         => optional_param('active', 1, PARAM_INT),
                'items'          => nit_commerce_json_array('items'),
            ], $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => ['id' => $id]]);
            break;

        case 'update_coupon':
            coupon_manager::update_coupon(required_param('id', PARAM_INT), [
                'code'           => required_param('code', PARAM_TEXT),
                'discount_type'  => optional_param('discount_type', 'percent', PARAM_ALPHA),
                'discount_value' => optional_param('discount_value', 0, PARAM_FLOAT),
                'max_discount'   => optional_param('max_discount', '', PARAM_RAW_TRIMMED),
                'usage_type'     => optional_param('usage_type', 'multiple', PARAM_ALPHA),
                'usage_limit'    => optional_param('usage_limit', 0, PARAM_INT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'status'         => optional_param('status', 'active', PARAM_ALPHA),
                'items'          => nit_commerce_json_array('items'),
            ], $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'activate_coupon':
            coupon_manager::activate_coupon(required_param('id', PARAM_INT), $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'deactivate_coupon':
            coupon_manager::deactivate_coupon(required_param('id', PARAM_INT), $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'delete_coupon':
            coupon_manager::delete_coupon(required_param('id', PARAM_INT));
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Offers ──
        case 'get_offers':
            nit_commerce_respond(['status' => 'success', 'data' => offer_manager::get_offers()]);
            break;

        case 'create_offer':
            $id = offer_manager::create_offer([
                'name'           => required_param('name', PARAM_TEXT),
                'discount_type'  => optional_param('discount_type', 'percent', PARAM_ALPHA),
                'discount_value' => optional_param('discount_value', 0, PARAM_FLOAT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'active'         => optional_param('active', 1, PARAM_INT),
                'items'          => nit_commerce_json_array('items'),
            ], $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => ['id' => $id]]);
            break;

        case 'update_offer':
            offer_manager::update_offer(required_param('id', PARAM_INT), [
                'name'           => required_param('name', PARAM_TEXT),
                'discount_type'  => optional_param('discount_type', 'percent', PARAM_ALPHA),
                'discount_value' => optional_param('discount_value', 0, PARAM_FLOAT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'status'         => optional_param('status', 'active', PARAM_ALPHA),
                'items'          => nit_commerce_json_array('items'),
            ], $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'activate_offer':
            offer_manager::activate_offer(required_param('id', PARAM_INT), $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'deactivate_offer':
            offer_manager::deactivate_offer(required_param('id', PARAM_INT), $USER->id);
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        case 'delete_offer':
            offer_manager::delete_offer(required_param('id', PARAM_INT));
            nit_commerce_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Shared: selectable scope targets ──
        case 'get_discount_targets':
            nit_commerce_respond(['status' => 'success', 'data' => nit_commerce_discount_targets()]);
            break;

        // ── Checkout: preview the discounted price (offer auto + optional coupon code) ──
        case 'preview_discount':
            $itemtype = required_param('item_type', PARAM_ALPHA);
            $itemid   = required_param('item_id', PARAM_INT);
            $code     = optional_param('coupon_code', '', PARAM_TEXT);
            // Course prices live in local_payments (per-course rules), not in the discount engine,
            // so resolve the base there and pass it in; subscriptions/packages resolve their own.
            $base = nit_commerce_base_price($itemtype, $itemid, $USER->id);
            try {
                $resolved = \local_nit_commerce\discount_manager::resolve($itemtype, $itemid, $USER->id, $code, $base);
                nit_commerce_respond(['status' => 'success', 'data' => $resolved]);
            } catch (\moodle_exception $e) {
                // Invalid coupon — recompute without it so the offer-only price still shows.
                $resolved = \local_nit_commerce\discount_manager::resolve($itemtype, $itemid, $USER->id, '', $base);
                $resolved['coupon_error'] = $e->getMessage();
                nit_commerce_respond(['status' => 'success', 'data' => $resolved]);
            }
            break;

        // ── Public reads for front-page blocks ──
        case 'get_available_coupons':
            nit_commerce_respond(['status' => 'success', 'data' => coupon_manager::get_available_coupons()]);
            break;

        case 'get_available_offers':
            nit_commerce_respond(['status' => 'success', 'data' => offer_manager::get_available_offers()]);
            break;

        default:
            throw new \moodle_exception('err_unknownfunction', 'local_nit_commerce');
    }
} catch (\Throwable $e) {
    nit_commerce_respond(['status' => 'error', 'error' => $e->getMessage()]);
}

/**
 * The base (pre-discount) price of an item for the discount engine. Courses resolve their price via
 * local_payments (per-course rules); other item types return null so discount_manager resolves them.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param int $userid
 * @return float|null
 */
function nit_commerce_base_price(string $itemtype, int $itemid, int $userid): ?float {
    global $CFG;
    if ($itemtype !== 'course') {
        return null;
    }
    $file = $CFG->dirroot . '/local/payments/classes/price_resolver.php';
    if (!file_exists($file) || !class_exists('\local_payments\price_resolver')) {
        return null;
    }
    try {
        $pricing = \local_payments\price_resolver::resolve($itemid, $userid);
        // Use the pre-sale original price as the discount base (offers/coupons stack on the real price).
        return (float) ($pricing->price ?? 0);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * The selectable scope targets for the coupon/offer editors: categories+courses, packages,
 * subscriptions, and programs (empty until a programs plugin lands).
 *
 * @return array
 */
function nit_commerce_discount_targets(): array {
    global $DB;

    // Categories with their courses.
    $cats = $DB->get_records('course_categories', array(), 'sortorder ASC', 'id, name');
    $courses = $DB->get_records('course', array(), 'sortorder ASC', 'id, fullname, category');
    $tree = [];
    foreach ($cats as $cat) {
        $tree[$cat->id] = ['id' => (int) $cat->id, 'name' => format_string($cat->name), 'courses' => []];
    }
    foreach ($courses as $c) {
        if ($c->category > 0 && isset($tree[$c->category])) {
            $tree[$c->category]['courses'][] = ['id' => (int) $c->id, 'fullname' => format_string($c->fullname)];
        }
    }
    $categories = [];
    foreach ($tree as $node) {
        if (!empty($node['courses'])) {
            $categories[] = $node;
        }
    }

    // Packages (from local_nit_flex) and subscriptions (from local_nit_subscriptions), when installed.
    $packages = [];
    if ($DB->get_manager()->table_exists('nit_package')) {
        foreach ($DB->get_records('nit_package', null, 'name ASC', 'id, name') as $p) {
            $packages[] = ['id' => (int) $p->id, 'name' => format_string($p->name)];
        }
    }
    $subs = [];
    if ($DB->get_manager()->table_exists('nit_subscription')) {
        foreach ($DB->get_records('nit_subscription', null, 'name ASC', 'id, name') as $s) {
            $subs[] = ['id' => (int) $s->id, 'name' => format_string($s->name)];
        }
    }

    return [
        'categories'    => $categories,
        'packages'      => $packages,
        'subscriptions' => $subs,
        'programs'      => [],
    ];
}
