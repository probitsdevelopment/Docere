<?php
// Make sure this is the VERY FIRST line in the file (no spaces/BOM above!)

namespace local_orgadmin\form;

defined('MOODLE_INTERNAL') || die();

// $CFG is a global; bring it into scope before using it
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class adduser_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // Custom data: [rolechoices, coursechoices, categoryname]
        [$rolechoices, $coursechoices, $catname] = $this->_customdata;

        $mform->addElement('static', 'orglabel', get_string('adduser'),
            get_string('adduserfor', 'local_orgadmin', $catname));

        $mform->addElement('text', 'username', get_string('username'));
        $mform->setType('username', PARAM_USERNAME);
        $mform->addRule('username', null, 'required');

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required');

        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required');

        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required');

        $mform->addElement('passwordunmask', 'password', get_string('password'));
        $mform->addRule('password', null, 'required');

        $mform->addElement('advcheckbox', 'addenrol', get_string('addenrol','local_orgadmin'));

        $mform->addElement('select', 'roleid', get_string('role'), $rolechoices);
        $mform->disabledIf('roleid', 'addenrol', 'notchecked');

        $mform->addElement('select', 'courseids', get_string('courses','local_orgadmin'),
            $coursechoices, ['multiple' => 'multiple', 'size' => 10]);
        $mform->disabledIf('courseids', 'addenrol', 'notchecked');

        $this->add_action_buttons(true, get_string('createuser','local_orgadmin'));
    }
}
