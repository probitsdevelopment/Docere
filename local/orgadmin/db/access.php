<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Capability to expose Org Admin tools and allow adding/assigning users.
 * We use CONTEXT_COURSECAT so org admins can be scoped to their category (“Org”).
 */
$capabilities = [
    'local/orgadmin:adduser' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [
            // Example: enable by default for manager role (optional)
            // 'manager' => CAP_ALLOW,
        ],
    ],
];
