<?php
namespace local_orgadmin\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class adduser extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $cd    = $this->_customdata ?? [];

        $categories        = $cd['categories']        ?? [];
        $roles             = $cd['roles']             ?? [];
        $lockcategory      = $cd['lockcategory']      ?? false;
        $defaultcategoryid = $cd['defaultcategoryid'] ?? 0;

        // ── Organisation (Course category)
        if ($lockcategory && $defaultcategoryid && isset($categories[$defaultcategoryid])) {
            $mform->addElement('static', 'categoryid_label',
                get_string('f_category', 'local_orgadmin'),
                s($categories[$defaultcategoryid])
            );
            $mform->addElement('hidden', 'categoryid', $defaultcategoryid);
            $mform->setType('categoryid', PARAM_INT);
        } else {
            $mform->addElement('select', 'categoryid',
                get_string('f_category', 'local_orgadmin'), $categories);
            $mform->addRule('categoryid',
                get_string('err_category_required', 'local_orgadmin'), 'required', null, 'client');
            if ($defaultcategoryid && isset($categories[$defaultcategoryid])) {
                $mform->setDefault('categoryid', $defaultcategoryid);
            }
        }

        // ── Role (in the selected Org category)
        $mform->addElement('select', 'roleid', get_string('f_role', 'local_orgadmin'), $roles);
        $mform->addRule('roleid', get_string('err_role_required', 'local_orgadmin'), 'required', null, 'client');

        // ── Account fields (similar to core "Add new user")
        $mform->addElement('text', 'username', get_string('f_username', 'local_orgadmin'), ['size' => 32]);
        $mform->setType('username', PARAM_USERNAME);

        $authopts = [];
        foreach (\get_enabled_auth_plugins(true) as $auth) {
            $authopts[$auth] = \get_string('pluginname', "auth_{$auth}");
        }
        if (!$authopts) { $authopts = ['manual' => \get_string('pluginname', 'auth_manual')]; }
        $mform->addElement('select', 'auth', get_string('f_auth', 'local_orgadmin'), $authopts);
        $mform->setDefault('auth', 'manual');

        $mform->addElement('advcheckbox', 'suspended', get_string('f_suspended', 'local_orgadmin'));

        $mform->addElement('advcheckbox', 'genpassword', get_string('f_genpassword', 'local_orgadmin'));
        $mform->addElement('passwordunmask', 'password', get_string('f_password', 'local_orgadmin'), ['size' => 20]);
        $mform->setType('password', PARAM_RAW_TRIMMED);

        $mform->addElement('advcheckbox', 'forcepasswordchange', get_string('f_forcepasswordchange', 'local_orgadmin'));
        $mform->setDefault('forcepasswordchange', 1);

        $mform->addElement('text', 'firstname', get_string('f_firstname', 'local_orgadmin'), ['size' => 24]);
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'lastname', get_string('f_lastname', 'local_orgadmin'), ['size' => 24]);
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('f_email', 'local_orgadmin'), ['size' => 50]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('required'), 'required', null, 'client');

        // Email visibility (maildisplay)
        $visopts = [
            2 => get_string('emaildisplaycourse', 'local_orgadmin'), // Visible to course participants
            1 => get_string('emaildisplayall', 'local_orgadmin'),    // Visible to everyone
            0 => get_string('emaildisplayhide', 'local_orgadmin'),   // Hide from everyone
        ];
        $mform->addElement('select', 'maildisplay', get_string('f_maildisplay', 'local_orgadmin'), $visopts);
        $mform->setDefault('maildisplay', 2);

        // ── Profile picture (NEW)
        global $CFG;
        $mform->addElement(
            'filepicker',
            'userpicture',
            get_string('userpicture'), // core string
            null,
            ['maxbytes' => $CFG->maxbytes, 'accepted_types' => ['image']]
        );

        $mform->addElement('advcheckbox', 'createifmissing', get_string('f_createifmissing', 'local_orgadmin'));
        $mform->setDefault('createifmissing', 1);

        $this->add_action_buttons(true, get_string('btn_submit', 'local_orgadmin'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors['password'] = get_string('err_password_length', 'local_orgadmin');
        }
        return $errors;
    }
}