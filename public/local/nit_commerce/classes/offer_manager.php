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
 * CRUD + rules for automatic offers. Ported from local_academy\offer_manager (tables nit_offer*).
 *
 * Offers carry no code and no max cap — they apply automatically at checkout.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Automatic offer manager.
 */
class offer_manager {

    /** @var string Active status. */
    const STATUS_ACTIVE   = 'active';
    /** @var string Inactive status. */
    const STATUS_INACTIVE = 'inactive';

    // ── CRUD ──

    /**
     * Create an offer.
     *
     * @param array $data name, discount_type, discount_value, startdate, enddate, active, items[]
     * @param int $userid admin
     * @return int new offer id
     */
    public static function create_offer(array $data, $userid) {
        global $DB;
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_offernamerequired', 'local_nit_commerce');
        }
        $now = time();
        $record = new \stdClass();
        $record->name           = $name;
        $record->discount_type  = discount_manager::normalize_discount_type($data['discount_type'] ?? 'percent');
        $record->discount_value = self::validate_value($record->discount_type, $data['discount_value'] ?? 0);
        list($record->startdate, $record->enddate) = self::validate_dates($data['startdate'] ?? 0, $data['enddate'] ?? 0);
        $record->status         = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->timecreated    = $now;
        $record->timemodified   = $now;
        $record->usermodified   = $userid;

