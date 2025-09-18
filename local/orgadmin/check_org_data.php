<?php
// Check organization data for assessments and current user
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<h1>Organization Data Check</h1>";

echo "<h2>Current User Info</h2>";
echo "<p>User: " . fullname($USER) . " (ID: " . $USER->id . ")</p>";
echo "<p>Is admin: " . (is_siteadmin() ? 'YES' : 'NO') . "</p>";

// Check user's role assignments
echo "<h2>User's Role Assignments</h2>";
$roles = $DB->get_records_sql("
    SELECT DISTINCT r.shortname, ctx.contextlevel, ctx.instanceid, cc.name as category_name
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    LEFT JOIN {course_categories} cc ON cc.id = ctx.instanceid AND ctx.contextlevel = 40
    WHERE ra.userid = ?
    ORDER BY ctx.contextlevel, r.shortname
", [$USER->id]);

foreach ($roles as $role) {
    $context_type = '';
    switch ($role->contextlevel) {
        case 10: $context_type = 'System'; break;
        case 40: $context_type = 'Category'; break;
        case 50: $context_type = 'Course'; break;
        default: $context_type = 'Level ' . $role->contextlevel; break;
    }

    $location = $context_type;
    if ($role->contextlevel == 40 && $role->category_name) {
        $location .= ' (' . $role->category_name . ')';
    } elseif ($role->instanceid) {
        $location .= ' (ID: ' . $role->instanceid . ')';
    }

    echo "<p>Role: <strong>" . $role->shortname . "</strong> at " . $location . "</p>";
}

echo "<h2>All Course Categories</h2>";
$categories = $DB->get_records('course_categories', [], 'sortorder');
foreach ($categories as $cat) {
    echo "<p>ID: " . $cat->id . " - <strong>" . htmlspecialchars($cat->name) . "</strong></p>";
}

echo "<h2>Assessment Organization Data</h2>";
$assessments = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
echo "<table border='1'>";
echo "<tr><th>Title</th><th>Creator</th><th>Org ID</th><th>Org Name</th></tr>";

foreach ($assessments as $assessment) {
    $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
    $creator = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';

    $org_id = isset($assessment->organization_id) ? $assessment->organization_id : 'NULL';
    $org_name = 'Site Level';

    if ($org_id && $org_id > 0) {
        $org = $DB->get_record('course_categories', ['id' => $org_id], 'name');
        $org_name = $org ? $org->name : 'Unknown Org';
    }

    echo "<tr>";
    echo "<td>" . htmlspecialchars($assessment->title) . "</td>";
    echo "<td>" . htmlspecialchars($creator) . "</td>";
    echo "<td>" . $org_id . "</td>";
    echo "<td>" . htmlspecialchars($org_name) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='lnd_dashboard.php'>Back to LND Dashboard</a>";
?>