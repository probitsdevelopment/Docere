<?php
// Debug categories and organization admin setup
require_once('../../config.php');
require_once('./role_detector.php');

require_login();

global $DB;

echo "<h2>Category and Organization Admin Debug</h2>";

echo "<h3>1. Available Course Categories:</h3>";
$categories = $DB->get_records('course_categories', ['visible' => 1], 'name ASC');

if (empty($categories)) {
    echo "No visible course categories found.<br>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Parent</th></tr>";
    foreach ($categories as $category) {
        echo "<tr>";
        echo "<td>" . $category->id . "</td>";
        echo "<td>" . htmlspecialchars($category->name) . "</td>";
        echo "<td>" . htmlspecialchars($category->description) . "</td>";
        echo "<td>" . $category->parent . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>2. Manager Role Assignments at Category Level:</h3>";
$org_admins = $DB->get_records_sql("
    SELECT ra.id, u.firstname, u.lastname, u.email, cc.name as category_name, ctx.instanceid
    FROM {role_assignments} ra
    JOIN {user} u ON u.id = ra.userid
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    JOIN {course_categories} cc ON cc.id = ctx.instanceid
    WHERE r.shortname = 'manager' AND ctx.contextlevel = 40
    ORDER BY cc.name, u.lastname
");

if (empty($org_admins)) {
    echo "No manager role assignments at category level found.<br>";
    echo "<br><strong>To create an organization admin:</strong><br>";
    echo "1. Go to Site Administration > Users > Permissions > Assign system roles<br>";
    echo "2. OR go to a specific course category and assign manager role to a user<br>";
    echo "3. OR create a new user and assign them as manager in a category<br>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>User</th><th>Email</th><th>Category</th><th>Category ID</th></tr>";
    foreach ($org_admins as $admin) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($admin->firstname . ' ' . $admin->lastname) . "</td>";
        echo "<td>" . htmlspecialchars($admin->email) . "</td>";
        echo "<td>" . htmlspecialchars($admin->category_name) . "</td>";
        echo "<td>" . $admin->instanceid . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>3. How to Test Organization Admin Dashboard:</h3>";
echo "1. <strong>Create a new user</strong> (or use existing non-admin user)<br>";
echo "2. <strong>Assign manager role</strong> to that user in a course category<br>";
echo "3. <strong>Login as that user</strong><br>";
echo "4. They should see the organization admin dashboard<br>";

echo "<h3>4. Quick Setup Guide:</h3>";
echo "1. Go to: <a href='" . new moodle_url('/user/edituser.php', ['id' => -1]) . "' target='_blank'>Create New User</a><br>";
echo "2. After creating user, go to a course category and assign them the 'Manager' role<br>";
echo "3. Login as that user to test the org admin dashboard<br>";

if (!empty($categories)) {
    $first_category = reset($categories);
    echo "4. Example: Assign manager role in category '" . htmlspecialchars($first_category->name) . "' (ID: " . $first_category->id . ")<br>";
    echo "   Link: <a href='" . new moodle_url('/admin/roles/assign.php', ['contextid' => context_coursecat::instance($first_category->id)->id]) . "' target='_blank'>Assign Roles in " . htmlspecialchars($first_category->name) . "</a><br>";
}
?>