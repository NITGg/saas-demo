<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

class mod_jitsi_mod_form extends moodleform_mod {

    /** @var int default length of a session, minutes. */
    const DEFAULT_DURATION = 60;

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
            $mform->addElement('text', 'sessionduration', get_string('sessionduration', 'jitsi'), ['size' => 5]);
            $mform->setType('sessionduration', PARAM_INT);
            $mform->setDefault('sessionduration', self::DEFAULT_DURATION);
            $mform->hideIf('sessionduration', 'sessionstart[enabled]', 'notchecked');
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
        global $DB;
        $errors = parent::validation($data, $files);
        if (empty($data['sessionstart'])) {
            return $errors;
        }
        $duration = (int) ($data['sessionduration'] ?? 0);
        if ($duration <= 0) {
            $errors['sessionduration'] = get_string('sessionduration_invalid', 'jitsi');
            return $errors;
        }
        // A time that is already over is a mistake (a wrong day), unless it is the time the
        // session already had: editing other settings of a past session is fine.
        $session = !empty($data['instance'])
            ? $DB->get_record('academy_live_sessions', ['jitsiid' => $data['instance']]) : null;
        $unchanged = $session && (int) $session->start_time === (int) $data['sessionstart']
            && (int) $session->duration === $duration;
        if (!$unchanged && (int) $data['sessionstart'] + $duration * MINSECS <= time()) {
            $errors['sessionstart'] = get_string('sessionstart_past', 'jitsi');
        }
        return $errors;
    }
}
