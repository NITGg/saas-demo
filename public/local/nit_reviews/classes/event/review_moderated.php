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

namespace local_nit_reviews\event;

/**
 * A moderator approved, rejected or deleted a review — the audit trail of the
 * moderation page (Site administration → Reports → Logs).
 *
 * other: action (approved|rejected|deleted), courseid, teacherid, rating.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_moderated extends \core\event\base {

    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_nit_reviews';
    }

    /**
     * The event for one moderation action on a review.
     *
     * @param \stdClass $review the review record (as it was, for a delete)
     * @param \context $context the review's course, or the site
     * @param string $action approved | rejected | deleted
     * @return self
     */
    public static function create_from_review(\stdClass $review, \context $context, string $action): self {
        $event = self::create([
            'context' => $context,
            'objectid' => (int) $review->id,
            'relateduserid' => (int) $review->userid,
            'other' => [
                'action' => $action,
                'courseid' => (int) $review->courseid,
                'teacherid' => (int) $review->teacherid,
                'rating' => (int) $review->rating,
            ],
        ]);
        if ($action === 'deleted') {
            $event->add_record_snapshot('local_nit_reviews', $review);
        }
        return $event;
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreviewmoderated', 'local_nit_reviews');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $target = $this->other['teacherid'] ? "of the teacher with id '{$this->other['teacherid']}'" : 'of the course';
        return "The user with id '{$this->userid}' {$this->other['action']} the review with id '{$this->objectid}' "
            . "$target written by the user with id '{$this->relateduserid}'.";
    }

    /**
     * The moderation page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/nit_reviews/moderate.php');
    }

    /**
     * Validate.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['action'])) {
            throw new \coding_exception('The \'action\' value must be set in other.');
        }
    }

    /**
     * Restore mapping (reviews are not backed up).
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_nit_reviews', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
