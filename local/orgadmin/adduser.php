<?php
// local/orgadmin/adduser.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/passwordlib.php'); // check_password_policy()
require_once($CFG->libdir . '/gdlib.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

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

    require_sesskey();

    // Validate category.
    $categoryid = (int)$data->categoryid;
    if (!isset($allowedcats[$categoryid])) {
        redirect($PAGE->url, get_string('nopermissions', 'error'));
    }
    $catctx = context_coursecat::instance($categoryid);

    // Capability & matrix checks in the selected category.
    if (!has_capability('local/orgadmin:adduser', $catctx) || !has_capability('moodle/role:assign', $catctx)) {
        redirect($PAGE->url, get_string('nopermissions', 'error'));
    }

    $roleid = (int)$data->roleid;
    $assignable = get_assignable_roles($catctx, ROLENAME_ORIGINAL, false);
    if (!isset($assignable[$roleid])) {
        // Role not assignable here (matrix or role lacks Category context).
        redirect($PAGE->url, get_string('nopermissions', 'error'));
    }

    // Collect fields.
    $email     = trim(core_text::strtolower($data->email));
    $username  = trim($data->username);
    $firstname = trim($data->firstname);
    $lastname  = trim($data->lastname);
    $auth      = $data->auth ?? 'manual';
    $password  = (string)$data->password;

    if ($email === '' || $username === '' || $password === '') {
        redirect($PAGE->url, get_string('missingfield', 'error'));
    }

    // Ensure chosen auth is enabled; fallback to manual (older Moodle compatibility).
    $enabledauths = get_enabled_auth_plugins(); // returns enabled list
    if (!in_array($auth, $enabledauths, true)) { $auth = 'manual'; }

    // Pre-validate password against site policy.
    $errmsg = '';
    if (!check_password_policy($password, $errmsg)) {
        redirect($PAGE->url, $errmsg);
    }

    $transaction = $DB->start_delegated_transaction();

    // Find existing by email.
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

    if (!$user) {
        if (empty($data->createifmissing)) {
            $transaction->allow_commit(); // nothing changed
            redirect($PAGE->url, get_string('err_user_not_found', 'local_orgadmin'));
        }

        // Ensure unique username on this host.
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

        // New user (password will be hashed by user_create_user).
        $new              = new stdClass();
        $new->auth        = $auth ?: 'manual';
        $new->username    = $username;
        $new->password    = $password; // plain here; Moodle hashes it
        $new->firstname   = $firstname ?: 'User';
        $new->lastname    = $lastname  ?: 'Org';
        $new->email       = $email;
        $new->mnethostid  = $CFG->mnet_localhost_id;
        $new->confirmed   = 1;
        $new->timecreated = time();
        $new->timemodified= time();

        // IMPORTANT: set password on create (second arg = true).
        try {
            $userid = user_create_user($new, true, false);
        } catch (Throwable $e) {
            $transaction->rollback($e);
            redirect($PAGE->url, 'Create failed: '.$e->getMessage());
        }
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        \core\notification::success(
            get_string('msg_user_created', 'local_orgadmin', fullname($user)) .
            " (username: {$user->username}, auth: {$user->auth})"
        );
    } else {
        \core\notification::info(
            get_string('msg_user_found', 'local_orgadmin', fullname($user)) .
            " (username: {$user->username}, auth: {$user->auth})"
        );
    }

    // Assign role at the category (avoid duplicate).
    if (!$DB->record_exists('role_assignments', [
        'roleid'    => $roleid,
        'userid'    => $user->id,
        'contextid' => $catctx->id,
    ])) {
        role_assign($roleid, $user->id, $catctx->id);
    }
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    $transaction->allow_commit();

    redirect(new moodle_url('/local/orgadmin/index.php'));

} else {
    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
    $mform->display();
    echo $OUTPUT->footer();
}
