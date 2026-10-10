<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * The account already uses its maximum number of devices (policy "block").
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class device_limit_exception extends \moodle_exception {

    /** @var int the limit that was reached */
    public int $max;

    /**
     * @param int $max the limit that was reached
     */
    public function __construct(int $max) {
        $this->max = $max;
        parent::__construct('devicelimit', 'local_nit_devices', '', $max);
    }
}
