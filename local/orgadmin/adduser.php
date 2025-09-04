
<?php
require('../../config.php');

require_login();

$contextid = required_param('contextid', PARAM_INT);
$ctx = context::instance_by_id($contextid, MUST_EXIST);

// Page setup.
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $contextid]));
$PAGE->set_title(get_string('adduser','local_orgadmin'));
$PAGE->set_heading(get_string('adduser','local_orgadmin'));

use local_orgadmin\local\org_helper;
use local_orgadmin\form\adduser_form;

require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/enrol/manual/lib.php');

global $USER, $DB;

// Enforce: category context + capability + not site admin.
$catctx = org_helper::require_category_context_for_user($ctx);

// Build form choices (strictly within this category):
$coursechoices = org_helper::get_org_courses($catctx);
$rolechoices   = org_helper::get_assignable_roles_for_category($catctx);
$catname       = format_string(\core_course_category::get($catctx->instanceid)->get_formatted_name());

// Form.
$mform = new adduser_form(null, [$rolechoices, $coursechoices, $catname]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));
}

if ($data = $mform->get_data()) {
    // Pre-validate username/email uniqueness (friendlier errors).
    $username = core_text::strtolower(trim($data->username));
    if ($DB->record_exists('user', ['username' => $username])) {
        print_error('err_usernameexists', 'local_orgadmin', '', '', 
            html_writer::link(new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $catctx->id]), get_string('goback','local_orgadmin')));
    }
    if ($DB->record_exists('user', ['email' => trim($data->email)])) {
        print_error('err_emailexists', 'local_orgadmin', '', '', 
            html_writer::link(new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $catctx->id]), get_string('goback','local_orgadmin')));
    }

    // 1) Create user.
    $new = new stdClass();
    $new->username  = $username;
    $new->email     = trim($data->email);
    $new->firstname = trim($data->firstname);
    $new->lastname  = trim($data->lastname);
    $new->auth      = 'manual';
    $new->confirmed = 1;
    $new->password  = $data->password; // hashed by user_create_user()
    $userid = user_create_user($new, false);

    // 2) Add to org cohort (creates it if missing).
    $cohortid = org_helper::ensure_org_cohort($catctx);
    cohort_add_member($cohortid, $userid);

    // 3) Optional manual enrolments — strictly limited to this category + allowed role.
    if (!empty($data->addenrol) && !empty($data->courseids) && !empty($data->roleid)) {
        // Filter posted courses to only those in this category.
        $courseids = org_helper::filter_courseids_to_category($data->courseids, $catctx);
        if (empty($courseids)) {
            print_error('err_invalidcourse', 'local_orgadmin');
        }

        // Check user can assign this role in each course.
        $roleid = (int)$data->roleid;
        foreach ($courseids as $courseid) {
            if (!org_helper::can_assign_role_in_course($roleid, $courseid)) {
                print_error('err_roleassign', 'local_orgadmin');
            }
        }

        // Enrol via manual plugin, ensure instance exists.
        $manual = enrol_get_plugin('manual');
        foreach ($courseids as $courseid) {
            $instances = enrol_get_instances($courseid, true);
            $manualinstance = null;
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual') {
                    $manualinstance = $instance; break;
                }
            }
            if (!$manualinstance) {
                $course = get_course($courseid);
                $id = $manual->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
                $manualinstance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
            }
            $manual->enrol_user($manualinstance, $userid, $roleid, time());
        }
    }

    redirect(
        new moodle_url('/local/orgadmin/adduser.php', ['contextid' => $catctx->id]),
        get_string('usercreated','local_orgadmin'),
        2
    );
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
