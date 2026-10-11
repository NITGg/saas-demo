<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * Video protection check: the paid courses whose video lessons are still on Vimeo.
 * Only VdoCipher lessons carry DRM (Widevine / FairPlay) and the viewer's
 * watermark; a Vimeo lesson can be downloaded or screen-recorded more easily, so
 * these are the lessons to move. Nothing is blocked — this only lists them.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_check {

    /**
     * Paid courses (local_payments pricing) with at least one Vimeo lesson.
     *
     * @return array<int, array{courseid:int, vimeo:int, vdocipher:int}> by course id
     */
    public static function paid_courses_on_vimeo(): array {
        global $DB;
        $manager = $DB->get_manager();
        $counts = [];
        foreach (['vimeo', 'vdocipher'] as $modname) {
            if (!$manager->table_exists($modname)) {
                continue;
            }
            $rows = $DB->get_records_sql(
                "SELECT cm.course, COUNT(1) AS n
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  WHERE cm.deletioninprogress = 0
               GROUP BY cm.course", ['modname' => $modname]);
            foreach ($rows as $row) {
                $counts[(int) $row->course][$modname] = (int) $row->n;
            }
        }

        $out = [];
        foreach ($counts as $courseid => $count) {
            if (empty($count['vimeo']) || !class_exists('\local_payments\price_resolver')
                    || !\local_payments\price_resolver::has_pricing($courseid)) {
                continue;
            }
            $out[$courseid] = ['courseid' => $courseid, 'vimeo' => $count['vimeo'], 'vdocipher' => $count['vdocipher'] ?? 0];
        }
        return $out;
    }
}
