<?php
// Test page to check student role detection
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/test_student.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_title('Student Role Test');

echo $OUTPUT->header();

echo "<h2>Role Detection Test</h2>";

if (orgadmin_role_detector::should_show_student_dashboard()) {
    echo "<p style='color: green; font-weight: bold;'>✅ User should see student dashboard</p>";
    echo "<p><a href='/local/orgadmin/student_dashboard.php'>Go to Student Dashboard</a></p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ User should NOT see student dashboard</p>";
}

$capabilities = orgadmin_role_detector::get_user_course_capabilities();
echo "<h3>User Capabilities:</h3>";
echo "<ul>";
foreach ($capabilities as $cap => $has) {
    $status = $has ? '✅' : '❌';
    echo "<li>$status $cap</li>";
}
echo "</ul>";

echo "<h3>User Info:</h3>";
echo "<ul>";
echo "<li>User ID: " . $USER->id . "</li>";
echo "<li>Username: " . $USER->username . "</li>";
echo "<li>Email: " . $USER->email . "</li>";
echo "</ul>";

echo $OUTPUT->footer();