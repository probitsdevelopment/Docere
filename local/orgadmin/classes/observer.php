<?php


namespace local_orgadmin;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function redirect_orgadmin(\core\event\user_loggedin $event) {
        global $USER, $SESSION, $CFG;

        // Never affect site admins.
        if (is_siteadmin($USER)) {
            return true;
        }

        // If user has orgadmin capability in ANY category, send them to our dashboard.
        $categories = \core_course_category::get_all();
        foreach ($categories as $cat) {
            $catctx = \context_coursecat::instance($cat->id);
            if (has_capability('local/orgadmin:adduser', $catctx, $USER)) {
                $SESSION->wantsurl = $CFG->wwwroot . '/local/orgadmin/index.php';
                break;
            }
        }
        return true;
    }
}
