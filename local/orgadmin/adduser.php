<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php'); // <-- needed for process_new_icon()

require_login();

$systemctx = context_system::instance();
$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context($systemctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

// Build allowed categories (…unchanged…)
// Build role whitelist (…unchanged…)
// Defaults + form load (…unchanged…)

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));
} else if ($data = $mform->get_data()) {

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

    // NEW profile fields
    $city       = trim($data->city ?? '');
    $country    = (string)($data->country ?? '');
    $timezone   = (string)($data->timezone ?? 99); // 99 = server default
    $lang       = (string)($data->lang ?? current_language());
    $desc       = $data->description['text']  ?? '';
    $descformat = $data->description['format']?? FORMAT_HTML;

    $catctx = context_coursecat::instance($categoryid);

    global $DB, $CFG, $OUTPUT;
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    $created = false; $temppass = '';

    if (!$user) {
        if (!$create) { print_error('err_user_not_found', 'local_orgadmin'); }

        if ($username === '') {
            $username = preg_replace('/[^a-z0-9._-]+/i', '', explode('@', $email, 2)[0]);
        }
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

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

        // NEW fields on creation
        $new->city        = $city;
        $new->country     = $country;
        $new->timezone    = $timezone;      // '99' means server default
        $new->lang        = $lang;
        $new->description = $desc;
        $new->descriptionformat = $descformat;

        $new->forcepasswordchange = $forcechange;

        $userid = user_create_user($new, false, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
        $created = true;
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
        // By design we do not override existing profile fields here.
    }

    // Profile picture (new or existing) — if uploaded replace it.
    $draftid = file_get_submitted_draft_itemid('userpicture');
    if (!empty($draftid)) {
        $userctx = context_user::instance($user->id);
        $itemid = process_new_icon($userctx, 'user', 'icon', 0, $draftid);
        if ($itemid) {
            $user->picture = $itemid;
            user_update_user($user, false, false);
        }
    }

    // Assign role at category.
    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    // Summary (…unchanged…)
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

// First load.
echo $OUTPUT->header();
echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
