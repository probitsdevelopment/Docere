
<?php
require_once(__DIR__ . '/../../config.php');
header('Content-Type: application/javascript');
if (isloggedin() && has_capability('local/orgadmin:adduser', context_system::instance())) {
    echo 'document.body.classList.add("orgadmin");';
}