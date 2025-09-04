<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once('adduser_form.php');

// Get parameters
$categoryid = required_param('categoryid', PARAM_INT);

// Security checks
require_login();
$context = context_coursecat::instance($categoryid);

// CRITICAL: Check if user is ORG ADMIN (not site admin or regular user)
if (!is_org_admin_for_category($USER->id, $categoryid)) {
    print_error('nopermissions', '', '', 'You must be an organization administrator for this category');
}

// Additional capability check
require_capability('local/orgusers:adduser', $context);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/local/orgusers/adduser.php', array('categoryid' => $categoryid));
$PAGE->set_title(get_string('addusertitle', 'local_orgusers'));
$PAGE->set_heading(get_string('addusertitle', 'local_orgusers'));
$PAGE->navbar->add(get_string('adduser', 'local_orgusers'));

// Get category info
$category = $DB->get_record('course_categories', array('id' => $categoryid), '*', MUST_EXIST);

// Initialize form
$mform = new adduser_form(null, array('categoryid' => $categoryid));

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/index.php', array('categoryid' => $categoryid)));
    
} else if ($data = $mform->get_data()) {
    
    // Create user
    $newuser = new stdClass();
    $newuser->auth = 'manual';
    $newuser->confirmed = 1;
    $newuser->mnethostid = $CFG->mnet_localhost_id;
    $newuser->username = $data->username;
    $newuser->password = hash_internal_user_password($data->newpassword);
    $newuser->firstname = $data->firstname;
    $newuser->lastname = $data->lastname;
    $newuser->email = $data->email;
    $newuser->lang = $CFG->lang;
    $newuser->timezone = $CFG->timezone;
    $newuser->timecreated = time();
    $newuser->timemodified = time();
    
    try {
        // Insert user
        $newuser->id = $DB->insert_record('user', $newuser);
        
        // Assign role at category level
        if (!empty($data->roleid)) {
            role_assign($data->roleid, $newuser->id, $context->id);
        }
        
        // Trigger user created event
        $event = \core\event\user_created::create_from_userid($newuser->id);
        $event->trigger();
        
        // Success message
        \core\notification::success(get_string('useraddedsuccessfully', 'local_orgusers'));
        
        // Redirect
        redirect(new moodle_url('/local/orgusers/adduser.php', array('categoryid' => $categoryid)));
        
    } catch (Exception $e) {
        \core\notification::error('Error creating user: ' . $e->getMessage());
    }
}

// Output page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addusertitle', 'local_orgusers') . ' - ' . format_string($category->name));
echo html_writer::tag('p', get_string('adduser', 'local_orgusers') . ' to organization: <strong>' . format_string($category->name) . '</strong>');
$mform->display();
echo $OUTPUT->footer();
?>