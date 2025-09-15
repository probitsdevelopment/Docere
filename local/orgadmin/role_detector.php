<?php
// local/orgadmin/role_detector.php - Role-based dashboard detection

defined('MOODLE_INTERNAL') || die();

class orgadmin_role_detector {
    
    /**
     * Check if current user should see student dashboard
     * @return bool
     */
    public static function should_show_student_dashboard() {
        global $USER;
        
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        
        // If user should see admin dashboard, they shouldn't see student dashboard
        if (self::should_show_admin_dashboard()) {
            return false;
        }
        
        // Check specific capabilities that indicate higher-level roles
        $systemcontext = context_system::instance();
        
        // Check for admin/manager capabilities
        if (is_siteadmin() || has_capability('moodle/site:config', $systemcontext)) {
            return false;
        }
        
        // Check for course creation capabilities (course creators, L&D managers, org admins)
        if (has_capability('moodle/course:create', $systemcontext)) {
            return false;
        }
        
        // Check for user management capabilities (org admins)
        if (has_capability('moodle/user:create', $systemcontext) || 
            has_capability('moodle/role:assign', $systemcontext)) {
            return false;
        }
        
        // Check for grading capabilities in any course (teachers)
        $courses = enrol_get_my_courses();
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            
            // If can grade assignments or edit grades, they're a teacher
            if (has_capability('moodle/grade:edit', $coursecontext) || 
                has_capability('mod/assign:grade', $coursecontext)) {
                return false;
            }
            
            // If can manage course activities, they're a teacher
            if (has_capability('moodle/course:manageactivities', $coursecontext)) {
                return false;
            }
        }
        
        // Check if user can submit assignments (student-like behavior)
        $canSubmit = false;
        
