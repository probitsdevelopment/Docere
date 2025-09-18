<?php
// Debug version of LND dashboard to see what's happening
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/debug_lnd.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('LND Debug');
$PAGE->set_heading('LND Dashboard Debug');

echo $OUTPUT->header();

echo "<h1>LND Dashboard Debug Information</h1>";

// Check user info
echo "<h2>User Information</h2>";
echo "<p>User ID: " . $USER->id . "</p>";
echo "<p>Username: " . $USER->username . "</p>";
echo "<p>Full name: " . fullname($USER) . "</p>";
echo "<p>Is site admin: " . (is_siteadmin() ? 'YES' : 'NO') . "</p>";

// Check role detection
echo "<h2>Role Detection</h2>";
echo "<p>Should show LND dashboard: " . (orgadmin_role_detector::should_show_lnd_dashboard() ? 'YES' : 'NO') . "</p>";
echo "<p>Should show admin dashboard: " . (orgadmin_role_detector::should_show_admin_dashboard() ? 'YES' : 'NO') . "</p>";
echo "<p>Should show teacher dashboard: " . (orgadmin_role_detector::should_show_teacher_dashboard() ? 'YES' : 'NO') . "</p>";

// Check capabilities
echo "<h2>Capabilities</h2>";
$systemcontext = context_system::instance();
echo "<p>moodle/grade:manage (system): " . (has_capability('moodle/grade:manage', $systemcontext) ? 'YES' : 'NO') . "</p>";
echo "<p>moodle/site:config: " . (has_capability('moodle/site:config', $systemcontext) ? 'YES' : 'NO') . "</p>";

// Check categories
echo "<h2>Category Capabilities</h2>";
foreach (core_course_category::get_all() as $category) {
    $categorycontext = context_coursecat::instance($category->id);
    if (has_capability('moodle/grade:manage', $categorycontext)) {
        echo "<p>Has grade:manage in category: " . $category->name . " (ID: " . $category->id . ")</p>";
    }
}

// Test our LND organization function
echo "<h2>LND Organization Function Test</h2>";

function debug_get_lnd_organization_id() {
    global $DB, $USER;

    echo "<p>Checking user ID: " . $USER->id . "</p>";

    $systemcontext = context_system::instance();

    // Check system level L&D
    $has_system_grade = has_capability('moodle/grade:manage', $systemcontext);
    $is_admin = is_siteadmin();
    $has_config = has_capability('moodle/site:config', $systemcontext);

    echo "<p>System level checks:</p>";
    echo "<ul>";
    echo "<li>Has grade:manage: " . ($has_system_grade ? 'YES' : 'NO') . "</li>";
    echo "<li>Is site admin: " . ($is_admin ? 'YES' : 'NO') . "</li>";
    echo "<li>Has site:config: " . ($has_config ? 'YES' : 'NO') . "</li>";
    echo "</ul>";

    if ($has_system_grade && !$is_admin && !$has_config) {
        echo "<p>✅ System L&D found via grade:manage capability</p>";
        return 0;
    }

    // Check category level L&D
    foreach (core_course_category::get_all() as $category) {
        $categorycontext = context_coursecat::instance($category->id);
        if (has_capability('moodle/grade:manage', $categorycontext)) {
            echo "<p>✅ Category L&D found for category " . $category->name . " (ID: " . $category->id . ")</p>";
            return $category->id;
        }
    }

    // Admin fallback
    if (is_siteadmin($USER->id)) {
        echo "<p>✅ User is admin, treating as site L&D</p>";
        return 0;
    }

    echo "<p>❌ No L&D role found</p>";
    return null;
}

$org_id = debug_get_lnd_organization_id();
echo "<p><strong>Organization ID result: " . ($org_id !== null ? $org_id : 'null') . "</strong></p>";

// Check assessments in database
echo "<h2>Assessments in Database</h2>";
try {
    $all_assessments = $DB->get_records('orgadmin_assessments');
    echo "<p>Total assessments: " . count($all_assessments) . "</p>";

    $pending_count = 0;
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>User</th><th>Org ID</th></tr>";

    foreach ($all_assessments as $assessment) {
        $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
        $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';

        echo "<tr>";
        echo "<td>" . $assessment->id . "</td>";
        echo "<td>" . htmlspecialchars($assessment->title) . "</td>";
        echo "<td><strong>" . $assessment->status . "</strong></td>";
        echo "<td>" . $creator_name . "</td>";
        echo "<td>" . ($assessment->organization_id ?: 'null') . "</td>";
        echo "</tr>";

        if ($assessment->status === 'pending_review') {
            $pending_count++;
        }
    }
    echo "</table>";
    echo "<p><strong>Pending review assessments: " . $pending_count . "</strong></p>";

} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test the full function
echo "<h2>Testing get_pending_assessments_for_lnd Function</h2>";

