<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Capability used to expose Org Admin tools and allow adding/assigning users.
 * Scoped to CONTEXT_COURSECAT so org admins are limited to their Org category.
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
