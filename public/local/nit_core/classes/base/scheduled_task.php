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

namespace local_nit_core\base;

/**
 * Base scheduled task: wraps run() with structured logging.
 *
 * Subclasses implement get_name() and run(); the base owns tracing and
 * re-throws on failure so Moodle's scheduler applies its retry/backoff.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class scheduled_task extends \core\task\scheduled_task {
    /**
     * The task's actual work. Exceptions propagate to trigger a reschedule.
     *
     * @return void
     */
    abstract protected function run(): void;

    /**
     * Run the task, tracing start/finish and re-throwing on failure.
     *
     * @return void
     */
    public function execute() {
        $name = $this->get_name();
        mtrace("[nit] scheduled task start: {$name}");
        try {
            $this->run();
            mtrace("[nit] scheduled task done: {$name}");
        } catch (\Throwable $e) {
            mtrace("[nit] scheduled task FAILED: {$name} - " . $e->getMessage());
            throw $e;
        }
    }
}
