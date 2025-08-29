<?php
namespace local_orgbranding;

defined('MOODLE_INTERNAL') || die();

use \moodle_page;
use \context_coursecat;
use const \CONTEXT_COURSECAT;
use const \CONTEXT_COURSE;

class helper {

    /** Get current category id from page context (0 if none). */
   public static function infer_user_primary_categoryid(int $userid): int {
    global $DB, $CFG;

    // 1) Category with most visible enrolments.
    $sql = "SELECT c.category AS catid, COUNT(*) AS cnt
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {course} c ON c.id = e.courseid
             WHERE ue.userid = :uid AND c.visible = 1
          GROUP BY c.category
          ORDER BY cnt DESC";
    if ($rec = $DB->get_record_sql($sql, ['uid' => $userid])) {
        return (int)$rec->catid;
    }

    // 2) Any category role.
    $sql2 = "SELECT ctx.instanceid AS catid
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :uid AND ctx.contextlevel = :lvl
           ORDER BY ra.id ASC";
    if ($rec2 = $DB->get_record_sql($sql2, ['uid' => $userid, 'lvl' => CONTEXT_COURSECAT])) {
        return (int)$rec2->catid;
    }

    // 3) Optional custom profile field 'orgcategoryid'.
    @require_once($CFG->dirroot . '/user/profile/lib.php');
    if (function_exists('profile_user_record')) {
        $pref = profile_user_record($userid);
        if (!empty($pref) && !empty($pref->orgcategoryid)) {
            return (int)$pref->orgcategoryid;
        }
    }

    return 0;
}

}
