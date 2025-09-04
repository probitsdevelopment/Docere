
<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Grant this to your Org Admin custom role *at category context*.
    'local/orgadmin:adduser' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],
    ],
];
