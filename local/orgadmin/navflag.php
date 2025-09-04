<?php
// File: local/orgadmin/navflag.php
// Safe JS gate: never redirects; prints JS only for Org Admins.

require_once(__DIR__ . '/../../config.php');
header('Content-Type: application/javascript');

// Do nothing for not-logged-in users, guests, or site admins.
if (!isloggedin() || isguestuser() || is_siteadmin()) {
    exit;
}

// Show if the user has the capability at system OR any category.
$has = has_capability('local/orgadmin:adduser', context_system::instance());
if (!$has) {
    foreach (core_course_category::get_all() as $cat) {
        if (has_capability('local/orgadmin:adduser', context_coursecat::instance($cat->id))) {
            $has = true; break;
        }
    }
}

if ($has) {
    echo 'document.documentElement.classList.add("orgadmin");';
}
