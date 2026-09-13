<?php
/**
 * Web service definitions for mod_jitsi.
 *
 * Exposes mod_jitsi_get_session_info for mobile apps — returns everything
 * needed to render a Jitsi session natively: JWT, room, whiteboard URL, recordings.
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_jitsi_get_session_info' => [
        'classname'     => 'mod_jitsi\external\get_session_info',
        'methodname'    => 'execute',
        'description'   => 'Get Jitsi session details for mobile: JWT, room, whiteboard URL, recordings.',
        'type'          => 'read',
        'capabilities'  => 'mod/jitsi:view',
        'ajax'          => true,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
