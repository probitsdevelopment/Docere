<?php
// File: local/orgadmin/navflag.php
require_once(__DIR__ . '/../../config.php');
require_login();

$has = has_capability('local/orgadmin:adduser', context_system::instance());
if (!$has) {
    foreach (core_course_category::get_all() as $cat) {
        if (has_capability('local/orgadmin:adduser', context_coursecat::instance($cat->id))) {
            $has = true;
            break;
        }
    }
}

header('Content-Type: application/javascript');
if ($has) {
    // Add the class early; works even before <body> is parsed.
    echo 'document.documentElement.classList.add("orgadmin");';
}
