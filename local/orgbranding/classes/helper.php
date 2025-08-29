<?php
namespace local_orgbranding;

defined('MOODLE_INTERNAL') || die();

// Import Moodle core types / constants for clarity in a namespace.
use moodle_page;
use context_coursecat;
use const CONTEXT_COURSECAT;
use const CONTEXT_COURSE;

class helper {

    /**
     * Find the current page's category id if available.
     * Returns 0 if page context has no category.
     */
    public static function current_page_categoryid(moodle_page $page): int {
        $ctx = $page->context;

        if ($ctx->contextlevel === CONTEXT_COURSECAT) {
            return (int)$ctx->instanceid;
        }

        if ($ctx->contextlevel === CONTEXT_COURSE && !empty($page->course->category)) {
            return (int)$page->course->category;
        }

        return 0;
    }

    /**
     * Infer the user's "primary" category (org) when the page has no category context.
     * Priority:
     *   1) Category with most visible enrolments
     *   2) Any category where the user has a role at category context
     *   3) Custom profile field 'orgcategoryid'
     * Returns 0 if nothing found.
     */
    public static function infer_user_primary_categoryid(int $userid): int {
        global $DB, $CFG;

        // 1) Most enrolments by category.
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

        // 2) Any role at category context.
        $sql2 = "SELECT ctx.instanceid AS catid
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE ra.userid = :uid AND ctx.contextlevel = :lvl
               ORDER BY ra.id ASC";
        if ($rec2 = $DB->get_record_sql($sql2, ['uid' => $userid, 'lvl' => CONTEXT_COURSECAT])) {
            return (int)$rec2->catid;
        }

        // 3) Custom profile field (optional).
        @require_once($CFG->dirroot . '/user/profile/lib.php');
        if (function_exists('profile_user_record')) {
            $pref = profile_user_record($userid);
            if (!empty($pref->orgcategoryid)) {
                return (int)$pref->orgcategoryid;
            }
        }

        return 0;
    }

    /**
     * Return the pluginfile URL to the category logo or null if none.
     */
    public static function category_logo_url_from_catid(int $catid): ?\moodle_url {
        if ($catid <= 0) {
            return null;
        }

        $categoryctx = context_coursecat::instance($catid, IGNORE_MISSING);
        if (!$categoryctx) {
            return null;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $categoryctx->id,
            'local_orgbranding',
            'orglogo',
            $catid,
            'itemid, filename',
            false
        );
        if (!$files) {
            return null;
        }

        $f = reset($files);
        return \moodle_url::make_pluginfile_url(
            $categoryctx->id,
            'local_orgbranding',
            'orglogo',
            $catid,
            $f->get_filepath(),
            $f->get_filename()
        );
    }
}
