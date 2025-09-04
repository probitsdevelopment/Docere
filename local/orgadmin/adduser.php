<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php'); // needed for process_new_icon()

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

/** Build $allowedcats and $filteredroles here (your existing code) **/

$defaultcatid  = optional_param('categoryid', 0, PARAM_INT);
if (!$defaultcatid || !array_key_exists($defaultcatid, $allowedcats)) {
    $defaultcatid = (int) array_key_first($allowedcats);
}
$lockcategory  = (count($allowedcats) === 1);

// *** INSTANTIATE THE FORM BEFORE USING IT ***
$customdata = [
    'categories'        => $allowedcats,
    'roles'             => $filteredroles,
    'lockcategory'      => $lockcategory,
    'defaultcategoryid' => $defaultcatid,
];
$mform = new \local_orgadmin\form\adduser(null, $customdata);

// Optional sanity check while debugging:
if (!$mform instanceof \moodleform) {
    throw new \moodle_exception('Form failed to build (local_orgadmin\\form\\adduser). Check file path & namespace.');
}

// Standard form workflow
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));

} else if ($data = $mform->get_data()) {
    // >>> your submit handler (create/find user, profile picture, assign role, summary) <<<
    exit;

} else {
    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
    $mform->display();
    echo $OUTPUT->footer();
}
