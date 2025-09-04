<?php
namespace local_orgadmin\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class adduser extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $cd    = $this->_customdata ?? [];
        $categories = $cd['categories'] ?? [];
        $roles      = $cd['roles'] ?? [];

        // Organisation (category)
        $mform->addElement('select', 'categoryid', get_string('f_category', 'local_orgadmin'), $categories);
        $mform->addRule('categoryid', get_string('err_category_required', 'local_orgadmin'), 'required', null, 'client');

        // Role
        $mform->addElement('select', 'roleid', get_string('f_role', 'local_orgadmin'), $roles);
        $mform->addRule('roleid', get_string('err_role_required', 'local_orgadmin'), 'required', null, 'client');

        // Email (lookup key)
        $mform->addElement('text', 'email', get_string('f_email', 'local_orgadmin'), ['size' => 50]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('err_email_required', 'local_orgadmin'), 'required', null, 'client');

        // First/Last
        $mform->addElement('text', 'firstname', get_string('f_firstname', 'local_orgadmin'), ['size' => 24]);
        $mform->setType('firstname', PARAM_NOTAGS);

        $mform->addElement('text', 'lastname', get_string('f_lastname', 'local_orgadmin'), ['size' => 24]);
        $mform->setType('lastname', PARAM_NOTAGS);

        // Username (REQUIRED)
        $mform->addElement('text', 'username', get_string('f_username', 'local_orgadmin'), ['size' => 32]);
        $mform->setType('username', PARAM_USERNAME);
        $mform->addRule('username', get_string('required'), 'required', null, 'client');

        // Authentication method (default Manual accounts)
        $authopts = [];
        foreach (\get_enabled_auth_plugins(true) as $auth) {
            $authopts[$auth] = \get_string('pluginname', "auth_{$auth}");
        }
        if (!$authopts) { $authopts = ['manual' => \get_string('pluginname', 'auth_manual')]; }
        $mform->addElement('select', 'auth', get_string('f_auth', 'local_orgadmin'), $authopts);
        $mform->setDefault('auth', 'manual');

        // Password (REQUIRED)
        $mform->addElement('passwordunmask', 'password', get_string('f_password', 'local_orgadmin'), ['size' => 20]);
        $mform->setType('password', PARAM_RAW_TRIMMED);
        $mform->addRule('password', get_string('required'), 'required', null, 'client');

        // Create if missing
        $mform->addElement('advcheckbox', 'createifmissing', get_string('f_createifmissing', 'local_orgadmin'));
        $mform->setDefault('createifmissing', 1);

        $this->add_action_buttons(true, get_string('btn_submit', 'local_orgadmin'));
    }
}