function debug_get_pending_assessments_for_lnd() {
    global $DB;

    try {
        $lnd_organization_id = debug_get_lnd_organization_id();
        echo "<p>L&D organization ID: " . ($lnd_organization_id !== null ? $lnd_organization_id : 'null') . "</p>";

        if ($lnd_organization_id === null) {
            echo "<p>❌ User is not a valid L&D user</p>";
            echo "<p>🔄 Trying fallback approach...</p>";

            // Fallback: get all pending assessments
            $all_pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
            echo "<p>Found " . count($all_pending) . " pending assessments via fallback</p>";

            $pending_assessments = [];
            foreach ($all_pending as $assessment) {
                $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
                $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown User';

                $trainer_type = '';
                if ($assessment->organization_id === 0 || $assessment->organization_id === null) {
                    $trainer_type = ' (Site Trainer)';
                } else {
                    $org = $DB->get_record('course_categories', ['id' => $assessment->organization_id], 'name');
                    $org_name = $org ? $org->name : 'Unknown Org';
                    $trainer_type = ' (' . $org_name . ')';
                }

                $pending_assessments[] = [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'creator' => $creator_name . $trainer_type,
                    'questions' => 1,
                    'time' => $assessment->duration ?: 45,
                    'students' => 0,
                    'organization_id' => $assessment->organization_id
                ];
                echo "<p>✅ Added: " . $assessment->title . " by " . $creator_name . $trainer_type . "</p>";
            }

            return $pending_assessments;
        }

        // Normal flow for valid L&D users
        if ($lnd_organization_id === 0) {
            $all_assessments = $DB->get_records('orgadmin_assessments');
            echo "<p>Site L&D - showing all assessments: " . count($all_assessments) . "</p>";
        } else {
            $all_assessments = $DB->get_records_sql(
                "SELECT * FROM {orgadmin_assessments}
                 WHERE organization_id = ? OR organization_id = 0 OR organization_id IS NULL
                 ORDER BY timemodified DESC",
                [$lnd_organization_id]
            );
            echo "<p>Organization L&D - showing filtered assessments: " . count($all_assessments) . "</p>";
        }

        $pending_assessments = [];
        foreach ($all_assessments as $assessment) {
            echo "<p>Processing: ID={$assessment->id}, Title={$assessment->title}, Status=[{$assessment->status}]</p>";

            if (trim($assessment->status) === 'pending_review') {
                $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
                $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown User';

                $trainer_type = '';
                if ($assessment->organization_id === 0 || $assessment->organization_id === null) {
                    $trainer_type = ' (Site Trainer)';
                } else {
                    $org = $DB->get_record('course_categories', ['id' => $assessment->organization_id], 'name');
                    $org_name = $org ? $org->name : 'Unknown Org';
                    $trainer_type = ' (' . $org_name . ')';
                }

                $pending_assessments[] = [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'creator' => $creator_name . $trainer_type,
                    'questions' => 1,
                    'time' => $assessment->duration ?: 0,
                    'students' => 0,
                    'organization_id' => $assessment->organization_id
                ];
                echo "<p>✅ Added: {$assessment->title}</p>";
            } else {
                echo "<p>❌ Skipped: status mismatch [{$assessment->status}]</p>";
            }
        }

        echo "<p><strong>Final pending assessments count: " . count($pending_assessments) . "</strong></p>";
        return $pending_assessments;

    } catch (Exception $e) {
        echo "<p>❌ ERROR: " . $e->getMessage() . "</p>";
        return [];
    }
}

$pending_assessments = debug_get_pending_assessments_for_lnd();

echo "<h2>Final Results</h2>";
echo "<p>Returned " . count($pending_assessments) . " pending assessments:</p>";
if (!empty($pending_assessments)) {
    echo "<ul>";
    foreach ($pending_assessments as $assessment) {
        echo "<li><strong>" . $assessment['title'] . "</strong> by " . $assessment['creator'] . " (ID: " . $assessment['id'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>❌ No assessments returned</p>";
}

echo "<br><a href='lnd_dashboard.php'>Go to LND Dashboard</a>";

echo $OUTPUT->footer();
?>