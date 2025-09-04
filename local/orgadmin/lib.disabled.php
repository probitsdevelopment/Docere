<?php
// File: local/orgadmin/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Add our node only for Org Admins (NOT for site admins).
 * Robust against errors: wrapped in try/catch and uses FQCNs.
 */

function local_orgadmin_extend_primary_navigation($nav): void {
    local_orgadmin_maybe_add_node($nav);
}

function local_orgadmin_extend_navigation($nav): void {
    local_orgadmin_maybe_add_node($nav);
}

function local_orgadmin_maybe_add_node($nav): void {
    // Don’t show to site admins.
    if (function_exists('is_siteadmin') && is_siteadmin()) {
        return;
    }

    // Capability must exist.
    if (!function_exists('get_capability_info') || !get_capability_info('local/orgadmin:adduser')) {
        return;
    }

    try {
        // Show if user has the cap at system or any category.
        $has = has_capability('local/orgadmin:adduser', \context_system::instance());
        if (!$has) {
            foreach (\core_course_category::get_all() as $cat) {
                $ctx = \context_coursecat::instance($cat->id);
                if (has_capability('local/orgadmin:adduser', $ctx)) {
                    $has = true;
                    break;
                }
            }
        }
        if ($has) {
            local_orgadmin_add_nav_node($nav);
        }
    } catch (\Throwable $e) {
        // Never break the page for non-admins. Log in dev mode only.
        if (function_exists('debugging')) {
            debugging('local_orgadmin nav error: '.$e->getMessage(), DEBUG_DEVELOPER);
        }
        return;
    }
}

function local_orgadmin_add_nav_node($nav): void {
    $key  = 'local_orgadmin_nav';
    if ($nav->find($key, \navigation_node::TYPE_CUSTOM)) {
        return; // already exists
    }

    $name = get_string('pluginname', 'local_orgadmin');
    $url  = new \moodle_url('/local/orgadmin/index.php');

    $node = \navigation_node::create(
        $name,
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        $key,
        new \pix_icon('i/users', $name)
    );
    $nav->add_node($node);
}