        // First check course-level enrollment
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            if (has_capability('mod/assign:submit', $coursecontext)) {
                $canSubmit = true;
                break;
            }
        }
        
        // If not found in courses, check category-level roles (organization students)
        if (!$canSubmit) {
            foreach (core_course_category::get_all() as $category) {
                $categorycontext = context_coursecat::instance($category->id);
                
                // Check if user has student role in this category
                if (has_capability('mod/assign:submit', $categorycontext)) {
                    $canSubmit = true;
                    break;
                }
                
                // Alternative check - if user has basic course view capability at category level
                // but no higher capabilities, they might be an organization student
                if (has_capability('moodle/course:view', $categorycontext)) {
                    // Double-check they don't have higher capabilities at this category level
                    if (!has_capability('moodle/grade:edit', $categorycontext) && 
                        !has_capability('moodle/course:create', $categorycontext) &&
                        !has_capability('moodle/course:manageactivities', $categorycontext)) {
                        $canSubmit = true;
                        break;
                    }
                }
            }
        }
        
        // Show student dashboard if user can submit assignments and doesn't have higher capabilities
        return $canSubmit;
    }
    
    /**
     * Check if current user should see L&D dashboard
     * @return bool
     */
    public static function should_show_lnd_dashboard() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        
        // If user should see admin dashboard, they shouldn't see L&D dashboard
        if (self::should_show_admin_dashboard()) {
            return false;
        }
        
        // If user is organization admin, they shouldn't see L&D dashboard
        // Check for manager role at category level directly to avoid circular dependency
        global $DB, $USER;
        $has_org_admin = $DB->record_exists_sql("
            SELECT 1
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND (r.shortname = 'manager' OR r.shortname = 'orgadmin')
        ", [$USER->id]);
        
        if ($has_org_admin) {
            return false;
        }
        
        // Check for specific L&D capability: moodle/grade:manage
        $systemcontext = context_system::instance();
        
        // First check system level - but exclude site admins and managers with full config access
        if (has_capability('moodle/grade:manage', $systemcontext) && 
            !is_siteadmin() && 
            !has_capability('moodle/site:config', $systemcontext)) {
            return true;
        }
        
        // Check category level (organization L&D managers)
        foreach (core_course_category::get_all() as $category) {
            $categorycontext = context_coursecat::instance($category->id);
            if (has_capability('moodle/grade:manage', $categorycontext)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if current user should see admin dashboard (full site admin only)
     * @return bool
     */
    public static function should_show_admin_dashboard() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        
        // Check for site admin or full system capabilities
        $systemcontext = context_system::instance();
        
        // Site admin users
        if (is_siteadmin()) {
            return true;
        }
        
        // Users with system configuration capability (managers with full site access)
        if (has_capability('moodle/site:config', $systemcontext)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current user should see organization admin dashboard
     * @return bool
     */
    public static function should_show_org_admin_dashboard() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        
        // If user should see full site admin dashboard, they shouldn't see org admin dashboard
        if (self::should_show_admin_dashboard()) {
            return false;
        }
        
        // Check for manager or orgadmin role at category level (organization admin)
        global $DB, $USER;
        $categories = $DB->get_records_sql("
            SELECT DISTINCT cc.id, cc.name
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            JOIN {course_categories} cc ON cc.id = ctx.instanceid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND (r.shortname = 'manager' OR r.shortname = 'orgadmin')
        ", [$USER->id]);
        
        return !empty($categories);
    }

    /**
     * Check if current user should see teacher/trainer dashboard
     * @return bool
     */
    public static function should_show_teacher_dashboard() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // If user should see higher priority dashboards, they shouldn't see teacher dashboard
        if (self::should_show_admin_dashboard() || self::should_show_org_admin_dashboard()) {
            return false;
        }

        global $DB, $USER;

        // Only check for editingteacher role (Trainer)
        // Regular 'teacher' role is for Stakeholders, not Trainers
        $has_trainer_role = $DB->record_exists_sql("
            SELECT 1
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ? AND r.shortname = 'editingteacher'
        ", [$USER->id]);

        return $has_trainer_role;
    }

    /**
     * Check if current user should see stakeholder dashboard
     * @return bool
     */
    public static function should_show_stakeholder_dashboard() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // If user should see higher priority dashboards, they shouldn't see stakeholder dashboard
        if (self::should_show_admin_dashboard() || self::should_show_org_admin_dashboard() ||
            self::should_show_teacher_dashboard()) {
            return false;
        }

        global $DB, $USER;

        // Check for teacher role only (Stakeholder - non-editing teacher)
        $has_stakeholder_role = $DB->record_exists_sql("
            SELECT 1
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ? AND r.shortname = 'teacher'
        ", [$USER->id]);

        return $has_stakeholder_role;
    }

    /**
     * Get dashboard URL based on user role
     * @return moodle_url
     */
    public static function get_dashboard_url() {
        if (self::should_show_admin_dashboard()) {
            return new moodle_url('/local/orgadmin/admin_dashboard.php');
        }
        if (self::should_show_org_admin_dashboard()) {
            return new moodle_url('/local/orgadmin/org_admin_dashboard.php');
        }
        if (self::should_show_teacher_dashboard()) {
            return new moodle_url('/local/orgadmin/teacher_dashboard.php');
        }
        if (self::should_show_stakeholder_dashboard()) {
            return new moodle_url('/local/orgadmin/stakeholder_dashboard.php');
        }
        if (self::should_show_lnd_dashboard()) {
            return new moodle_url('/local/orgadmin/lnd_dashboard.php');
        }

        if (self::should_show_student_dashboard()) {
            return new moodle_url('/local/orgadmin/student_dashboard.php');
        }

        // Default Moodle dashboard
        return new moodle_url('/my/index.php');
    }
    
    /**
     * Get user's course capabilities across all enrolled courses
     * @return array
     */
    public static function get_user_course_capabilities() {
        global $USER, $DB;
        
        $capabilities = [
            'manager' => false,
            'coursecreator' => false, 
            'editingteacher' => false,
            'teacher' => false,
            'student' => false,
            'user' => false
        ];
        
        // Get system context capabilities
        $systemcontext = context_system::instance();
        
        if (has_capability('moodle/site:config', $systemcontext)) {
            $capabilities['manager'] = true;
            return $capabilities;
        }
        
        if (has_capability('moodle/course:create', $systemcontext)) {
            $capabilities['coursecreator'] = true;
            return $capabilities;
        }
        
        // Check course-level capabilities
        $courses = enrol_get_my_courses();
        
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            
            if (has_capability('moodle/course:update', $coursecontext)) {
                $capabilities['editingteacher'] = true;
                break;
            }
            
            if (has_capability('moodle/grade:edit', $coursecontext)) {
                $capabilities['teacher'] = true;
                break;
            }
            
            if (has_capability('mod/assign:submit', $coursecontext)) {
                $capabilities['student'] = true;
            }
        }
        
        // If no specific course capabilities, check if logged in user
        if (!array_filter($capabilities)) {
            $capabilities['user'] = isloggedin() && !isguestuser();
        }
        
        return $capabilities;
    }

    /**
     * Get real user statistics for admin dashboard
     * @return array
     */
    public static function get_admin_statistics() {
        global $DB;

        // Total confirmed users (excluding guest and admin)
        $totalUsers = $DB->count_records_sql("
            SELECT COUNT(*) 
            FROM {user} 
            WHERE deleted = 0 AND suspended = 0 AND confirmed = 1 AND id != 1 AND username != 'guest'
        ");

        // Students - users with student role
        $students = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'student'
        ");

        // Trainers - users with teacher OR editingteacher role (both display as "Trainer")
        $trainers = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
        ");

        // Organizations - users with Organization role as determined by role detection
        $all_users = $DB->get_records_sql("
            SELECT u.id
            FROM {user} u
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id != 1 AND u.username != 'guest'
        ");
        
        $organizations = 0;
        foreach ($all_users as $user) {
            $user_roles = self::get_user_roles($user->id);
            $primary_role = self::determine_primary_role($user_roles);
            if ($primary_role === 'Organization') {
                $organizations++;
            }
        }

        // L&D - course creators
        $lnd = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'coursecreator'
        ");

        return [
            'total_users' => $totalUsers,
            'students' => $students,
            'trainers' => $trainers,
            'organizations' => $organizations,
            'lnd' => $lnd
        ];
    }

    /**
     * Get users for admin dashboard directory with real Moodle data
     * @param int $page
     * @param int $perpage
     * @param string $search
     * @param string $role_filter
     * @return array
     */
    public static function get_admin_users($page = 0, $perpage = 10, $search = '', $role_filter = 'all') {
        global $DB, $CFG;

        // Base conditions
        $where_conditions = "u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id != 1 AND u.username != 'guest'";
        $params = [];
        $role_join = "";

        // Add search condition
        if (!empty($search)) {
            $where_conditions .= " AND (" . $DB->sql_like('u.firstname', ':search1') . 
                                " OR " . $DB->sql_like('u.lastname', ':search2') . 
                                " OR " . $DB->sql_like('u.email', ':search3') . ")";
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
        }

        // Add role filter - only for specific roles, not organization
        if ($role_filter !== 'all' && $role_filter !== 'organization') {
            $role_join = "JOIN {role_assignments} ra ON ra.userid = u.id
                         JOIN {role} r ON r.id = ra.roleid";
            
            if ($role_filter === 'teacher') {
                // Teacher filter should include both teacher and editingteacher roles (both display as "Trainer")
                $where_conditions .= " AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher')";
            } else {
                $where_conditions .= " AND r.shortname = :role";
                $params['role'] = $role_filter;
            }
        }

        // Get total count
        $total_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            $role_join
            WHERE $where_conditions
        ", $params);

        // Get users with pagination
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated
                FROM {user} u
                $role_join
                WHERE $where_conditions
                ORDER BY u.timecreated DESC";

        $users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        // Process users and get their primary roles
        $processed_users = [];
        foreach ($users as $user) {
            $user_roles = self::get_user_roles($user->id);
            $primary_role = self::determine_primary_role($user_roles);
            
            // Filter by organization role if needed
            if ($role_filter === 'organization' && $primary_role !== 'Organization') {
                continue;
            }
            
            $processed_users[] = [
                'id' => $user->id,
                'name' => trim($user->firstname . ' ' . $user->lastname),
                'email' => $user->email,
                'role' => $primary_role,
                'registered' => date('F j, Y', $user->timecreated),
                'avatar' => self::get_user_avatar($user->id)
            ];
        }

        // Adjust total count for organization filter
        if ($role_filter === 'organization') {
            $total_count = count($processed_users);
            // Need to get actual total count by processing all users
            $all_users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated
                                               FROM {user} u
                                               WHERE $where_conditions
                                               ORDER BY u.timecreated DESC", $params);
            $org_count = 0;
            foreach ($all_users as $user) {
                $user_roles = self::get_user_roles($user->id);
                $primary_role = self::determine_primary_role($user_roles);
                if ($primary_role === 'Organization') {
                    $org_count++;
                }
            }
            $total_count = $org_count;
        }
        
        return [
            'users' => $processed_users,
            'total_count' => $total_count,
            'current_page' => $page,
            'per_page' => $perpage,
            'total_pages' => ceil($total_count / $perpage)
        ];
    }

    /**
     * Get user roles
     * @param int $userid
     * @return array
     */
    private static function get_user_roles($userid) {
        global $DB;
        
        $roles = $DB->get_records_sql("
            SELECT DISTINCT r.shortname
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ?
        ", [$userid]);

        return array_keys($roles);
    }

    /**
     * Determine primary role for display
     * @param array $roles
     * @return string
     */
    private static function determine_primary_role($roles) {
        // Priority order
        $role_priority = [
            'manager' => 1,
            'coursecreator' => 2,
            'editingteacher' => 3,
            'teacher' => 4,
            'student' => 5
        ];

        $primary_role = 'User';
        $highest_priority = 999;

        foreach ($roles as $role) {
            if (isset($role_priority[$role]) && $role_priority[$role] < $highest_priority) {
                $highest_priority = $role_priority[$role];
                switch ($role) {
                    case 'manager': $primary_role = 'Manager'; break;
                    case 'coursecreator': $primary_role = 'L&D'; break;
                    case 'editingteacher': $primary_role = 'Trainer'; break;
                    case 'teacher': $primary_role = 'Trainer'; break;
                    case 'student': $primary_role = 'Student'; break;
                }
            }
        }

        // Special case: If user has roles at category level but is not a course creator, they're organization users
        if ($primary_role === 'User' && !empty($roles)) {
            // Check if user has category-level roles
            global $DB;
            $userid = 0; // We'll need to pass this differently, for now default to Organization
            $primary_role = 'Organization';
        }

        return $primary_role;
    }

    /**
     * Get user avatar
     * @param int $userid
     * @return string
     */
    private static function get_user_avatar($userid) {
        global $CFG;
        
        // For now, return default avatar - can be enhanced later
        return $CFG->wwwroot . '/pix/u/f2.png';
    }
    
    /**
     * Get organization-specific statistics for org admin dashboard
     * @return array
     */
    public static function get_org_admin_statistics() {
        global $DB, $USER;
        
        // Get the organization/category this user manages
        $categories = $DB->get_records_sql("
            SELECT DISTINCT cc.id, cc.name
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            JOIN {course_categories} cc ON cc.id = ctx.instanceid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND (r.shortname = 'manager' OR r.shortname = 'orgadmin')
        ", [$USER->id]);
        
        if (empty($categories)) {
            return ['total_users' => 0, 'trainers' => 0, 'stakeholders' => 0, 'lnd' => 0];
        }
        
        $category_ids = array_keys($categories);
        list($in_sql, $params) = $DB->get_in_or_equal($category_ids);
        
        // Total users in this organization (any role assignment in the category)
        $total_users = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ", $params);
        
        // Students in this organization
        $students = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'student'
            AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ", $params);
        
        // Trainers (teachers and editingteachers) in this organization
        $trainers = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
            AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ", $params);
        
        // Stakeholders (non-editing teachers) in this organization
        $stakeholders = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'teacher'
            AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ", $params);
        
        // L&D (course creators) in this organization
        $lnd = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'coursecreator'
            AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ", $params);
        
        return [
            'total_users' => $total_users,
            'students' => $students,
            'trainers' => $trainers,
            'stakeholders' => $stakeholders,
            'lnd' => $lnd
        ];
    }
    
    /**
     * Get users for organization admin dashboard
     * @param int $page
     * @param int $perpage  
     * @param string $search
     * @param string $role_filter
     * @return array
     */
    public static function get_org_admin_users($page = 0, $perpage = 10, $search = '', $role_filter = 'all') {
        global $DB, $USER;
        
        // Get the organization/category this user manages
        $categories = $DB->get_records_sql("
            SELECT DISTINCT cc.id, cc.name
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            JOIN {course_categories} cc ON cc.id = ctx.instanceid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND (r.shortname = 'manager' OR r.shortname = 'orgadmin')
        ", [$USER->id]);
        
        if (empty($categories)) {
            return ['users' => [], 'total_count' => 0, 'current_page' => $page, 'per_page' => $perpage, 'total_pages' => 0];
        }
        
        $category_ids = array_keys($categories);
        list($in_sql, $params) = $DB->get_in_or_equal($category_ids);
        
        // Base conditions for users in this organization
        $where_conditions = "u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql";
        $role_join = "JOIN {role_assignments} ra ON ra.userid = u.id
                      JOIN {context} ctx ON ctx.id = ra.contextid";
        
        // Add search condition
        if (!empty($search)) {
            $where_conditions .= " AND (" . $DB->sql_like('u.firstname', ':search1') . 
                                " OR " . $DB->sql_like('u.lastname', ':search2') . 
                                " OR " . $DB->sql_like('u.email', ':search3') . ")";
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
        }
        
        // Don't filter at SQL level - we'll filter after determining roles
        $role_join .= " JOIN {role} r ON r.id = ra.roleid";
        
        // Get total count
        $total_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            $role_join
            WHERE $where_conditions
        ", $params);
        
        // Get users with pagination
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated
                FROM {user} u
                $role_join
                WHERE $where_conditions
                ORDER BY u.timecreated DESC";
                
        $users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        
        // Process users and get their primary roles
        $processed_users = [];
        foreach ($users as $user) {
            $user_roles = self::get_user_roles($user->id);
            $primary_role = self::determine_primary_role($user_roles);
            
            // Map roles for org admin dashboard
            if ($primary_role === 'Trainer') {
                // Trainer role from editingteacher -> keep as Trainer
                $primary_role = 'Trainer';
            } elseif ($primary_role === 'Teacher') {
                // Teacher role (non-editing) -> Stakeholder
                $primary_role = 'Stakeholder';
            }
            
            // Filter by role after determining primary role
            if ($role_filter !== 'all') {
                $show_user = false;
                
                switch ($role_filter) {
                    case 'student':
                        $show_user = ($primary_role === 'Student');
                        break;
                    case 'trainer':
                        $show_user = ($primary_role === 'Trainer');
                        break;
                    case 'stakeholder':
                        $show_user = ($primary_role === 'Stakeholder');
                        break;
                    case 'coursecreator':
                        $show_user = ($primary_role === 'L&D');
                        break;
                    default:
                        $show_user = true;
                }
                
                if (!$show_user) {
                    continue;
                }
            }
            
            $processed_users[] = [
                'id' => $user->id,
                'name' => trim($user->firstname . ' ' . $user->lastname),
                'email' => $user->email,
                'role' => $primary_role,
                'registered' => date('F j, Y', $user->timecreated),
                'avatar' => self::get_user_avatar($user->id)
            ];
        }
        
        // Adjust total count for role filtering
        if ($role_filter !== 'all') {
            // Need to get actual total count by processing all users
            $all_users = $DB->get_records_sql($sql, $params);
            $filtered_count = 0;
            
            foreach ($all_users as $all_user) {
                $all_user_roles = self::get_user_roles($all_user->id);
                $all_primary_role = self::determine_primary_role($all_user_roles);
                
                // Map roles for org admin dashboard
                if ($all_primary_role === 'Trainer') {
                    $all_primary_role = 'Trainer';
                } elseif ($all_primary_role === 'Teacher') {
                    $all_primary_role = 'Stakeholder';
                }
                
                // Apply same filter logic
                $show_user = false;
                switch ($role_filter) {
                    case 'student':
                        $show_user = ($all_primary_role === 'Student');
                        break;
                    case 'trainer':
                        $show_user = ($all_primary_role === 'Trainer');
                        break;
                    case 'stakeholder':
                        $show_user = ($all_primary_role === 'Stakeholder');
                        break;
                    case 'coursecreator':
                        $show_user = ($all_primary_role === 'L&D');
                        break;
                    default:
                        $show_user = true;
                }
                
                if ($show_user) {
                    $filtered_count++;
                }
            }
            $total_count = $filtered_count;
        }
        
        return [
            'users' => $processed_users,
            'total_count' => $total_count,
            'current_page' => $page,
            'per_page' => $perpage,
            'total_pages' => ceil($total_count / $perpage)
        ];
    }
}