<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_orgadmin'));
$PAGE->set_heading(get_string('pluginname', 'local_orgadmin'));

// Hide this page from Site Admins entirely.
if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

// Require the user to be an Org Admin in at least one category.
$allowed = false;
foreach (core_course_category::get_all() as $cat) {
    if (has_capability('local/orgadmin:adduser', context_coursecat::instance($cat->id))) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

echo $OUTPUT->header();
// No extra heading here (avoid duplicates).
echo $OUTPUT->notification('Org Admin landing page OK', \core\output\notification::NOTIFY_SUCCESS);
echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/adduser.php'), get_string('nav_adduser', 'local_orgadmin'));
echo $OUTPUT->footer();
