
<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_loggedin',
        'callback'    => '\local_orgadmin\observer::redirect_orgadmin',
        'includefile' => '/local/orgadmin/classes/observer.php',
        'internal'    => false,
        'priority'    => 1000,
    ],
];
