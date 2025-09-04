<?php
defined('MOODLE_INTERNAL') || die();

/**
 * DEBUG: Test if navigation function is being called at all
 */
function local_orgusers_extend_navigation(global_navigation $navigation) {
    global $USER;
    
    // DEBUG: This should ALWAYS appear for everyone
    $navigation->add(
        'TEST LINK - Function Called', 
        new moodle_url('/'), 
        navigation_node::TYPE_CUSTOM,
        null,
        'test_debug'
    );
    
    // Log to see if function is called
    error_log('Navigation function called for user: ' . $USER->id);
}