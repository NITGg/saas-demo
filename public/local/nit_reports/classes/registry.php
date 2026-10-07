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

namespace local_nit_reports;

/**
 * The list of reports, in display order.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {

    /** Report classes, in tab order. */
    private const REPORTS = [
        report\students::class,
        report\teachers::class,
        report\courses::class,
        report\student_results::class,
        report\videos::class,
        report\sales::class,
        report\subscriptions::class,
        report\packages::class,
        report\lessons::class,
        report\discounts::class,
        report\codes::class,
        report\teacher_dues::class,
    ];

    /**
     * The reports a user may open.
     *
     * @param scope $scope
     * @return array<string,string> key => class
     */
    public static function for_scope(scope $scope): array {
        $out = [];
        foreach (self::REPORTS as $class) {
            if (class_exists($class) && $class::available() && $scope->can($class::level())) {
                $out[$class::key()] = $class;
            }
        }
        return $out;
    }
}
