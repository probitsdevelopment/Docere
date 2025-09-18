<?php
// local/orgadmin/assessment_results.php - Assessment Results Handler

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/assessment_results.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Assessment Results');
$PAGE->set_heading('Assessment Results');

// Handle AJAX requests for assessment operations
$action = optional_param('action', '', PARAM_TEXT);

// Mock database functions - replace with real Moodle database operations
class AssessmentResultsManager {

    public static function save_assessment_submission($data) {
        global $DB, $USER;

        // Get student's organization (category) for proper data separation
        $student_organization = $DB->get_record_sql("
            SELECT DISTINCT cc.id as org_id, cc.name as org_name
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            JOIN {course_categories} cc ON cc.id = ctx.instanceid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'student'
            LIMIT 1
        ", [$data['student_id']]);

        // TODO: Save to Moodle database with organization separation
        // Table: local_orgadmin_assessment_submissions
        /*
        CREATE TABLE local_orgadmin_assessment_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assessment_id VARCHAR(255) NOT NULL,
            student_id INT NOT NULL,
            organization_id INT, -- For data separation
            code_solution TEXT,
            test_results JSON,
            score INT DEFAULT 0,
            max_score INT DEFAULT 100,
            time_taken INT DEFAULT 0, -- seconds
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'submitted', -- submitted, graded, reviewed
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_organization_assessment (organization_id, assessment_id),
            INDEX idx_student_organization (student_id, organization_id)
        );

        $submission_record = [
            'assessment_id' => $data['assessment_id'],
            'student_id' => $data['student_id'],
            'organization_id' => $student_organization ? $student_organization->org_id : null,
            'code_solution' => $data['code_solution'],
            'test_results' => $data['test_results'],
            'score' => $data['score'],
            'max_score' => $data['max_score'],
            'time_taken' => $data['time_taken'],
            'status' => 'submitted',
            'submitted_at' => time()
        ];

        $submission_id = $DB->insert_record('local_orgadmin_assessment_submissions', $submission_record);
        */

        $submission_id = rand(1000, 9999); // Mock ID for now

        return [
            'success' => true,
            'submission_id' => $submission_id,
            'organization_id' => $student_organization ? $student_organization->org_id : null,
            'organization_name' => $student_organization ? $student_organization->org_name : 'No Organization',
            'message' => 'Assessment submitted successfully'
        ];
    }

    public static function get_student_results($student_id, $assessment_id = null) {
        // Mock data - replace with real database query
        return [
            [
                'id' => 1,
                'assessment_id' => 'java-basics',
                'assessment_title' => 'Java Basics Test',
                'student_id' => $student_id,
                'score' => 85,
                'max_score' => 100,
                'percentage' => 85,
                'time_taken' => 2340, // seconds
                'submitted_at' => '2024-09-15 14:30:00',
                'status' => 'graded',
                'test_cases_passed' => 8,
                'total_test_cases' => 10
            ],
            [
                'id' => 2,
                'assessment_id' => 'python-basics',
                'assessment_title' => 'Python Fundamentals',
                'student_id' => $student_id,
                'score' => 92,
                'max_score' => 100,
                'percentage' => 92,
                'time_taken' => 1890,
                'submitted_at' => '2024-09-10 16:15:00',
                'status' => 'graded',
                'test_cases_passed' => 10,
                'total_test_cases' => 10
            ]
        ];
    }

    public static function get_lnd_assessment_analytics($assessment_id = null) {
        global $USER, $DB;

        // Determine if this is Site L&D or Organization L&D
        $is_site_lnd = self::is_site_level_role($USER->id, 'coursecreator');
        $organization_filter = '';
        $params = [];
        $data_source = '';

        if ($is_site_lnd) {
            // Site L&D - ONLY see site-level data (system context roles)
            $students_sql = "
                SELECT COUNT(DISTINCT u.id) as total_submissions
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} ctx ON ctx.id = ra.contextid
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                AND r.shortname = 'student'
                AND ctx.contextlevel = 10
            ";
            $data_source = 'site';
        } else {
            // Organization L&D - ONLY see their organization data
            $categories = $DB->get_records_sql("
                SELECT DISTINCT cc.id, cc.name
                FROM {role_assignments} ra
                JOIN {context} ctx ON ctx.id = ra.contextid
                JOIN {role} r ON r.id = ra.roleid
                JOIN {course_categories} cc ON cc.id = ctx.instanceid
                WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'coursecreator'
            ", [$USER->id]);

            if (!empty($categories)) {
                $category = reset($categories);
                $students_sql = "
                    SELECT COUNT(DISTINCT u.id) as total_submissions
                    FROM {user} u
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {role} r ON r.id = ra.roleid
                    JOIN {context} ctx ON ctx.id = ra.contextid
                    WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                    AND r.shortname = 'student'
                    AND ctx.contextlevel = 40 AND ctx.instanceid = ?
                ";
                $params[] = $category->id;
                $data_source = 'organization_' . $category->id;
            }
        }

        $total_submissions = $DB->get_field_sql($students_sql, $params) ?: 0;

        // Mock analytics data based on data source
        $base_score = rand(70, 80);
        $completion_rate = rand(85, 95);
        $students_below_60 = round($total_submissions * (rand(5, 15) / 100));

        return [
            'total_submissions' => $total_submissions,
            'average_score' => $base_score + (rand(-5, 10) / 10),
            'completion_rate' => $completion_rate + (rand(-3, 5) / 10),
            'students_below_60' => $students_below_60,
            'data_source' => $data_source,
            'is_site_level' => $is_site_lnd,
            'time_analytics' => [
                'average_time' => rand(1800, 2700), // 30-45 minutes
                'fastest_completion' => rand(600, 1200), // 10-20 minutes
                'slowest_completion' => rand(2700, 3600) // 45-60 minutes
            ],
            'difficulty_analysis' => [
                'easy_questions' => rand(60, 75), // avg success rate
                'medium_questions' => rand(40, 55),
                'hard_questions' => rand(20, 35)
            ]
        ];
    }

    public static function get_stakeholder_heatmap_data($organization_id = null) {
        global $USER, $DB;

        // Determine if this is Site Stakeholder or Organization Stakeholder
        $is_site_stakeholder = self::is_site_level_role($USER->id, 'teacher');
        $students_sql = '';
        $params = [];
        $data_source = '';
        $org_name = '';

        if ($is_site_stakeholder) {
            // Site Stakeholder - ONLY see site-level students (system context roles)
            $students_sql = "
                SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} ctx ON ctx.id = ra.contextid
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                AND r.shortname = 'student'
                AND ctx.contextlevel = 10
                ORDER BY u.timecreated DESC
                LIMIT 50
            ";
            $data_source = 'site';
            $org_name = 'Site Level';
        } else {
            // Organization Stakeholder - ONLY see their organization students
            $categories = $DB->get_records_sql("
                SELECT DISTINCT cc.id, cc.name
                FROM {role_assignments} ra
                JOIN {context} ctx ON ctx.id = ra.contextid
                JOIN {role} r ON r.id = ra.roleid
                JOIN {course_categories} cc ON cc.id = ctx.instanceid
                WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'teacher'
            ", [$USER->id]);

            if (!empty($categories)) {
                $category = reset($categories);
                $organization_id = $category->id;
                $org_name = $category->name;

                $students_sql = "
                    SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated
                    FROM {user} u
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {role} r ON r.id = ra.roleid
                    JOIN {context} ctx ON ctx.id = ra.contextid
                    WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                    AND r.shortname = 'student'
                    AND ctx.contextlevel = 40 AND ctx.instanceid = ?
                    ORDER BY u.timecreated DESC
                    LIMIT 50
                ";
                $params[] = $organization_id;
                $data_source = 'organization_' . $organization_id;
            }
        }

        $students_data = $DB->get_records_sql($students_sql, $params);
        $students = [];

        foreach ($students_data as $student) {
            // Mock performance data - in real implementation, calculate from actual assessment submissions
            $average_score = rand(40, 95);
            $risk_level = $average_score < 60 ? 'high' : ($average_score < 80 ? 'medium' : 'low');

            $students[] = [
                'id' => $student->id,
                'name' => trim($student->firstname . ' ' . $student->lastname),
                'email' => $student->email,
                'assessments_taken' => rand(3, 12),
                'average_score' => $average_score,
                'improvement_trend' => rand(-10, 20) . '%',
                'last_assessment' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
                'risk_level' => $risk_level,
                'coding_skills' => [
                    'java' => rand(30, 95),
                    'python' => rand(30, 95),
                    'javascript' => rand(30, 95)
                ]
            ];
        }

        // Calculate summary statistics
        $total_students = count($students);
        $at_risk_students = count(array_filter($students, function($s) { return $s['risk_level'] === 'high'; }));
        $high_performers = count(array_filter($students, function($s) { return $s['average_score'] >= 85; }));
        $average_improvement = array_sum(array_map(function($s) {
            return (float)str_replace('%', '', $s['improvement_trend']);
        }, $students)) / ($total_students ?: 1);
        $organization_performance = array_sum(array_column($students, 'average_score')) / ($total_students ?: 1);

        return [
            'students' => $students,
            'summary' => [
                'total_students' => $total_students,
                'at_risk_students' => $at_risk_students,
                'high_performers' => $high_performers,
                'average_improvement' => sprintf('+%.1f%%', $average_improvement),
                'organization_performance' => round($organization_performance, 1),
                'organization_id' => $organization_id,
                'organization_name' => $org_name,
                'data_source' => $data_source,
                'is_site_level' => $is_site_stakeholder
            ]
        ];
    }

    public static function get_tdd_analysis($student_id, $assessment_id) {
        // Mock TDD approach analysis
        return [
            'student_info' => [
                'id' => $student_id,
                'name' => 'John Doe',
                'assessment' => 'Java Basics Test'
            ],
            'code_analysis' => [
                'test_driven_approach' => 75, // percentage score
                'code_quality' => 82,
                'best_practices' => 68,
                'complexity_handling' => 79
            ],
            'test_coverage' => [
                'edge_cases_covered' => 6,
                'total_edge_cases' => 8,
                'coverage_percentage' => 75
            ],
            'recommendations' => [
                'Focus on handling edge cases',
                'Improve error handling in code',
                'Practice more complex algorithmic problems'
            ],
            'code_submission' => "public class Solution {\n    public int[] fibonacci(int n) {\n        if (n <= 0) return new int[0];\n        if (n == 1) return new int[]{0};\n        \n        int[] result = new int[n];\n        result[0] = 0;\n        result[1] = 1;\n        \n        for (int i = 2; i < n; i++) {\n            result[i] = result[i-1] + result[i-2];\n        }\n        \n        return result;\n    }\n}"
        ];
    }

    /**
     * Check if user has site-level role (context level 10) vs organization role (context level 40)
     * @param int $user_id
     * @param string $role_shortname
     * @return bool
     */
    private static function is_site_level_role($user_id, $role_shortname) {
        global $DB;

        // Check if user has the role at SITE level (context level 10 = system context)
        $has_site_role = $DB->record_exists_sql("
            SELECT 1
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = ? AND r.shortname = ? AND ctx.contextlevel = 10
        ", [$user_id, $role_shortname]);

        return $has_site_role;
    }

    /**
     * Check if user has organization-level role (context level 40)
     * @param int $user_id
     * @param string $role_shortname
     * @return bool
     */
    private static function is_organization_level_role($user_id, $role_shortname) {
        global $DB;

        // Check if user has the role at ORGANIZATION level (context level 40 = category context)
        $has_org_role = $DB->record_exists_sql("
            SELECT 1
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = ? AND r.shortname = ? AND ctx.contextlevel = 40
        ", [$user_id, $role_shortname]);

        return $has_org_role;
    }
}

// Handle AJAX requests
if (!empty($action)) {
    header('Content-Type: application/json');

    switch ($action) {
        case 'submit_assessment':
            $assessment_id = required_param('assessment_id', PARAM_TEXT);
            $student_id = required_param('student_id', PARAM_INT);
            $code = required_param('code', PARAM_RAW);
            $time_taken = optional_param('time_taken', 0, PARAM_INT);

            $data = [
                'assessment_id' => $assessment_id,
                'student_id' => $student_id,
                'code_solution' => $code,
                'time_taken' => $time_taken,
                'test_results' => json_encode(['passed' => 8, 'total' => 10]),
                'score' => 85, // Calculate based on test results
                'max_score' => 100
            ];

            $result = AssessmentResultsManager::save_assessment_submission($data);
            echo json_encode($result);
            exit;

        case 'get_student_results':
            $student_id = required_param('student_id', PARAM_INT);
            $results = AssessmentResultsManager::get_student_results($student_id);
            echo json_encode(['success' => true, 'data' => $results]);
            exit;

        case 'get_lnd_analytics':
            $assessment_id = optional_param('assessment_id', null, PARAM_TEXT);
            $analytics = AssessmentResultsManager::get_lnd_assessment_analytics($assessment_id);
            echo json_encode(['success' => true, 'data' => $analytics]);
            exit;

        case 'get_stakeholder_heatmap':
            $organization_id = optional_param('organization_id', null, PARAM_INT);
            $heatmap = AssessmentResultsManager::get_stakeholder_heatmap_data($organization_id);
            echo json_encode(['success' => true, 'data' => $heatmap]);
            exit;

        case 'get_tdd_analysis':
            $student_id = required_param('student_id', PARAM_INT);
            $assessment_id = required_param('assessment_id', PARAM_TEXT);
            $analysis = AssessmentResultsManager::get_tdd_analysis($student_id, $assessment_id);
            echo json_encode(['success' => true, 'data' => $analysis]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// If not AJAX, show interface
echo $OUTPUT->header();
?>

<style>
body {
    background: #f8fafc;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.results-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
}

.results-header {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.results-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 10px 0;
    color: #2d3748;
}

.results-subtitle {
    color: #64748b;
    font-size: 16px;
    margin: 0;
}

.api-endpoints {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.endpoint {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.endpoint-title {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.endpoint-url {
    background: #2d3748;
    color: #ffffff;
    padding: 10px 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 14px;
    margin-bottom: 15px;
}

.endpoint-description {
    color: #64748b;
    line-height: 1.6;
}

.parameters {
    margin-top: 15px;
}

.parameters-title {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.parameter {
    background: #ebf8ff;
    border-left: 3px solid #3182ce;
    padding: 10px 15px;
    margin-bottom: 8px;
    font-family: monospace;
    font-size: 13px;
}

.parameter-name {
    font-weight: 600;
    color: #2b6cb0;
}

.parameter-type {
    color: #805ad5;
    margin-left: 10px;
}

.parameter-description {
    color: #4a5568;
    margin-left: 10px;
}

.test-buttons {
    margin-top: 30px;
    display: flex;
    gap: 15px;
}

.test-btn {
    background: #3182ce;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.test-btn:hover {
    background: #2c5282;
}

.test-btn.success {
    background: #38a169;
}

.test-btn.danger {
    background: #e53e3e;
}

#test-results {
    margin-top: 20px;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    font-family: monospace;
    font-size: 14px;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}
</style>

<div class="results-container">
    <div class="results-header">
        <h1 class="results-title">Assessment Results API</h1>
        <p class="results-subtitle">Backend system for handling assessment submissions and analytics</p>
    </div>

    <div class="api-endpoints">
        <h2 style="margin-top: 0; margin-bottom: 30px; color: #2d3748;">Available Endpoints</h2>

        <!-- Submit Assessment -->
        <div class="endpoint">
            <h3 class="endpoint-title">1. Submit Assessment</h3>
            <div class="endpoint-url">POST /local/orgadmin/assessment_results.php?action=submit_assessment</div>
            <p class="endpoint-description">
                Handles student assessment submission including code, test results, and timing data.
            </p>
            <div class="parameters">
                <h4 class="parameters-title">Required Parameters:</h4>
                <div class="parameter">
                    <span class="parameter-name">assessment_id</span>
                    <span class="parameter-type">(string)</span>
                    <span class="parameter-description">- Unique assessment identifier</span>
                </div>
                <div class="parameter">
                    <span class="parameter-name">student_id</span>
                    <span class="parameter-type">(int)</span>
                    <span class="parameter-description">- Student's Moodle user ID</span>
                </div>
                <div class="parameter">
                    <span class="parameter-name">code</span>
                    <span class="parameter-type">(text)</span>
                    <span class="parameter-description">- Student's code solution</span>
                </div>
                <div class="parameter">
                    <span class="parameter-name">time_taken</span>
                    <span class="parameter-type">(int)</span>
                    <span class="parameter-description">- Time taken in seconds</span>
                </div>
            </div>
        </div>

        <!-- Get Student Results -->
        <div class="endpoint">
            <h3 class="endpoint-title">2. Get Student Results</h3>
            <div class="endpoint-url">GET /local/orgadmin/assessment_results.php?action=get_student_results</div>
            <p class="endpoint-description">
                Retrieves all assessment results for a specific student.
            </p>
            <div class="parameters">
                <h4 class="parameters-title">Required Parameters:</h4>
                <div class="parameter">
                    <span class="parameter-name">student_id</span>
                    <span class="parameter-type">(int)</span>
                    <span class="parameter-description">- Student's Moodle user ID</span>
                </div>
            </div>
        </div>

        <!-- L&D Analytics -->
        <div class="endpoint">
            <h3 class="endpoint-title">3. Get L&D Analytics</h3>
            <div class="endpoint-url">GET /local/orgadmin/assessment_results.php?action=get_lnd_analytics</div>
            <p class="endpoint-description">
                Provides comprehensive analytics for L&D dashboard including completion rates, scores, and trends.
            </p>
            <div class="parameters">
                <h4 class="parameters-title">Optional Parameters:</h4>
                <div class="parameter">
                    <span class="parameter-name">assessment_id</span>
                    <span class="parameter-type">(string)</span>
                    <span class="parameter-description">- Filter by specific assessment</span>
                </div>
            </div>
        </div>

        <!-- Stakeholder Heatmap -->
        <div class="endpoint">
            <h3 class="endpoint-title">4. Get Stakeholder Heatmap</h3>
            <div class="endpoint-url">GET /local/orgadmin/assessment_results.php?action=get_stakeholder_heatmap</div>
            <p class="endpoint-description">
                Returns student performance heatmap data for stakeholder dashboard.
            </p>
            <div class="parameters">
                <h4 class="parameters-title">Optional Parameters:</h4>
                <div class="parameter">
                    <span class="parameter-name">organization_id</span>
                    <span class="parameter-type">(int)</span>
                    <span class="parameter-description">- Filter by organization</span>
                </div>
            </div>
        </div>

        <!-- TDD Analysis -->
        <div class="endpoint">
            <h3 class="endpoint-title">5. Get TDD Analysis</h3>
            <div class="endpoint-url">GET /local/orgadmin/assessment_results.php?action=get_tdd_analysis</div>
            <p class="endpoint-description">
                Provides detailed TDD approach analysis for individual student submissions.
            </p>
            <div class="parameters">
                <h4 class="parameters-title">Required Parameters:</h4>
                <div class="parameter">
                    <span class="parameter-name">student_id</span>
                    <span class="parameter-type">(int)</span>
                    <span class="parameter-description">- Student's Moodle user ID</span>
                </div>
                <div class="parameter">
                    <span class="parameter-name">assessment_id</span>
                    <span class="parameter-type">(string)</span>
                    <span class="parameter-description">- Assessment identifier</span>
                </div>
            </div>
        </div>

        <div class="test-buttons">
            <button class="test-btn" onclick="testEndpoint('submit_assessment')">Test Submit Assessment</button>
            <button class="test-btn" onclick="testEndpoint('get_student_results')">Test Student Results</button>
            <button class="test-btn success" onclick="testEndpoint('get_lnd_analytics')">Test L&D Analytics</button>
            <button class="test-btn danger" onclick="testEndpoint('get_stakeholder_heatmap')">Test Stakeholder Heatmap</button>
            <button class="test-btn" onclick="testEndpoint('get_tdd_analysis')">Test TDD Analysis</button>
        </div>

        <div id="test-results"></div>
    </div>
</div>

<script>
async function testEndpoint(action) {
    const resultsDiv = document.getElementById('test-results');
    resultsDiv.textContent = `Testing ${action}...`;

    try {
        let url = `assessment_results.php?action=${action}`;
        let options = { method: 'GET' };

        // Add test parameters for different endpoints
        switch (action) {
            case 'submit_assessment':
                options = {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        'assessment_id': 'java-basics',
                        'student_id': <?php echo $USER->id; ?>,
                        'code': 'public class Solution { /* test code */ }',
                        'time_taken': 1800
                    })
                };
                break;
            case 'get_student_results':
                url += `&student_id=<?php echo $USER->id; ?>`;
                break;
            case 'get_tdd_analysis':
                url += `&student_id=<?php echo $USER->id; ?>&assessment_id=java-basics`;
                break;
        }

        const response = await fetch(url, options);
        const data = await response.json();

        resultsDiv.textContent = `Response for ${action}:\n\n${JSON.stringify(data, null, 2)}`;
    } catch (error) {
        resultsDiv.textContent = `Error testing ${action}:\n\n${error.message}`;
    }
}

// Test all endpoints on page load (for demo)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Assessment Results API loaded');
    console.log('Available endpoints:', [
        'submit_assessment',
        'get_student_results',
        'get_lnd_analytics',
        'get_stakeholder_heatmap',
        'get_tdd_analysis'
    ]);
});
</script>

<?php
echo $OUTPUT->footer();
?>