
<?php
require('../../config.php');
require_login();

global $USER;

$sysctx = context_system::instance();
$PAGE->set_context($sysctx);
$PAGE->set_url(new moodle_url('/local/orgadmin/index.php'));
$PAGE->set_title(get_string('dashboardtitle','local_orgadmin'));
$PAGE->set_heading(get_string('dashboardtitle','local_orgadmin'));

// Block site admins entirely (as requested).
if (is_siteadmin($USER)) {
    throw new moodle_exception('nopermission','local_orgadmin');
}

use local_orgadmin\local\org_helper;

$orgcatctxs = org_helper::user_org_categories();

echo $OUTPUT->header();

if (empty($orgcatctxs)) {
    // User is not an Org Admin anywhere.
    echo $OUTPUT->notification(get_string('nopermission','local_orgadmin'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

if (count($orgcatctxs) === 1) {
    // Single org → show big Add User button directly.
    $catctx = $orgcatctxs[0];
    $catname = format_string(\core_course_category::get($catctx->instanceid)->get_formatted_name());
    echo html_writer::tag('h4', get_string('adduserfor','local_orgadmin', $catname));
    $url = new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $catctx->id]);
    echo html_writer::div(
        html_writer::link($url, '➕ '.get_string('adduser','local_orgadmin'), ['class' => 'btn btn-primary btn-lg'])
    );
} else {
    // Multiple orgs → show a list of cards/buttons.
    echo html_writer::tag('h4', get_string('chooseorg','local_orgadmin'));
    foreach ($orgcatctxs as $catctx) {
        $cat = \core_course_category::get($catctx->instanceid);
        $catname = format_string($cat->get_formatted_name());
        $url = new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $catctx->id]);

        echo html_writer::div(
            html_writer::div($catname, 'fw-bold') .
            html_writer::div(
                html_writer::link($url, '➕ '.get_string('adduser','local_orgadmin'), ['class' => 'btn btn-primary mt-2'])
            ),
            'p-3 mb-3 border rounded'
        );
    }
}

// Optional: link to normal dashboard (useful for them).
echo html_writer::div(
    html_writer::link(new moodle_url('/my/'), '📌 Go to My Dashboard', ['class' => 'btn btn-secondary mt-4'])
);

echo $OUTPUT->footer();
