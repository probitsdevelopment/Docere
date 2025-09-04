<?php
// File: local/orgadmin/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Add our entry into the navigation for users who have the capability
 * either at system level or in ANY course category they can manage.
 *
 * We implement BOTH callbacks for wider compatibility across Moodle versions.
 */

function local_orgadmin_extend_primary_navigation($nav): void {
    local_orgadmin_maybe_add_node($nav);
}

function local_orgadmin_extend_navigation($nav): void {
    local_orgadmin_maybe_add_node($nav);
}

function local_orgadmin_maybe_add_node($nav): void {
    global $CFG;

    // Capability must exist (plugin installed/upgraded).
    if (!function_exists('get_capability_info') || !get_capability_info('local/orgadmin:adduser')) {
        return;
    }

    // If the user has cap at system, show immediately.
    if (has_capability('local/orgadmin:adduser', context_system::instance())) {
        local_orgadmin_add_nav_node($nav);
        return;
    }

    // Else: show if user has cap in ANY category.
    foreach (core_course_category::get_all() as $cat) {
        $ctx = context_coursecat::instance($cat->id);
        if (has_capability('local/orgadmin:adduser', $ctx)) {
            local_orgadmin_add_nav_node($nav);
            return;
        }
    }
}

function local_orgadmin_add_nav_node($nav): void {
    $key  = 'local_orgadmin_nav';
    if ($nav->find($key, navigation_node::TYPE_CUSTOM)) {
        return; // already exists
    }

    $name = get_string('pluginname', 'local_orgadmin');
    $url  = new moodle_url('/local/orgadmin/index.php');

    $node = navigation_node::create(
        $name,
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        $key,
        new pix_icon('i/users', $name)
    );

    $nav->add_node($node);
}
