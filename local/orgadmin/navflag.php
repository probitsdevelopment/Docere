<?php
// File: local/orgadmin/navflag.php
require_once(__DIR__ . '/../../config.php');
require_login();

// Never show the navbar item to site admins.
if (is_siteadmin()) {
    header('Content-Type: application/javascript');
    exit; // No class added => stays hidden for admins.
}

// Show only if the user has orgadmin cap in ANY category.
$has = false;
foreach (core_course_category::get_all() as $cat) {
    if (has_capability('local/orgadmin:adduser', context_coursecat::instance($cat->id))) {
        $has = true; break;
    }
}

header('Content-Type: application/javascript');
if ($has) {
    // Add class that reveals the nav item.
    echo 'document.documentElement.classList.add("orgadmin");';
}
