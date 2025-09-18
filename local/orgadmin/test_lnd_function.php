<?php
// Test the LND dashboard function directly
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

echo "=== LND Dashboard Function Test ===\n\n";

// Check current user
echo "Current user: " . $USER->firstname . " " . $USER->lastname . " (ID: " . $USER->id . ")\n";
echo "Is site admin: " . (is_siteadmin($USER->id) ? 'YES' : 'NO') . "\n\n";

// Check all assessments in database
echo "=== All Assessments in Database ===\n";
$all_assessments = $DB->get_records('orgadmin_assessments');
echo "Total assessments: " . count($all_assessments) . "\n";
foreach ($all_assessments as $assessment) {
    $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
    $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';
    echo "- ID: {$assessment->id}, Title: {$assessment->title}, Status: [{$assessment->status}], Creator: {$creator_name}\n";
}

echo "\n=== Pending Review Assessments ===\n";
$pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
echo "Pending review count: " . count($pending) . "\n";
foreach ($pending as $assessment) {
    echo "- {$assessment->title} (ID: {$assessment->id})\n";
}

// Test the LND organization ID function
echo "\n=== Testing LND Organization Function ===\n";

function get_lnd_organization_id() {
    global $DB, $USER;

    echo "Checking user ID: " . $USER->id . "\n";

    // Check category roles
    $category_roles = $DB->get_records_sql("
        SELECT DISTINCT cc.id as category_id
        FROM {role_assignments} ra
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        JOIN {course_categories} cc ON cc.id = ctx.instanceid
        WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'coursecreator'
        ORDER BY cc.id ASC
    ", [$USER->id]);

    echo "Category roles found: " . count($category_roles) . "\n";

    if (!empty($category_roles)) {
        $first_category = reset($category_roles);
        echo "Returning category ID: " . $first_category->category_id . "\n";
        return $first_category->category_id;
    }

    // Check system roles
    $system_role = $DB->record_exists_sql("
        SELECT 1
        FROM {role_assignments} ra
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE ra.userid = ? AND ctx.contextlevel = 10 AND r.shortname = 'coursecreator'
    ", [$USER->id]);

    echo "System role found: " . ($system_role ? 'YES' : 'NO') . "\n";

    if ($system_role) {
        echo "Returning 0 for site L&D\n";
        return 0;
    }

    // Admin fallback
    if (is_siteadmin($USER->id)) {
        echo "Admin fallback - returning 0\n";
        return 0;
    }

    echo "No L&D role found - returning null\n";
    return null;
}

$org_id = get_lnd_organization_id();
echo "LND Organization ID result: " . ($org_id !== null ? $org_id : 'null') . "\n";

// Test the full pending assessments function
echo "\n=== Testing get_pending_assessments_for_lnd Function ===\n";

function get_pending_assessments_for_lnd() {
    global $DB;

    try {
        $lnd_organization_id = get_lnd_organization_id();
        echo "L&D organization ID: " . ($lnd_organization_id !== null ? $lnd_organization_id : 'null') . "\n";

        if ($lnd_organization_id === null) {
            echo "User is not a valid L&D user - returning empty array\n";
            return [];
        }

        // Build the query based on organization
        if ($lnd_organization_id === 0) {
            // Site L&D - can see all assessments
            $all_assessments = $DB->get_records('orgadmin_assessments');
            echo "Site L&D - showing all assessments: " . count($all_assessments) . "\n";
        } else {
            // Organization L&D
            $all_assessments = $DB->get_records_sql(
                "SELECT * FROM {orgadmin_assessments}
                 WHERE organization_id = ? OR organization_id = 0 OR organization_id IS NULL
                 ORDER BY timemodified DESC",
                [$lnd_organization_id]
            );
            echo "Organization L&D - showing filtered assessments: " . count($all_assessments) . "\n";
        }

        $pending_assessments = [];
        foreach ($all_assessments as $assessment) {
            echo "Processing: ID={$assessment->id}, Title={$assessment->title}, Status=[{$assessment->status}]\n";

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
                echo "✅ Added: {$assessment->title}\n";
            } else {
                echo "❌ Skipped: status mismatch [{$assessment->status}]\n";
            }
        }

        echo "Final pending assessments count: " . count($pending_assessments) . "\n";
        return $pending_assessments;

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        return [];
    }
}

$pending_assessments = get_pending_assessments_for_lnd();
echo "\n=== Final Results ===\n";
echo "Returned " . count($pending_assessments) . " pending assessments:\n";
foreach ($pending_assessments as $idx => $assessment) {
    echo "- {$assessment['title']} by {$assessment['creator']}\n";
}

echo "\n=== Test Complete ===\n";
?>