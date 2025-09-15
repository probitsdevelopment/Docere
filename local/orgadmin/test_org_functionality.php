<?php
// Test organization admin dashboard functionality
require_once('../../config.php');
require_once('./role_detector.php');

require_login();

global $DB, $USER;

echo "<h2>Organization Admin Dashboard Functionality Test</h2>";
echo "Testing for user: " . fullname($USER) . " (ID: $USER->id)<br><br>";

// Test 1: Role Detection
echo "<h3>✅ Test 1: Role Detection</h3>";
echo "Should show org admin dashboard: " . (orgadmin_role_detector::should_show_org_admin_dashboard() ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . "<br>";

// Test 2: User's Managed Categories
echo "<h3>✅ Test 2: User's Managed Categories</h3>";
$categories = $DB->get_records_sql("
    SELECT DISTINCT cc.id, cc.name
    FROM {role_assignments} ra
    JOIN {context} ctx ON ctx.id = ra.contextid
    JOIN {role} r ON r.id = ra.roleid
    JOIN {course_categories} cc ON cc.id = ctx.instanceid
    WHERE ra.userid = ? AND ctx.contextlevel = 40 AND (r.shortname = 'manager' OR r.shortname = 'orgadmin')
", [$USER->id]);

if (empty($categories)) {
    echo '<span style="color: red;">❌ No managed categories found</span><br>';
} else {
    echo '<span style="color: green;">✅ Managed categories:</span><br>';
    foreach ($categories as $cat) {
        echo "- " . htmlspecialchars($cat->name) . " (ID: $cat->id)<br>";
    }
}

// Test 3: Statistics Function
echo "<h3>✅ Test 3: Statistics Function</h3>";
try {
    $statistics = orgadmin_role_detector::get_org_admin_statistics();
    echo '<span style="color: green;">✅ Statistics function works:</span><br>';
    echo "- Total Users: " . $statistics['total_users'] . "<br>";
    echo "- Trainers: " . $statistics['trainers'] . "<br>";
    echo "- Stakeholders: " . $statistics['stakeholders'] . "<br>";
    echo "- L&D: " . $statistics['lnd'] . "<br>";
} catch (Exception $e) {
    echo '<span style="color: red;">❌ Statistics function error: ' . $e->getMessage() . '</span><br>';
}

// Test 4: Users Function
echo "<h3>✅ Test 4: Users Function</h3>";
try {
    $users_data = orgadmin_role_detector::get_org_admin_users(0, 5, '', 'all');
    echo '<span style="color: green;">✅ Users function works:</span><br>';
    echo "- Total Count: " . $users_data['total_count'] . "<br>";
    echo "- Users Retrieved: " . count($users_data['users']) . "<br>";
    
    if (!empty($users_data['users'])) {
        echo "- Sample Users:<br>";
        foreach (array_slice($users_data['users'], 0, 3) as $user) {
            echo "  * " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['role']) . ")<br>";
        }
    }
} catch (Exception $e) {
    echo '<span style="color: red;">❌ Users function error: ' . $e->getMessage() . '</span><br>';
}

// Test 5: Organization-Specific Data Check
echo "<h3>✅ Test 5: Organization-Specific Data Verification</h3>";
if (!empty($categories)) {
    $category_ids = array_keys($categories);
    list($in_sql, $params) = $DB->get_in_or_equal($category_ids);
    
    // Check all users in managed categories
    $all_org_users = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, r.shortname as role
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
        AND ctx.contextlevel = 40 AND ctx.instanceid $in_sql
        ORDER BY u.lastname, u.firstname
    ", $params);
    
    echo '<span style="color: green;">✅ All users in managed categories (' . count($all_org_users) . ' total):</span><br>';
    if (count($all_org_users) <= 10) {
        foreach ($all_org_users as $user) {
            echo "- " . htmlspecialchars($user->firstname . ' ' . $user->lastname) . " (" . htmlspecialchars($user->role) . ") - " . htmlspecialchars($user->email) . "<br>";
        }
    } else {
        echo "- Too many users to display (showing first 5):<br>";
        foreach (array_slice($all_org_users, 0, 5) as $user) {
            echo "- " . htmlspecialchars($user->firstname . ' ' . $user->lastname) . " (" . htmlspecialchars($user->role) . ") - " . htmlspecialchars($user->email) . "<br>";
        }
        echo "- ... and " . (count($all_org_users) - 5) . " more<br>";
    }
}

// Test 6: Dashboard Access Test
echo "<h3>✅ Test 6: Dashboard Access</h3>";
echo '<a href="org_admin_dashboard.php" style="color: blue;">🔗 Click here to access Organization Admin Dashboard</a><br>';
echo '<a href="debug_org_roles.php" style="color: blue;">🔗 Click here to run role debug</a><br>';

echo "<hr>";
echo "<h3>Overall Test Results:</h3>";
$tests_passed = 0;
$total_tests = 6;

if (orgadmin_role_detector::should_show_org_admin_dashboard()) $tests_passed++;
if (!empty($categories)) $tests_passed++;
// Add other test results...

echo "Tests Passed: " . $tests_passed . "/" . $total_tests . "<br>";
if ($tests_passed >= 4) {
    echo '<span style="color: green; font-weight: bold;">✅ Organization Admin Dashboard appears to be functioning correctly!</span><br>';
} else {
    echo '<span style="color: red; font-weight: bold;">❌ Some issues detected. Please review the test results above.</span><br>';
}
?>