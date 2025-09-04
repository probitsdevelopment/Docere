<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_orgadmin'));
$PAGE->set_heading(get_string('pluginname', 'local_orgadmin'));

// Always allow site admins in.
if (!is_siteadmin()) {
    // Allow if user has the cap at SYSTEM or in ANY category.
    $allowed = has_capability('local/orgadmin:adduser', context_system::instance());
    if (!$allowed) {
        foreach (core_course_category::get_all() as $cat) {
            if (has_capability('local/orgadmin:adduser', context_coursecat::instance($cat->id))) {
                $allowed = true; break;
            }
        }
    }
    if (!$allowed) {
        // Fall back to a clear message for now.
        print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_orgadmin'));

// Simple content so we KNOW the page loaded.
echo html_writer::div('Org Admin landing page OK', 'alert alert-success');
echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/adduser.php'), get_string('nav_adduser', 'local_orgadmin'));

echo $OUTPUT->footer();
