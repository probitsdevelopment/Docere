<?php
defined('MOODLE_INTERNAL') || die();

// Include our role detector
require_once(__DIR__ . '/role_detector.php');

/**
 * Check if student should be redirected to custom dashboard
 */
function local_orgadmin_check_student_redirect() {
    global $PAGE, $SESSION;
    
    // Only redirect on dashboard pages
    $pagetype = $PAGE->pagetype ?? '';
    $requesturi = $_SERVER['REQUEST_URI'] ?? '';
    
    // Check if this is a dashboard page request and user should see student dashboard
    if (($pagetype === 'my-index' || strpos($requesturi, '/my/index.php') !== false) && 
        empty($SESSION->orgadmin_redirected)) {
        
        if (orgadmin_role_detector::should_show_student_dashboard()) {
            $SESSION->orgadmin_redirected = true;
            redirect(new moodle_url('/local/orgadmin/student_dashboard.php'));
        }
    }
}

/**
 * Add our assets + (optionally) a drawer node for Org Admins.
 * No HEAD snippet needed — assets are enqueued via $PAGE->requires.
 */

function local_orgadmin_extend_primary_navigation(\global_navigation $nav) {
    local_orgadmin_require_flag_assets();
    local_orgadmin_maybe_add_node($nav);
}

function local_orgadmin_extend_navigation(\global_navigation $nav) {
    local_orgadmin_require_flag_assets();
    local_orgadmin_maybe_add_node($nav);
}

/**
 * Enqueue our JS + CSS once per request.
 * JS runs in <head> so the 'orgadmin' class is available for CSS.
 */
function local_orgadmin_require_flag_assets(): void {
    static $done = false;
    if ($done) { return; }
    global $PAGE;

    // Redirect users to their appropriate dashboard when they visit the home page
    if ($PAGE->pagetype === 'site-index') {
        // Check for students first (preserve existing functionality)
        if (orgadmin_role_detector::should_show_student_dashboard()) {
            $redirecturl = new \moodle_url('/local/orgadmin/student_dashboard.php');
            redirect($redirecturl);
        }
        
        // Then check for L&D users (only if not a student)
        if (orgadmin_role_detector::should_show_lnd_dashboard()) {
            $redirecturl = new \moodle_url('/local/orgadmin/lnd_dashboard.php');
            redirect($redirecturl);
        }
    }

    // Tiny JS that adds 'orgadmin' class only for Org Admins (no redirects).
    $PAGE->requires->js(new \moodle_url('/local/orgadmin/navflag.php'), /*inhead*/ true);

    // CSS that hides/shows the navbar item.
    $PAGE->requires->css(new \moodle_url('/local/orgadmin/navflag.css'));

    $done = true;
}

/**
 * Optionally add a node to the left drawer for Org Admins.
 * (Safe: will not run for users without the capability.)
 */
function local_orgadmin_maybe_add_node($nav): void {
    // Capability must exist (plugin installed).
    if (!function_exists('get_capability_info') || !get_capability_info('local_orgadmin:adduser')) {
        return;
    }

    // Show if user has the cap at system OR any course category.
    $has = has_capability('local_orgadmin:adduser', \context_system::instance());
    if (!$has) {
        foreach (\core_course_category::get_all() as $cat) {
            if (has_capability('local_orgadmin:adduser', \context_coursecat::instance($cat->id))) {
                $has = true;
                break;
            }
        }
    }
    if (!$has) { return; }

    // Add once.
    $key = 'local_orgadmin';
    if ($nav->find($key, \navigation_node::TYPE_CUSTOM)) {
        return;
    }

    $nav->add_node(\navigation_node::create(
        get_string('pluginname', 'local_orgadmin'),
        new \moodle_url('/local/orgadmin/index.php'),
        \navigation_node::TYPE_CUSTOM,
        null,
        $key,
        new \pix_icon('i/users', '')
    ));
}
