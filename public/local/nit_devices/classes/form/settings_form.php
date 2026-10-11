<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_devices\manager;

/**
 * The device limit settings, on the plugin's one page (devices.php).
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_nit_devices'));
        $this->describe('enabled');

        $mform->addElement('select', 'maxdevices', get_string('maxdevices', 'local_nit_devices'), array_combine(range(1, 10), range(1, 10)));
        $this->describe('maxdevices');

        $mform->addElement('select', 'policy', get_string('policy', 'local_nit_devices'), [
            manager::POLICY_BLOCK => get_string('policyblock', 'local_nit_devices'),
            manager::POLICY_REPLACE => get_string('policyreplace', 'local_nit_devices'),
        ]);
        $this->describe('policy');

        $mform->addElement('advcheckbox', 'allowlegacylogin', get_string('allowlegacylogin', 'local_nit_devices'));
        $this->describe('allowlegacylogin');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * The setting's explanation under it.
     *
     * @param string $name
     */
    private function describe(string $name): void {
        $this->_form->addElement('static', $name . '_desc', '',
            \html_writer::span(get_string($name . '_desc', 'local_nit_devices'), 'text-muted small'));
    }

    /**
     * The values in force (the defaults where none was saved yet).
     *
     * @return array
     */
    public static function current(): array {
        return [
            'enabled' => (int) manager::enabled(),
            'maxdevices' => manager::max_devices(),
            'policy' => manager::policy(),
            'allowlegacylogin' => (int) manager::legacy_login_allowed(),
        ];
    }

    /**
     * Save the submitted values.
     *
     * @param \stdClass $data
     */
    public static function save(\stdClass $data): void {
        set_config('enabled', empty($data->enabled) ? 0 : 1, 'local_nit_devices');
        set_config('maxdevices', max(1, min(10, (int) $data->maxdevices)), 'local_nit_devices');
        set_config('policy', $data->policy === manager::POLICY_REPLACE ? manager::POLICY_REPLACE : manager::POLICY_BLOCK,
            'local_nit_devices');
        set_config('allowlegacylogin', empty($data->allowlegacylogin) ? 0 : 1, 'local_nit_devices');
    }
}
