<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php'); // user_create_user()
require_login();

$systemctx = context_system::instance();
$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context($systemctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

// Check: user must have capability in at least one category (or system).
$allowedcats = [];
if (has_capability('local/orgadmin:adduser', $systemctx)) {
    // System-level org admin: can see all categories.
    $allowedcats = core_course_category::make_categories_list();
} else {
    // Limit to categories where the user holds the capability.
    foreach (core_course_category::get_all() as $cat) {
        $ctx = context_coursecat::instance($cat->id);
        if (has_capability('local/orgadmin:adduser', $ctx)) {
            $allowedcats[$cat->id] = $cat->get_formatted_name();
        }
    }
}
if (empty($allowedcats)) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

// Build roles list (from system scope; you can filter further if desired).
$rolenames = get_assignable_roles($systemctx, ROLENAME_ORIGINAL, false); // roleid => name

// Load form.
$customdata = ['categories' => $allowedcats, 'roles' => $rolenames];
$formclass = '\local_orgadmin\form\adduser';
$mform = new $formclass(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));
} else if ($data = $mform->get_data()) {
    global $DB;

    $categoryid = (int)$data->categoryid;
    $roleid     = (int)$data->roleid;
    $email      = trim(core_text::strtolower($data->email));
    $firstname  = trim($data->firstname ?? '');
    $lastname   = trim($data->lastname ?? '');
    $username   = trim($data->username ?? '');
    $password   = (string)($data->password ?? '');
    $create     = !empty($data->createifmissing);

    // Choose context for assignment.
    $catctx = context_coursecat::instance($categoryid);

    // Find user by email.
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

    $created = false;
    $temppass = '';

    if (!$user) {
        if (!$create) {
            print_error('err_user_not_found', 'local_orgadmin');
        }

        // Create new user (manual auth). Username default from email if empty.
        if ($username === '') {
            $username = preg_replace('/[^a-z0-9._-]+/i', '', explode('@', $email, 2)[0]);
        }

        // Make username unique if needed.
        $base = $username;
        $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

        // Generate password if not provided.
        if ($password === '') {
            $password = random_string(12);
            $temppass = $password;
        }

        $new = new stdClass();
        $new->auth                 = 'manual';
        $new->username             = $username;
        $new->password             = $password;
        $new->firstname            = $firstname ?: 'User';
        $new->lastname             = $lastname  ?: 'Org';
        $new->email                = $email;
        $new->mnethostid           = $CFG->mnet_localhost_id;
        $new->confirmed            = 1;
        $new->timecreated          = time();
        $new->timemodified         = time();
        $new->maildisplay          = 1;
        $new->city                 = '';
        $new->country              = '';
        $new->description          = '';
        $new->timezone             = 99;
        $new->lang                 = current_language();
        $new->forcepasswordchange  = ($temppass !== '') ? 1 : 0;

        $userid = user_create_user($new, false, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
        $created = true;
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
    }

    // Assign role at category context.
    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    // Show a short summary.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('heading_adduser', 'local_orgadmin'));
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');

    $tbl = new html_table();
    $tbl->attributes['class'] = 'generaltable';
    $tbl->data[] = [get_string($created ? 'summary_created' : 'summary_existing', 'local_orgadmin'), fullname($user) . ' &lt;' . s($user->email) . '&gt;'];
    $tbl->data[] = [get_string('summary_username', 'local_orgadmin'), s($user->username)];
    if ($temppass !== '') {
        $tbl->data[] = [get_string('summary_temp_password', 'local_orgadmin'), s($temppass) . ' (user will be asked to change on first login)'];
    }
    $rolename = $rolenames[$roleid] ?? ('#'.$roleid);
    $tbl->data[] = [get_string('summary_role', 'local_orgadmin'), s($rolename)];
    $catname = $allowedcats[$categoryid] ?? ('#'.$categoryid);
    $tbl->data[] = [get_string('summary_category', 'local_orgadmin'), s($catname)];

    echo html_writer::table($tbl);

    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/adduser.php'), get_string('nav_adduser', 'local_orgadmin'));
    echo $OUTPUT->single_button(new moodle_url('/local/orgadmin/index.php'), get_string('pluginname', 'local_orgadmin'));
    echo $OUTPUT->footer();
    exit;
}

// Default page render with form.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading_adduser', 'local_orgadmin'));
echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
