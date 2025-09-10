<?php
// Simple redirect test for students
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

echo "<h1>Redirect Test</h1>";
echo "<p>Testing student role detection...</p>";

if (orgadmin_role_detector::should_show_student_dashboard()) {
    echo "<p style='color: green;'>✅ You should see student dashboard!</p>";
    echo "<p>Redirecting in 3 seconds...</p>";
    echo "<script>";
    echo "setTimeout(function() { window.location.href = '/moodle42/moodle/local/orgadmin/student_dashboard.php'; }, 3000);";
    echo "</script>";
} else {
    echo "<p style='color: red;'>❌ You should NOT see student dashboard</p>";
    echo "<p>Your role capabilities:</p>";
    $capabilities = orgadmin_role_detector::get_user_course_capabilities();
    echo "<ul>";
    foreach ($capabilities as $cap => $has) {
        $status = $has ? '✅' : '❌';
        echo "<li>$status $cap</li>";
    }
    echo "</ul>";
}