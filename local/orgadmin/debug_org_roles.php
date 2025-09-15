<?php
// local/orgadmin/debug_org_roles.php - Debug organization roles

require_once('../../config.php');
require_once('./role_detector.php');

// Require login
require_login();

global $USER, $DB;

echo "<h2>Role Detection Debug for User: " . fullname($USER) . " (ID: $USER->id)</h2>";

echo "<h3>1. Basic Checks:</h3>";
echo "Is logged in: " . (isloggedin() ? 'YES' : 'NO') . "<br>";
echo "Is guest user: " . (isguestuser() ? 'YES' : 'NO') . "<br>";
echo "Is site admin: " . (is_siteadmin() ? 'YES' : 'NO') . "<br>";

echo "<h3>2. System Context Capabilities:</h3>";
$systemcontext = context_system::instance();
echo "Has moodle/site:config: " . (has_capability('moodle/site:config', $systemcontext) ? 'YES' : 'NO') . "<br>";

echo "<h3>3. Role Detection Results:</h3>";
echo "Should show admin dashboard: " . (orgadmin_role_detector::should_show_admin_dashboard() ? 'YES' : 'NO') . "<br>";
echo "Should show org admin dashboard: " . (orgadmin_role_detector::should_show_org_admin_dashboard() ? 'YES' : 'NO') . "<br>";
echo "Should show L&D dashboard: " . (orgadmin_role_detector::should_show_lnd_dashboard() ? 'YES' : 'NO') . "<br>";
echo "Should show student dashboard: " . (orgadmin_role_detector::should_show_student_dashboard() ? 'YES' : 'NO') . "<br>";

echo "<h3>4. User's Role Assignments:</h3>";
$role_assignments = $DB->get_records_sql("
    SELECT ra.id, r.shortname, r.name, ctx.contextlevel, ctx.instanceid, cc.name as category_name
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    LEFT JOIN {course_categories} cc ON cc.id = ctx.instanceid AND ctx.contextlevel = 40
    WHERE ra.userid = ?
    ORDER BY ctx.contextlevel, r.sortorder
", [$USER->id]);

if (empty($role_assignments)) {
    echo "No role assignments found for this user.<br>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Role</th><th>Context Level</th><th>Instance ID</th><th>Category Name</th></tr>";
    foreach ($role_assignments as $assignment) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($assignment->shortname) . " (" . htmlspecialchars($assignment->name) . ")</td>";
        echo "<td>" . $assignment->contextlevel . "</td>";
        echo "<td>" . $assignment->instanceid . "</td>";
        echo "<td>" . ($assignment->category_name ? htmlspecialchars($assignment->category_name) : 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>5. Manager Role at Category Level Check:</h3>";
$categories = $DB->get_records_sql("
    SELECT DISTINCT cc.id, cc.name
    FROM {role_assignments} ra
    JOIN {context} ctx ON ctx.id = ra.contextid
    JOIN {role} r ON r.id = ra.roleid
    JOIN {course_categories} cc ON cc.id = ctx.instanceid
    WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'manager'
", [$USER->id]);

if (empty($categories)) {
    echo "No manager role assignments at category level (contextlevel = 40) found.<br>";
} else {
    echo "Found manager role assignments at category level:<br>";
    foreach ($categories as $category) {
        echo "- Category: " . htmlspecialchars($category->name) . " (ID: $category->id)<br>";
    }
}

echo "<h3>6. Dashboard URL:</h3>";
$dashboard_url = orgadmin_role_detector::get_dashboard_url();
echo "Recommended dashboard: " . $dashboard_url->out() . "<br>";

echo "<h3>7. Context Levels Reference:</h3>";
echo "CONTEXT_SYSTEM = 10<br>";
echo "CONTEXT_USER = 30<br>";
echo "CONTEXT_COURSECAT = 40<br>";
echo "CONTEXT_COURSE = 50<br>";
echo "CONTEXT_MODULE = 70<br>";
?>