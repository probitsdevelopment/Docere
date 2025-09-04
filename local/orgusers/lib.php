<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extend main navigation - appears in TOP navigation bar
 */
function local_orgusers_extend_navigation(global_navigation $navigation) {
    global $USER;
    
    // Check if user is org admin (not site admin)
    if (!is_siteadmin($USER) && has_org_admin_access($USER->id)) {
        
        $categories = get_user_organization_categories($USER->id);
        
        if (!empty($categories)) {
            
            if (count($categories) == 1) {
                // Single organization - direct link
                $categoryid = array_keys($categories)[0];
                $categoryname = array_values($categories)[0];
                
                $url = new moodle_url('/local/orgusers/adduser.php', array('categoryid' => $categoryid));
                $navigation->add(
                    get_string('adduser', 'local_orgusers'),
                    $url,
                    navigation_node::TYPE_CUSTOM,
                    null,
                    'orgusers_single',
                    new pix_icon('i/user', get_string('adduser', 'local_orgusers'))
                );
                
            } else {
                // Multiple organizations - dropdown menu
                $mainnode = $navigation->add(
                    get_string('adduser', 'local_orgusers'),
                    null,
                    navigation_node::TYPE_CUSTOM,
                    null,
                    'orgusers_multi',
                    new pix_icon('i/user', get_string('adduser', 'local_orgusers'))
                );
                
                // Add sub-items for each organization
                foreach ($categories as $categoryid => $categoryname) {
                    $url = new moodle_url('/local/orgusers/adduser.php', array('categoryid' => $categoryid));
                    $mainnode->add(
                        $categoryname,
                        $url,
                        navigation_node::TYPE_CUSTOM,
                        null,
                        'orguser_' . $categoryid
                    );
                }
            }
        }
    }
}

/**
 * Check if user has org admin access (not site admin)
 */
function has_org_admin_access($userid) {
    global $DB;
    
    // Must NOT be site admin
    if (is_siteadmin($userid)) {
        return false;
    }
    
    // Check if user has adduser capability in any category context
    $sql = "SELECT DISTINCT 1
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE ra.userid = :userid 
            AND ctx.contextlevel = :contextlevel
            AND rc.capability = :capability 
            AND rc.permission = :permission";
    
    $params = array(
        'userid' => $userid,
        'contextlevel' => CONTEXT_COURSECAT,
        'capability' => 'local/orgusers:adduser',
        'permission' => CAP_ALLOW
    );
    
    return $DB->record_exists_sql($sql, $params);
}

/**
 * Get categories where user is ORG ADMIN (not site admin)
 */
function get_user_organization_categories($userid) {
    global $DB;
    
    $categories = array();
    
    // Don't show anything for site admins
    if (is_siteadmin($userid)) {
        return array();
    }
    
    // Get categories where user has role assignments at category level
    $sql = "SELECT DISTINCT cc.id, cc.name 
            FROM {course_categories} cc
            JOIN {context} ctx ON ctx.instanceid = cc.id AND ctx.contextlevel = :contextlevel
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE ra.userid = :userid 
            AND rc.capability = :capability 
            AND rc.permission = :permission
            AND cc.visible = 1
            ORDER BY cc.name";
    
    $params = array(
        'userid' => $userid,
        'capability' => 'local/orgusers:adduser',
        'permission' => CAP_ALLOW,
        'contextlevel' => CONTEXT_COURSECAT
    );
    
    $records = $DB->get_records_sql($sql, $params);
    
    foreach ($records as $record) {
        $categories[$record->id] = $record->name;
    }
    
    return $categories;
}

/**
 * Check if user is org admin for specific category
 */
function is_org_admin_for_category($userid, $categoryid) {
    global $DB;
    
    // Site admins should not have access
    if (is_siteadmin($userid)) {
        return false;
    }
    
    $context = context_coursecat::instance($categoryid);
    
    // Must have the adduser capability in this category
    if (!has_capability('local/orgusers:adduser', $context, $userid)) {
        return false;
    }
    
    // Must have role assignment specifically at this category level
    $sql = "SELECT ra.id 
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE ra.userid = :userid 
            AND ctx.instanceid = :categoryid 
            AND ctx.contextlevel = :contextlevel
            AND rc.capability = :capability 
            AND rc.permission = :permission";
    
    $params = array(
        'userid' => $userid,
        'categoryid' => $categoryid,
        'contextlevel' => CONTEXT_COURSECAT,
        'capability' => 'local/orgusers:adduser',
        'permission' => CAP_ALLOW
    );
    
    return $DB->record_exists_sql($sql, $params);
}