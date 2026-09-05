<?php
namespace mod_vimeo\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_vimeo course module viewed event.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'vimeo';
    }

    public static function get_objectid_mapping() {
        return ['db' => 'vimeo', 'restore' => 'vimeo'];
    }
}
