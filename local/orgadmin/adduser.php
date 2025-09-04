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

// Block site admins (as per your requirement).
if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

// Build list of categories where current user has orgadmin cap.
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

// Build role whitelist.
$firstcatid = (int) array_key_first($allowedcats);
$firstctx   = context_coursecat::instance($firstcatid);
$whitelistshortnames = ['stakeholder', 'student', 'teacher', 'editingteacher', 'ld'];
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
if (!$filteredroles) {
    $need = $DB->get_records_list('role', 'shortname', $whitelistshortnames, '', 'id,shortname,name');
    foreach ($need as $r) { $filteredroles[$r->id] = $r->name ?: $r->shortname; }
}

// Defaults for org picker behaviour.
$defaultcatid  = optional_param('categoryid', 0, PARAM_INT);
if (!$defaultcatid || !array_key_exists($defaultcatid, $allowedcats)) {
    $defaultcatid = (int) array_key_first($allowedcats);
}
$lockcategory  = (count($allowedcats) === 1);

// Load the form.
$customdata = [
    'categories'        => $allowedcats,
    'roles'             => $filteredroles,
    'lockcategory'      => $lockcategory,
    'defaultcategoryid' => $defaultcatid,
];
$formclass = '\local_orgadmin\form\adduser';
$mform = new $formclass(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));
} else if ($data = $mform->get_data()) {
    // Read submitted fields.
    $categoryid = (int)$data->categoryid;
    $roleid     = (int)$data->roleid;

    $email      = trim(core_text::strtolower($data->email));
    $firstname  = trim($data->firstname ?? '');
    $lastname   = trim($data->lastname ?? '');
    $username   = trim($data->username ?? '');
    $auth       = trim($data->auth ?? 'manual');
    $suspended  = !empty($data->suspended) ? 1 : 0;
    $genpass    = !empty($data->genpassword);
    $password   = (string)($data->password ?? '');
    $forcechange= !empty($data->forcepasswordchange) ? 1 : 0;
    $maildisplay= isset($data->maildisplay) ? (int)$data->maildisplay : 2;
    $create     = !empty($data->createifmissing);

    $catctx = context_coursecat::instance($categoryid);

    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    $created = false; $temppass = '';

    if (!$user) {
        if (!$create) { print_error('err_user_not_found', 'local_orgadmin'); }

        // Username default from email if blank.
        if ($username === '') {
            $username = preg_replace('/[^a-z0-9._-]+/i', '', explode('@', $email, 2)[0]);
        }
        // Ensure unique username on this mnet host.
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

        // Decide password.
        if ($genpass || $password === '') {
            $password = random_string(12);
            $temppass = $password;
            $forcechange = 1;
        }

        $new = new stdClass();
        $new->auth        = $auth ?: 'manual';
        $new->suspended   = $suspended;
        $new->username    = $username;
        $new->password    = $password;
        $new->firstname   = $firstname ?: 'User';
        $new->lastname    = $lastname  ?: 'Org';
        $new->email       = $email;
        $new->maildisplay = $maildisplay;
        $new->mnethostid  = $CFG->mnet_localhost_id;
        $new->confirmed   = 1;
        $new->timecreated = time();
        $new->timemodified= time();
        $new->lang        = current_language();
        $new->forcepasswordchange = $forcechange;

        $userid = user_create_user($new, false, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
        $created = true;
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
    }

    // ── NEW: handle profile picture (for new or existing user if a file was uploaded).
    $draftid = file_get_submitted_draft_itemid('userpicture');
    if (!empty($draftid)) {
        $userctx = context_user::instance($user->id);
        $itemid = process_new_icon($userctx, 'user', 'icon', 0, $draftid); // stores/resizes
        if ($itemid) {
            $user->picture = $itemid;
            user_update_user($user, false, false);
        }
    }

    // Assign role at category.
    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    // Render summary.
    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');

    $tbl = new html_table();
    $tbl->attributes['class'] = 'generaltable';
    $tbl->data[] = [get_string($created ? 'summary_created' : 'summary_existing', 'local_orgadmin'), fullname($user) . ' &lt;' . s($user->email) . '&gt;'];
    $tbl->data[] = [get_string('summary_username', 'local_orgadmin'), s($user->username)];
    if ($temppass !== '') {
        $tbl->data[] = [get_string('summary_temp_password', 'local_orgadmin'), s($temppass) . ' (user changes on first login)'];
    }
    $rolename = $filteredroles[$roleid] ?? $DB->get_field('role', 'name', ['id' => $roleid]) ?: '#'.$roleid;
    $catname  = $allowedcats[$categoryid] ?? ('#'.$categoryid);
    $tbl->data[] = [get_string('summary_role', 'local_orgadmin'), s($rolename)];
    $tbl->data[] = [get_string('summary_category', 'local_orgadmin'), s($catname)];

    echo html_writer::table($tbl);
    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/adduser.php'), get_string('nav_adduser', 'local_orgadmin'));
    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/index.php'), get_string('pluginname', 'local_orgadmin'));
    echo $OUTPUT->footer();
    exit;
}

// First load: show form.
echo $OUTPUT->header();
echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
