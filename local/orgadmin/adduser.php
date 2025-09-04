<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

/* ---------- Allowed orgs (categories) ---------- */
$allowedcats = [];
foreach (core_course_category::get_all() as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    if (has_capability('local/orgadmin:adduser', $ctx)) {
        $allowedcats[$cat->id] = $cat->get_formatted_name();
    }
}
if (!$allowedcats) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

/* ---------- Whitelisted roles ---------- */
global $DB;
$whitelistshortnames = ['stakeholder', 'student', 'teacher', 'editingteacher', 'ld'];

$firstcatid = (int) array_key_first($allowedcats);
$firstctx   = context_coursecat::instance($firstcatid);

$assignable = get_assignable_roles($firstctx, ROLENAME_ORIGINAL, false); // [roleid => name]

$roleid2short = [];
if ($assignable) {
    list($in, $params) = $DB->get_in_or_equal(array_keys($assignable), SQL_PARAMS_NAMED);
    $recs = $DB->get_records_select('role', "id $in", $params, '', 'id,shortname');
    foreach ($recs as $r) { $roleid2short[$r->id] = $r->shortname; }
}

$filteredroles = [];
foreach ($assignable as $rid => $rname) {
    $sn = $roleid2short[$rid] ?? '';
    if (in_array($sn, $whitelistshortnames, true)) { $filteredroles[$rid] = $rname; }
}
if (!$filteredroles) {
    $need = $DB->get_records_list('role', 'shortname', $whitelistshortnames, '', 'id,shortname,name');
    foreach ($need as $r) { $filteredroles[$r->id] = $r->name ?: $r->shortname; }
}

/* ---------- Build and show the form ---------- */
$customdata = [
    'categories' => $allowedcats,
    'roles'      => $filteredroles,
];
$mform = new \local_orgadmin\form\adduser(null, $customdata);

echo $OUTPUT->header();
echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
