<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class adduser_form extends moodleform {  // Updated class name
    
    public function definition() {
        global $CFG;
        
        $mform = $this->_form;
        $categoryid = $this->_customdata['categoryid'];
        
        // Header
        $mform->addElement('header', 'general', get_string('addusertitle', 'local_orgusers'));
        
        // Username
        $mform->addElement('text', 'username', get_string('username'), 'maxlength="100" size="12"');
        $mform->setType('username', PARAM_RAW);
        $mform->addRule('username', get_string('required'), 'required', null, 'client');
        
        // Password
        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'), 'maxlength="32" size="12"');
        $mform->setType('newpassword', PARAM_RAW);
        $mform->addRule('newpassword', get_string('required'), 'required', null, 'client');
        
        // First name
        $mform->addElement('text', 'firstname', get_string('firstname'), 'maxlength="100" size="30"');
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', get_string('required'), 'required', null, 'client');
        
        // Last name
        $mform->addElement('text', 'lastname', get_string('lastname'), 'maxlength="100" size="30"');
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', get_string('required'), 'required', null, 'client');
        
        // Email
        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="30"');
        $mform->setType('email', PARAM_RAW);
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->addRule('email', get_string('invalidemail'), 'email', null, 'client');
        
        // Role selection
        $roles = $this->get_assignable_roles($categoryid);
        $mform->addElement('select', 'roleid', get_string('selectrole', 'local_orgusers'), $roles);
        $mform->setType('roleid', PARAM_INT);
        
        // Hidden fields
        $mform->addElement('hidden', 'categoryid', $categoryid);
        $mform->setType('categoryid', PARAM_INT);
        
        // Submit buttons
        $this->add_action_buttons(true, get_string('createuser'));
    }
    
    private function get_assignable_roles($categoryid) {
        global $DB;
        
        $context = context_coursecat::instance($categoryid);
        $roles = get_assignable_roles($context);
        
        $filtered_roles = array();
        foreach ($roles as $roleid => $rolename) {
            $role = $DB->get_record('role', array('id' => $roleid));
            if (in_array($role->shortname, array('student', 'teacher', 'editingteacher', 'coursecreator'))) {
                $filtered_roles[$roleid] = $rolename;
            }
        }
        
        return $filtered_roles;
    }
    
    public function validation($data, $files) {
        global $DB;
        
        $errors = parent::validation($data, $files);
        
        // Check if username exists
        if ($DB->record_exists('user', array('username' => $data['username']))) {
            $errors['username'] = get_string('usernameexists');
        }
        
        // Check if email exists
        if ($DB->record_exists('user', array('email' => $data['email']))) {
            $errors['email'] = get_string('emailexists');
        }
        
        return $errors;
    }
}