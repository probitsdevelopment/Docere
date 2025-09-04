<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_login();

$systemctx = context_system::instance();
$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context($systemctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

// Hide from Site Admins completely.
if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

// Build allowed categories (where current user has the cap).
$allowedcats = [];
foreach (core_course_category::get_all() as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    if (has_capability('local/orgadmin:adduser', $ctx)) {
        $allowedcats[$cat->id] = $cat->get_formatted_name();
    }
}
if (empty($allowedcats)) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

// Build role list from first allowed category & filter shortnames.
$firstcatid = (int) array_key_first($allowedcats);
$firstctx   = context_coursecat::instance($firstcatid);

// Roles to allow in the dropdown (by shortname).
$whitelistshortnames = ['stakeholder', 'student', 'teacher', 'editingteacher', 'ld'];

// Get assignable roles at that category.
$assignable = get_assignable_roles($firstctx, ROLENAME_ORIGINAL, false); // [roleid => name]

global $DB;
$roleid2short = [];
if ($assignable) {
    list($in, $params) = $DB->get_in_or_equal(array_keys($assignable), SQL_PARAMS_NAMED);
    $records = $DB->get_records_select('role', "id $in", $params, '', 'id,shortname');
    foreach ($records as $r) { $roleid2short[$r->id] = $r->shortname; }
}
$filteredroles = [];
foreach ($assignable as $rid => $rname) {
    $sn = $roleid2short[$rid] ?? '';
    if (in_array($sn, $whitelistshortnames, true)) { $filteredroles[$rid] = $rname; }
}
// If nothing left (e.g., allow matrix not set), fetch by shortnames directly.
if (empty($filteredroles)) {
    $need = $DB->get_records_list('role', 'shortname', $whitelistshortnames, '', 'id,shortname,name');
    foreach ($need as $r) { $filteredroles[$r->id] = $r->name ?: $r->shortname; }
}

// Load form.
$customdata = ['categories' => $allowedcats, 'roles' => $filteredroles];
$formclass = '\local_orgadmin\form\adduser';
$mform = new $formclass(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));
} else if ($data = $mform->get_data()) {
    global $CFG, $DB;

    $categoryid = (int)$data->categoryid;
    $roleid     = (int)$data->roleid;
    $email      = trim(core_text::strtolower($data->email));
    $firstname  = trim($data->firstname ?? '');
    $lastname   = trim($data->lastname ?? '');
    $username   = trim($data->username ?? '');
    $password   = (string)($data->password ?? '');
    $create     = !empty($data->createifmissing);

    $catctx = context_coursecat::instance($categoryid);

    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    $created = false; $temppass = '';

    if (!$user) {
        if (!$create) { print_error('err_user_not_found', 'local_orgadmin'); }

        if ($username === '') { $username = preg_replace('/[^a-z0-9._-]+/i', '', explode('@', $email, 2)[0]); }
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }
        if ($password === '') { $password = random_string(12); $temppass = $password; }

        $new = new stdClass();
        $new->auth = 'manual';
        $new->username = $username;
        $new->password = $password;
        $new->firstname = $firstname ?: 'User';
        $new->lastname  = $lastname  ?: 'Org';
        $new->email = $email;
        $new->mnethostid = $CFG->mnet_localhost_id;
        $new->confirmed = 1;
        $new->timecreated = time();
        $new->timemodified = time();
        $new->maildisplay = 1;
        $new->timezone = 99;
        $new->lang = current_language();
        $new->forcepasswordchange = ($temppass !== '') ? 1 : 0;

        $userid = user_create_user($new, false, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
        $created = true;
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
    }

    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');

    $tbl = new html_table();
    $tbl->attributes['class'] = 'generaltable';
    $tbl->data[] = [get_string($created ? 'summary_created' : 'summary_existing', 'local_orgadmin'), fullname($user) . ' &lt;' . s($user->email) . '&gt;'];
    $tbl->data[] = [get_string('summary_username', 'local_orgadmin'), s($user->username)];
    if ($temppass !== '') {
        $tbl->data[] = [get_string('summary_temp_password', 'local_orgadmin'), s($temppass) . ' (user changes on first login)'];
    }
    $rolename = $filteredroles[$roleid] ?? $DB->get_field('role', 'name', ['id' => $roleid]);
    if (!$rolename) { $rolename = '#'.$roleid; }
    $catname = $allowedcats[$categoryid] ?? ('#'.$categoryid);
    $tbl->data[] = [get_string('summary_role', 'local_orgadmin'), s($rolename)];
    $tbl->data[] = [get_string('summary_category', 'local_orgadmin'), s($catname)];

    echo html_writer::table($tbl);
    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/adduser.php'), get_string('nav_adduser', 'local_orgadmin'));
    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/index.php'), get_string('pluginname', 'local_orgadmin'));
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
