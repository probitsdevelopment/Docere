<?php
// Safe JS gate: prints one line of JS ONLY for Org Admins; blank for others.
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
    echo <<<'JS'
document.documentElement.classList.add('orgadmin');

document.addEventListener('DOMContentLoaded', function () {
  // If we are on any /local/orgadmin/* page, highlight the navbar item.
  var path = (location.pathname || '').replace(/\/+$/, '');
  if (path.indexOf('/local/orgadmin') === 0) {
    var link = document.querySelector('li.orgadmin-only > a[href*="/local/orgadmin/"]');
    if (link) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
      var li = link.closest('.nav-item');
      if (li) li.classList.add('active');
    }
  }
});
JS;
}
