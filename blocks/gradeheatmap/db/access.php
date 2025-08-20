<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Allow managers/teachers to add the block to course pages.
    'block/gradeheatmap:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ],

    // Allow users to add the block to their Dashboard (My home).
    'block/gradeheatmap:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ],
];
