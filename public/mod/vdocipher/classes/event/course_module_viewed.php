<?php
namespace mod_vdocipher\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_vdocipher course module viewed event.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'vdocipher';
    }

    public static function get_objectid_mapping() {
        return ['db' => 'vdocipher', 'restore' => 'vdocipher'];
    }
}
