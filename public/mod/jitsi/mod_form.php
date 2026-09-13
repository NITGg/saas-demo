<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_jitsi_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sessionname', 'jitsi'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Security options ─────────────────────────────────────────────────
        $mform->addElement('header', 'securityheader', get_string('security_header', 'jitsi'));

        // Room password.
        $mform->addElement('passwordunmask', 'roompassword', get_string('roompassword', 'jitsi'));
        $mform->setType('roompassword', PARAM_TEXT);
        $mform->addHelpButton('roompassword', 'roompassword', 'jitsi');

        // Lobby (waiting room — teacher must approve each participant).
        $mform->addElement('advcheckbox', 'lobby_enabled', get_string('lobby_enabled', 'jitsi'));
        $mform->addHelpButton('lobby_enabled', 'lobby_enabled', 'jitsi');
        $mform->setDefault('lobby_enabled', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
