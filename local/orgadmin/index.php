
<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$systemctx = context_system::instance();
$PAGE->set_url(new moodle_url('/local/orgadmin/index.php'));
$PAGE->set_context($systemctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_orgadmin'));
$PAGE->set_heading(get_string('pluginname', 'local_orgadmin'));

// Ensure the current user has orgadmin rights in at least one category (or system).
$hasany = has_capability('local/orgadmin:adduser', $systemctx);
if (!$hasany) {
    foreach (core_course_category::get_all() as $cat) {
        $ctx = context_coursecat::instance($cat->id);
        if (has_capability('local/orgadmin:adduser', $ctx)) { $hasany = true; break; }
    }
}
if (!$hasany) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_orgadmin'));

// Simple menu: link to Add User tool.
$menu = html_writer::start_div('list-group my-4');
$menu .= html_writer::link(
    new moodle_url('/local/orgadmin/adduser.php'),
    get_string('nav_adduser', 'local_orgadmin'),
    ['class' => 'list-group-item list-group-item-action']
);
$menu .= html_writer::end_div();

echo $menu;
echo $OUTPUT->footer();
