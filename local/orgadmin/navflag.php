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

(function () {
  function activateOrgAdminLink() {
    var path = (location.pathname || '').replace(/\/+$/, '');
    if (!path.startsWith('/local/orgadmin')) { return; }

    // 1) Remove active state from the primary nav (Home/Dashboard/My courses).
    document.querySelectorAll('.primary-navigation a.nav-link.active, .primary-navigation .nav-item.active a.nav-link')
      .forEach(function (el) {
        el.classList.remove('active');
        el.removeAttribute('aria-current');
      });

    // 2) Find our Org Admin link and mark it active.
    var orgLink =
      document.querySelector('a.nav-link[href="/local/orgadmin/index.php"]') ||
      document.querySelector('a.nav-link[href^="/local/orgadmin/"]') ||
      document.querySelector('.orgadmin-only a.nav-link');

    if (orgLink) {
      orgLink.classList.add('active');
      orgLink.setAttribute('aria-current', 'page');
      var li = orgLink.closest('.nav-item');
      if (li) { li.classList.add('active'); }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activateOrgAdminLink);
  } else {
    activateOrgAdminLink();
  }
})();
JS;
}

