<?php
// Make sure this is the VERY FIRST line in the file (no spaces/BOM above!)

namespace local_orgadmin\local;

defined('MOODLE_INTERNAL') || die();

class org_helper {
    /** Ensure the given context is a category the current user can manage (and is not site admin). */
    public static function require_category_context_for_user(\context $ctx): \context_coursecat {
        global $USER;

        if ($ctx->contextlevel !== CONTEXT_COURSECAT) {
            throw new \moodle_exception('noorgcategory', 'local_orgadmin');
        }
        if (is_siteadmin($USER)) {
            // Explicitly block site admins from this plugin.
            throw new \moodle_exception('nopermission', 'local_orgadmin');
        }
        if (!has_capability('local/orgadmin:adduser', $ctx, $USER)) {
            throw new \moodle_exception('nopermission', 'local_orgadmin');
        }
        return $ctx;
    }

    /** All categories (contexts) where current user has our capability. */
    public static function user_org_categories(): array {
        global $USER;
        $out = [];
        foreach (\core_course_category::get_all() as $cat) {
            $catctx = \context_coursecat::instance($cat->id);
            if (!is_siteadmin($USER) && has_capability('local/orgadmin:adduser', $catctx, $USER)) {
                $out[] = $catctx;
            }
        }
        return $out;
    }

    /** Return or create the org cohort bound to this category (idnumber: "org_{categoryid}"). */
    public static function ensure_org_cohort(\context_coursecat $catctx): int {
        global $DB;
        $idnumber = 'org_' . $catctx->instanceid;
        if ($id = $DB->get_field('cohort', 'id', ['contextid' => $catctx->id, 'idnumber' => $idnumber])) {
            return (int)$id;
        }
        require_once($GLOBALS['CFG']->dirroot . '/cohort/lib.php');
        $cohort = (object)[
            'contextid' => $catctx->id,
            'name'      => 'Org '.$catctx->instanceid.' Members',
            'idnumber'  => $idnumber,
            'description' => '',
            'visible'   => 1,
        ];
        return cohort_add_cohort($cohort);
    }

    /** Courses under this category (recursive) keyed by id => fullname. */
    public static function get_org_courses(\context_coursecat $catctx): array {
        $cat = \core_course_category::get($catctx->instanceid, IGNORE_MISSING, true);
        if (!$cat) return [];
        $courses = $cat->get_courses(['recursive' => true]);
        $out = [];
        foreach ($courses as $c) {
            $out[$c->id] = format_string($c->fullname);
        }
        return $out;
    }

    /** Keep only course IDs that belong to the category. */
    public static function filter_courseids_to_category(array $courseids, \context_coursecat $catctx): array {
        $allowed = array_map('intval', array_keys(self::get_org_courses($catctx)));
        return array_values(array_intersect(array_map('intval', $courseids), $allowed));
    }

    /** Roles assignable by current user at this category (for display). Server-side recheck happens per-course. */
    public static function get_assignable_roles_for_category(\context_coursecat $catctx): array {
        $roles = get_assignable_roles($catctx, ROLENAME_ALIAS, false); // returns [roleid => rolename]
        if (empty($roles)) {
            // Fallback if nothing is configured.
            $sys = \context_system::instance();
            $roles = get_assignable_roles($sys, ROLENAME_ALIAS, false);
        }
        return $roles;
    }

    /** Validate that a role can be assigned by current user in a given course. */
    public static function can_assign_role_in_course(int $roleid, int $courseid): bool {
        $coursectx = \context_course::instance($courseid);
        return user_can_assign($coursectx, $roleid);
    }
}
