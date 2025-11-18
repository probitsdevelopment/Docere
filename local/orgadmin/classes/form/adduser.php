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
        $mform->addElement('text', 'email', get_string('f_email', 'local_orgadmin'), ['size' => 30]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('err_email_required', 'local_orgadmin'), 'required', null, 'client');

        // First/Last
        $mform->addElement('text', 'firstname', get_string('f_firstname', 'local_orgadmin'), ['size' => 30]);
        $mform->setType('firstname', PARAM_NOTAGS);

        $mform->addElement('text', 'lastname', get_string('f_lastname', 'local_orgadmin'), ['size' => 30]);
        $mform->setType('lastname', PARAM_NOTAGS);

        // Username (REQUIRED)
        $mform->addElement('text', 'username', get_string('f_username', 'local_orgadmin'), ['size' => 30]);
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

    /**
     * Custom validation to ensure email is valid and properly formatted with real domain
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate email format
        if (!empty($data['email'])) {
            $email = trim(strtolower($data['email']));
            
            // Basic format validation using PHP's filter
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format. Please enter a valid email address.';
                return $errors;
            }
            
            // Additional check: ensure email has a valid domain structure
            if (strpos($email, '@') !== false) {
                list($local, $domain) = explode('@', $email, 2);
                
                // Check if domain has at least one dot (e.g., example.com)
                if (strpos($domain, '.') === false) {
                    $errors['email'] = 'Email must have a valid domain (e.g., user@example.com)';
                    return $errors;
                }
                
                // Check domain is not just a TLD or invalid
                $domainparts = explode('.', $domain);
                if (count($domainparts) < 2 || strlen($domainparts[0]) < 2) {
                    $errors['email'] = 'Email domain is invalid. Please use a proper domain (e.g., user@company.com)';
                    return $errors;
                }
                
                // Check TLD (last part) is at least 2 characters
                $tld = end($domainparts);
                if (strlen($tld) < 2) {
                    $errors['email'] = 'Email domain extension is invalid.';
                    return $errors;
                }
                
                // Reject common dummy/test domains
                $dummydomains = ['test.com', 'test', 'example', 'dummy.com', 'fake.com', 'invalid.com', 
                                'temp.com', 'temporary.com', 'sample.com', 'demo.com'];
                foreach ($dummydomains as $dummy) {
                    if (stripos($email, '@' . $dummy) !== false || stripos($domain, $dummy) !== false) {
                        $errors['email'] = 'Please use a valid working email address, not a test/dummy email.';
                        return $errors;
                    }
                }
                
                // Check if domain has valid DNS records (MX or A record)
                // This verifies the domain actually exists
                if (function_exists('checkdnsrr') && function_exists('dns_get_record')) {
                    $hasMX = checkdnsrr($domain, 'MX');
                    $hasA = checkdnsrr($domain, 'A');
                    
                    if (!$hasMX && !$hasA) {
                        $errors['email'] = 'Email domain does not exist or cannot receive emails. Please use a valid working email address.';
                        return $errors;
                    }
                }
            }
        }

        return $errors;
    }
}