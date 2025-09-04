<?php
defined('MOODLE_INTERNAL') || die();

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
