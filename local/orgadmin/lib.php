<?php
// File: local/orgadmin/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Adds "Org Admin Dashboard" (or whatever your pluginname string is)
 * to the top primary navigation for users who have the capability
 * local/orgadmin:adduser either at the system level or in ANY course category.
 *
 * IMPORTANT:
 * - version.php must have: $plugin->component = 'local_orgadmin';
 * - lang/en/local_orgadmin.php must define: $string['pluginname'] = 'Org Admin Dashboard';
 * - db/access.php must define the capability 'local/orgadmin:adduser' and you must run admin upgrades once.
 */
function local_orgadmin_extend_primary_navigation(global_navigation $nav) {
    global $USER;

    // If the capability is not installed/defined yet, exit silently.
    if (!get_capability_info('local/orgadmin:adduser')) {
        return;
    }

    // 1) System-level check: if user has cap at system, show node.
    if (has_capability('local/orgadmin:adduser', \context_system::instance())) {
        local_orgadmin_add_nav_node($nav);
        return;
    }

    // 2) Category-level check: if user has cap in ANY category, show node.
    $categories = \core_course_category::get_all();
    foreach ($categories as $cat) {
        $ctx = \context_coursecat::instance($cat->id);
        if (has_capability('local/orgadmin:adduser', $ctx)) {
            local_orgadmin_add_nav_node($nav);
            return;
        }
    }
}

/**
 * Helper to insert the node once (prevents duplicates when caches act up).
 *
 * @param global_navigation $nav
 * @return void
 */
function local_orgadmin_add_nav_node(global_navigation $nav): void {
    $url  = new \moodle_url('/local/orgadmin/index.php');
    $name = get_string('pluginname', 'local_orgadmin'); // label from lang file
    $key  = 'local_orgadmin';

    // If already present, don't add again.
    if ($nav->find($key, navigation_node::TYPE_CUSTOM)) {
        return;
    }

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