        $id = $DB->insert_record('nit_offer', $record);
        self::save_items($id, (array)($data['items'] ?? array()));
        return $id;
    }

    /**
     * Update an offer. Only provided fields change.
     *
     * @param int $id
     * @param array $data subset of create fields (+ status). If 'items' present it replaces scope.
     * @param int $userid admin
     * @return void
     */
    public static function update_offer($id, array $data, $userid) {
        global $DB;
        $offer = self::get_record($id);

        $update = new \stdClass();
        $update->id = $offer->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_offernamerequired', 'local_nit_commerce');
            }
            $update->name = $name;
        }
        if (array_key_exists('discount_type', $data)) {
            $update->discount_type = discount_manager::normalize_discount_type($data['discount_type']);
        }
        if (array_key_exists('discount_value', $data)) {
            $type = $update->discount_type ?? $offer->discount_type;
            $update->discount_value = self::validate_value($type, $data['discount_value']);
        }
        if (array_key_exists('startdate', $data) || array_key_exists('enddate', $data)) {
            $start = array_key_exists('startdate', $data) ? $data['startdate'] : $offer->startdate;
            $end   = array_key_exists('enddate', $data) ? $data['enddate'] : $offer->enddate;
            list($update->startdate, $update->enddate) = self::validate_dates($start, $end);
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        } else if (array_key_exists('active', $data)) {
            $update->status = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        }

        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('nit_offer', $update);

        if (array_key_exists('items', $data)) {
            self::save_items($offer->id, (array)$data['items']);
        }
    }

    /**
     * Deactivate an offer.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function deactivate_offer($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /**
     * Reactivate an offer.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function activate_offer($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /**
     * Delete an offer. Allowed only if it was never used.
     *
     * @param int $id
     * @return void
     */
    public static function delete_offer($id) {
        global $DB;
        self::get_record($id);
        if (self::has_usages($id)) {
            throw new \moodle_exception('err_offerhasusages', 'local_nit_commerce');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_offer_item', array('offerid' => $id));
        $DB->delete_records('nit_offer', array('id' => $id));
        $transaction->allow_commit();
    }

    // ── Reads ──

    /**
     * Fetch one offer (with scope + usage count) or throw.
     *
     * @param int $id
     * @return array
     */
    public static function get_offer($id) {
        return self::format(self::get_record($id));
    }

    /**
     * List offers for the admin UI, newest first.
     *
     * @param string $status '' | active | inactive
     * @return array
     */
    public static function get_offers($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        $rows = array_values($DB->get_records('nit_offer', $conditions, 'timecreated DESC'));
        return array_map(array(self::class, 'format'), $rows);
    }

    /**
     * Active, in-window offers. Each row includes its scope labels.
     *
     * @return array
     */
    public static function get_available_offers() {
        global $DB;
        $now = time();
        $rows = array_values($DB->get_records('nit_offer', array('status' => self::STATUS_ACTIVE), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            if ($r->startdate > 0 && $now < $r->startdate) { continue; }
            if ($r->enddate > 0 && $now > $r->enddate) { continue; }
            $out[] = self::format($r);
        }
        return $out;
    }

    /**
     * Whether the offer has any application records.
     *
     * @param int $id
     * @return bool
     */
    public static function has_usages($id) {
        global $DB;
        return $DB->record_exists('nit_offer_usage', array('offerid' => $id));
    }

    // ── Helpers ──

    /**
     * Fetch an offer record or throw.
     *
     * @param int $id
     * @return \stdClass
     */
    private static function get_record($id) {
        global $DB;
        $offer = $DB->get_record('nit_offer', array('id' => $id));
        if (!$offer) {
            throw new \moodle_exception('err_offernotfound', 'local_nit_commerce');
        }
        return $offer;
    }

    /**
     * Shape a record for the API: cast types + attach scope items and usage count.
     *
     * @param \stdClass $record
     * @return array
     */
    private static function format($record) {
        global $DB;
        $items = array_values($DB->get_records('nit_offer_item', array('offerid' => $record->id)));
        $applies = array();
        foreach ($items as $it) {
            $applies[] = array(
                'item_type' => $it->item_type,
                'item_id'   => (int)$it->item_id,
                'label'     => discount_manager::item_label($it->item_type, $it->item_id),
            );
        }
        return array(
            'id'             => (int)$record->id,
            // 'name' is localised for display; 'name_raw' keeps the stored {mlang} markup for editing.
            'name'           => format_string(discount_manager::resolve_mlang($record->name)),
            'name_raw'       => $record->name,
            'discount_type'  => $record->discount_type,
            'discount_value' => (float)$record->discount_value,
            'startdate'      => (int)$record->startdate,
            'enddate'        => (int)$record->enddate,
            'status'         => $record->status,
            'usage_count'    => $DB->count_records('nit_offer_usage', array('offerid' => $record->id)),
            'applies_to'     => $applies,
        );
    }

    /**
     * Replace the offer's applicable-types + scope rows.
     *
     * @param int $offerid
     * @param array $items
     * @return void
     */
    public static function save_items($offerid, array $items) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_offer_item', array('offerid' => $offerid));
        $seen = array();
        foreach ($items as $it) {
            $type = discount_manager::normalize_item_type($it['item_type'] ?? '');
            $iid  = max(0, (int)($it['item_id'] ?? 0));
            $key  = $type . ':' . $iid;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $DB->insert_record('nit_offer_item', (object) array(
                'offerid'   => $offerid,
                'item_type' => $type,
                'item_id'   => $iid,
            ));
        }
        $transaction->allow_commit();
    }

    /**
     * Validate a discount value (percent 0..100; fixed >= 0).
     *
     * @param string $type
     * @param mixed $value
     * @return float
     */
    private static function validate_value($type, $value) {
        $value = (float)$value;
        if ($value < 0) {
            throw new \moodle_exception('err_discountvalue', 'local_nit_commerce');
        }
        if ($type === discount_manager::DISCOUNT_PERCENT && $value > 100) {
            throw new \moodle_exception('err_discountpercent', 'local_nit_commerce');
        }
        return $value;
    }

    /**
     * Validate a start/end window.
     *
     * @param mixed $start
     * @param mixed $end
     * @return array [start, end]
     */
    private static function validate_dates($start, $end) {
        $start = max(0, (int)$start);
        $end = max(0, (int)$end);
        if ($start > 0 && $end > 0 && $end < $start) {
            throw new \moodle_exception('err_daterange', 'local_nit_commerce');
        }
        return array($start, $end);
    }

    /**
     * Validate a status.
     *
     * @param string $status
     * @return string
     */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_nit_commerce');
        }
        return $status;
    }

    /**
     * Set an offer status.
     *
     * @param int $id
     * @param string $status
     * @param int $userid
     * @return void
     */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_record($id);
        $DB->update_record('nit_offer', (object) array(
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
            'usermodified' => $userid,
        ));
    }
}
