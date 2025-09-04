<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add to user menu dropdown - this usually works
 */
function local_orgusers_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $USER, $PAGE;
    
    // Only for org admins
    if (!is_siteadmin($USER)) {
        
        // Find user menu
        if ($usernode = $settingsnav->find('usercurrentsettings', navigation_node::TYPE_CONTAINER)) {
            
            // Check if user has org admin capabilities
            $categories = get_user_organization_categories($USER->id);
            
            if (!empty($categories)) {
                foreach ($categories as $categoryid => $categoryname) {
                    $url = new moodle_url('/local/orgusers/adduser.php', array('categoryid' => $categoryid));
                    $usernode->add(
                        'Add User to ' . $categoryname,
                        $url,
                        navigation_node::TYPE_SETTING,
                        null,
                        'adduser_' . $categoryid
                    );
                }
            }
        }
    }
}

/**
 * Get categories where user is ORG ADMIN
 */
function get_user_organization_categories($userid) {
    global $DB;
    
    $categories = array();
    
    if (is_siteadmin($userid)) {
        return array();
    }
    
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

function is_org_admin_for_category($userid, $categoryid) {
    global $DB;
    
    if (is_siteadmin($userid)) {
        return false;
    }
    
    $context = context_coursecat::instance($categoryid);
    
    if (!has_capability('local/orgusers:adduser', $context, $userid)) {
        return false;
    }
    
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