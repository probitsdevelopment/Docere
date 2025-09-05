<?php
// local/orgadmin/adduser.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php'); // for process_new_icon() if you add avatar later

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

// Per your requirement: hide from site admins.
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
global $DB, $CFG, $OUTPUT;
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

/* ---------- Build the form ---------- */
$customdata = [
    'categories' => $allowedcats,
    'roles'      => $filteredroles,
];
$mform = new \local_orgadmin\form\adduser(null, $customdata);

/* ---------- Workflow ---------- */
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));

} else if ($data = $mform->get_data()) {

    // Validate category and role.
    $categoryid = (int)$data->categoryid;
    if (!isset($allowedcats[$categoryid])) {
        print_error('nopermissions', 'error', '', 'category');
    }
    $catctx = context_coursecat::instance($categoryid);

    $roleid = (int)$data->roleid;
   // 0) Make absolutely sure we are testing the selected category context.
$catctx = context_coursecat::instance($categoryid);

// 1) You must hold BOTH capabilities in this category:
$missing = [];
if (!has_capability('local/orgadmin:adduser', $catctx)) {
    $missing[] = 'local/orgadmin:adduser';
}
if (!has_capability('moodle/role:assign', $catctx)) {
    $missing[] = 'moodle/role:assign';
}

// 2) If anything is missing, show it clearly and stop.
if ($missing) {
    \core\notification::error('Missing capability in this category: '.implode(', ', $missing));
    echo $OUTPUT->footer();
    exit;
}

// 3) Check the allow-assign matrix result in THIS category.
$assignable = get_assignable_roles($catctx, ROLENAME_ORIGINAL, false); // [roleid => name]

// Optional: show what Moodle thinks you can assign here.
\core\notification::info('Assignable roles in this category: '.implode(', ', array_values($assignable)));

$roleid = (int)$data->roleid;
if (!isset($assignable[$roleid])) {
    $rshort = $DB->get_field('role', 'shortname', ['id' => $roleid]);
    $rolename = isset($assignable[$roleid]) ? $assignable[$roleid] : $rshort;

    $msg  = "This role is NOT assignable here: {$rolename}.";
    $msg .= " Reasons: (a) matrix doesn’t allow Acme Org Admin → {$rshort},";
    $msg .= " or (b) that role is not allowed in Category context.";

    \core\notification::error($msg);
    echo $OUTPUT->footer();
    exit;
}


    // Find/create user.
    $email     = trim(core_text::strtolower($data->email));
    $username  = trim($data->username);
    $firstname = trim($data->firstname);
    $lastname  = trim($data->lastname);
    $auth      = $data->auth ?? 'manual';
    $password  = (string)$data->password;

    if ($email === '' || $username === '' || $password === '') {
        print_error('missingfield', 'error'); // form should have blocked this already
    }

    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

    if (!$user) {
        if (empty($data->createifmissing)) {
            print_error('err_user_not_found', 'local_orgadmin');
        }

        // Ensure unique username on this host.
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

        $new              = new stdClass();
        $new->auth        = $auth ?: 'manual';
        $new->username    = $username;
        $new->password    = $password;
        $new->firstname   = $firstname ?: 'User';
        $new->lastname    = $lastname  ?: 'Org';
        $new->email       = $email;
        $new->mnethostid  = $CFG->mnet_localhost_id;
        $new->confirmed   = 1;
        $new->timecreated = time();
        $new->timemodified= time();

        $userid = user_create_user($new, true, false);
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
    }

    // Assign role at the category.
    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    // Redirect back to the dashboard (or stay on the form—your choice).
    redirect(new moodle_url('/local/orgadmin/index.php'));

} else {
    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
    $mform->display();
    echo $OUTPUT->footer();
}