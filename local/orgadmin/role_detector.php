cl<?php
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
        
        // Check for specific L&D capability: moodle/grade:manage
        $systemcontext = context_system::instance();
        
        // First check system level
        if (has_capability('moodle/grade:manage', $systemcontext)) {
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
     * Get dashboard URL based on user role
     * @return moodle_url
     */
    public static function get_dashboard_url() {
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
}