<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

class mod_jitsi_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sessionname', 'jitsi'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Session time ─────────────────────────────────────────────────────
        // Not for a lesson room: its lesson sets its time.
        if (!jitsi_is_lesson_room((object) ['course' => $this->get_course()->id])) {
            $mform->addElement('header', 'scheduleheader', get_string('schedule_header', 'jitsi'));
            $mform->setExpanded('scheduleheader');
            $mform->addElement('date_time_selector', 'sessionstart', get_string('sessionstart', 'jitsi'),
                ['optional' => true]);
            $mform->addHelpButton('sessionstart', 'sessionstart', 'jitsi');
            $mform->addElement('date_time_selector', 'sessionend', get_string('sessionend', 'jitsi'));
            $mform->addHelpButton('sessionend', 'sessionend', 'jitsi');
            $mform->setDefault('sessionend', time() + HOURSECS);
            $mform->hideIf('sessionend', 'sessionstart[enabled]', 'notchecked');
        }

        // ── Security options ─────────────────────────────────────────────────
        $mform->addElement('header', 'securityheader', get_string('security_header', 'jitsi'));

        // Room password.
        $mform->addElement('passwordunmask', 'roompassword', get_string('roompassword', 'jitsi'));
        $mform->setType('roompassword', PARAM_TEXT);
        $mform->addHelpButton('roompassword', 'roompassword', 'jitsi');

        // Waiting room: the Jitsi lobby; students knock and the teacher admits them in the meeting.
        $mform->addElement('advcheckbox', 'lobby_enabled', get_string('lobby_enabled', 'jitsi'));
        $mform->addHelpButton('lobby_enabled', 'lobby_enabled', 'jitsi');
        $mform->setDefault('lobby_enabled', 1);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        jitsi_schedule_form_defaults($defaultvalues);
    }

    public function validation($data, $files) {
        return array_merge(parent::validation($data, $files), jitsi_schedule_errors($data));
    }
}
