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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Registered payment providers (local_payments_providers) for the mobile admin API — the list,
 * enable/disable and priority of admin/providers.php. Lower priority = tried first.
 *
 * Capability checks (local/payments:manageproviders) are the caller's job.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_manager {

    /**
     * Every registered provider, by priority (as on admin/providers.php).
     *
     * @return array
     */
    public static function get_providers(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('local_payments_providers', null, 'priority ASC, id ASC') as $p) {
            $out[] = self::export($p);
        }
        return $out;
    }

    /**
     * Change a provider's enabled flag and/or priority. A null argument leaves that value as is.
     *
     * @param int $id
     * @param bool|null $enabled
     * @param int|null $priority >= 0
     * @return array the provider after the change
     * @throws \moodle_exception err_providernotfound|err_invalidpriority|err_nothingtochange
     */
    public static function set_provider(int $id, ?bool $enabled, ?int $priority): array {
        global $DB;
        $provider = $DB->get_record('local_payments_providers', ['id' => $id]);
        if (!$provider) {
            throw new \moodle_exception('err_providernotfound', 'local_payments');
        }
        if ($enabled === null && $priority === null) {
            throw new \moodle_exception('err_nothingtochange', 'local_payments');
        }
        if ($priority !== null && $priority < 0) {
            throw new \moodle_exception('err_invalidpriority', 'local_payments');
        }
        $update = (object) ['id' => $id, 'timemodified' => time()];
        if ($enabled !== null) {
            $update->enabled = $enabled ? 1 : 0;
        }
        if ($priority !== null) {
            $update->priority = $priority;
        }
        $DB->update_record('local_payments_providers', $update);
        return self::export($DB->get_record('local_payments_providers', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * One provider as API data. Countries/currencies: [] means "all".
     *
     * @param \stdClass $p
     * @return array
     */
    protected static function export(\stdClass $p): array {
        $list = function ($raw): array {
            if ($raw === null || $raw === '' || $raw === '*') {
                return [];
            }
            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? array_values(array_map('strval', $decoded))
                : array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
        };
        return [
            'id'           => (int) $p->id,
            'name'         => (string) $p->name,
            'display_name' => format_string($p->display_name),
            'plugin_name'  => (string) $p->plugin_name,
            'enabled'      => (bool) $p->enabled,
            'priority'     => (int) $p->priority,
            'supported_countries'  => $list($p->supported_countries),
            'supported_currencies' => $list($p->supported_currencies),
            'timemodified' => (int) $p->timemodified,
        ];
    }
}
