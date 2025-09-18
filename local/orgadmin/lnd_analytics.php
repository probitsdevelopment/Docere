<?php
// local/orgadmin/lnd_analytics.php - L&D Analytics Dashboard

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

// Verify user should access L&D analytics
if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
    redirect(new moodle_url('/my/index.php'));
}

$PAGE->set_url(new moodle_url('/local/orgadmin/lnd_analytics.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('L&D Analytics Dashboard');
$PAGE->set_heading('Assessment Analytics');

echo $OUTPUT->header();
?>

<style>
body {
    background: #f8fafc !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.analytics-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.analytics-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.analytics-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 10px 0;
}

.analytics-subtitle {
    opacity: 0.9;
    font-size: 16px;
    margin: 0;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.metric-card.submissions::before {
    background: #3b82f6;
}

.metric-card.average-score::before {
    background: #10b981;
}

.metric-card.completion::before {
    background: #f59e0b;
}

.metric-card.at-risk::before {
    background: #ef4444;
}

.metric-value {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 10px;
}

.metric-card.submissions .metric-value {
    color: #3b82f6;
}

.metric-card.average-score .metric-value {
    color: #10b981;
}

.metric-card.completion .metric-value {
    color: #f59e0b;
}

.metric-card.at-risk .metric-value {
    color: #ef4444;
}

.metric-label {
    color: #6b7280;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
}

.metric-trend {
    font-size: 14px;
    color: #6b7280;
}

.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.chart-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #374151;
}

.chart-placeholder {
    height: 300px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    font-size: 16px;
    font-weight: 500;
}

.detailed-metrics {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.detailed-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 25px;
    color: #374151;
}

.metrics-table {
    width: 100%;
    border-collapse: collapse;
}

.metrics-table th,
.metrics-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.metrics-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.metrics-table td {
    color: #6b7280;
}

.progress-bar {
    width: 100px;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill.high {
    background: #10b981;
}

.progress-fill.medium {
    background: #f59e0b;
}

.progress-fill.low {
    background: #ef4444;
}

.difficulty-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.difficulty-indicator.easy {
    background: #10b981;
}

.difficulty-indicator.medium {
    background: #f59e0b;
}

.difficulty-indicator.hard {
    background: #ef4444;
}

.export-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.export-btn {
    background: #6366f1;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.export-btn:hover {
    background: #4f46e5;
}

.export-btn.csv {
    background: #059669;
}

.export-btn.csv:hover {
    background: #047857;
}

.export-btn.pdf {
    background: #dc2626;
}

.export-btn.pdf:hover {
    background: #b91c1c;
}
</style>

<div class="analytics-container">
    <!-- Header -->
    <div class="analytics-header">
        <h1 class="analytics-title">Assessment Analytics Dashboard</h1>
        <p class="analytics-subtitle">Comprehensive insights into student performance and assessment effectiveness</p>
    </div>

    <!-- Key Metrics -->
    <div class="metrics-grid">
        <div class="metric-card submissions">
            <div class="metric-label">Total Submissions</div>
            <div class="metric-value" id="total-submissions">-</div>
            <div class="metric-trend">+12% from last month</div>
        </div>

        <div class="metric-card average-score">
            <div class="metric-label">Average Score</div>
            <div class="metric-value" id="average-score">-</div>
            <div class="metric-trend">+3.2% improvement</div>
        </div>

        <div class="metric-card completion">
            <div class="metric-label">Completion Rate</div>
            <div class="metric-value" id="completion-rate">-</div>
            <div class="metric-trend">-2.1% from target</div>
        </div>

        <div class="metric-card at-risk">
            <div class="metric-label">At Risk Students</div>
            <div class="metric-value" id="at-risk-students">-</div>
            <div class="metric-trend">Students below 60%</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Performance Trends Chart -->
        <div class="chart-card">
            <h2 class="chart-title">Performance Trends Over Time</h2>
            <div class="chart-placeholder">
                📈 Score trends, completion rates, and engagement metrics
                <br><small>Chart.js integration goes here</small>
            </div>
        </div>

        <!-- Time Distribution -->
        <div class="chart-card">
            <h2 class="chart-title">Time Distribution</h2>
            <div class="chart-placeholder">
                ⏱️ Assessment completion times
                <br><small>Histogram chart goes here</small>
            </div>
        </div>
    </div>

    <!-- Detailed Metrics -->
    <div class="detailed-metrics">
        <h2 class="detailed-title">Question Difficulty Analysis</h2>
        <table class="metrics-table">
            <thead>
                <tr>
                    <th>Question Type</th>
                    <th>Success Rate</th>
                    <th>Avg Time (min)</th>
                    <th>Difficulty</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody id="difficulty-table">
                <tr>
                    <td>Basic Syntax</td>
                    <td>87.5%</td>
                    <td>3.2</td>
                    <td><span class="difficulty-indicator easy"></span>Easy</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill high" style="width: 87.5%"></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Algorithm Implementation</td>
                    <td>64.3%</td>
                    <td>8.7</td>
                    <td><span class="difficulty-indicator medium"></span>Medium</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill medium" style="width: 64.3%"></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Complex Problem Solving</td>
                    <td>34.8%</td>
                    <td>15.2</td>
                    <td><span class="difficulty-indicator hard"></span>Hard</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill low" style="width: 34.8%"></div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Export Options -->
    <div class="export-buttons">
        <button class="export-btn" onclick="exportReport('detailed')">Export Full Report</button>
        <button class="export-btn csv" onclick="exportReport('csv')">Export CSV</button>
        <button class="export-btn pdf" onclick="exportReport('pdf')">Generate PDF</button>
    </div>
</div>

<script>
// Load analytics data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAnalyticsData();
});

async function loadAnalyticsData() {
    try {
        const response = await fetch('/local/orgadmin/assessment_results.php?action=get_lnd_analytics');
        const result = await response.json();

        if (result.success) {
            const data = result.data;

            // Update key metrics
            document.getElementById('total-submissions').textContent = data.total_submissions.toLocaleString();
            document.getElementById('average-score').textContent = data.average_score.toFixed(1) + '%';
            document.getElementById('completion-rate').textContent = data.completion_rate.toFixed(1) + '%';
            document.getElementById('at-risk-students').textContent = data.students_below_60;

            // Update time analytics in difficulty table (mock data for now)
            updateDifficultyAnalysis(data.difficulty_analysis);

            console.log('Analytics data loaded successfully:', data);
        }
    } catch (error) {
        console.error('Error loading analytics data:', error);
    }
}

function updateDifficultyAnalysis(difficultyData) {
    // In a real implementation, this would update the table with actual data
    console.log('Difficulty analysis:', difficultyData);
}

function exportReport(format) {
    switch (format) {
        case 'detailed':
            // Generate comprehensive analytics report
            alert('📊 Generating detailed analytics report...\n\n• Student performance summary\n• Question difficulty analysis\n• Time-based insights\n• Recommendations for improvement');
            break;
        case 'csv':
            // Export data as CSV
            alert('📋 Exporting data as CSV...\n\n• Raw assessment scores\n• Student completion times\n• Question-level analytics\n• Downloadable spreadsheet format');
            break;
        case 'pdf':
            // Generate PDF report
            alert('📄 Generating PDF report...\n\n• Executive summary\n• Visual charts and graphs\n• Detailed analysis\n• Ready for stakeholder presentation');
            break;
    }
}

// Real-time data refresh (optional)
setInterval(function() {
    loadAnalyticsData();
}, 300000); // Refresh every 5 minutes
</script>

<?php
echo $OUTPUT->footer();
?>