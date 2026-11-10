<?php
namespace local_academy\local;

use mod_quiz\access_manager;
use mod_quiz\quiz_settings;

/**
 * The quiz access rules without the Safe Exam Browser rule.
 *
 * For attempts in the Android app's exam mode (there is no SEB for Android): every other
 * rule (open/close dates, IP, attempts, delay between attempts…) still applies.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_access_without_seb extends access_manager {

    /**
     * The access manager quiz_settings::get_access_manager() would build, minus quizaccess_seb.
     */
    public static function for_quiz(quiz_settings $quizobj, int $timenow): self {
        return new self($quizobj, $timenow,
            has_capability('mod/quiz:ignoretimelimits', $quizobj->get_context(), null, false));
    }

    protected function make_rules(quiz_settings $quizobj, int $timenow, bool $canignoretimelimits): array {
        $rules = parent::make_rules($quizobj, $timenow, $canignoretimelimits);
        unset($rules['quizaccess_seb']);
        return $rules;
    }
}
