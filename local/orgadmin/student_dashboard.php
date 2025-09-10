<?php
// local/orgadmin/student_dashboard.php - Student Dashboard matching exact screenshot design

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

// Only redirect if user shouldn't see student dashboard AND they're accessing this page directly
// Don't interfere with normal Moodle navigation
if (!orgadmin_role_detector::should_show_student_dashboard()) {
    // Redirect non-students to appropriate dashboard
    $dashboardurl = orgadmin_role_detector::get_dashboard_url();
    redirect($dashboardurl);
}

$PAGE->set_url(new moodle_url('/local/orgadmin/student_dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('Student Dashboard');
$PAGE->set_heading('Student Dashboard');
$PAGE->navbar->add('Student Dashboard');

echo $OUTPUT->header();

// Custom CSS to exactly match the screenshot
echo html_writer::start_tag('style');
echo '
/* Reset and base styles */
html, body {
    background-color: #f5f7fa !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
    overflow-y: hidden !important;
    height: 100vh !important;
}

/* Override Moodle container constraints */
#page-wrapper,
#page,
#page-content,
.container-fluid,
#region-main-box,
#region-main,
.row {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
}

/* Fix double scrollbar issue */
#page-wrapper {
    overflow: visible !important;
}

#page {
    overflow: visible !important;
    height: auto !important;
}

#region-main {
    overflow: visible !important;
}

/* Keep Moodle navigation but style it */
.page-header-headings {
    display: none !important;
}

/* Style the navbar to match our design */
.navbar {
    background: #fff !important;
    border-bottom: 1px solid #e9ecef !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
}

.navbar-nav .nav-link {
    color: #5a6c7d !important;
    font-weight: 500 !important;
}

.navbar-nav .nav-link:hover,
.navbar-nav .nav-link.active {
    color: #007cba !important;
}

/* Main container - full width */
.student-dashboard {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 8px 40px 0px;
    background-color: #f5f7fa;
    height: calc(100vh - 70px);
    overflow-x: hidden;
    overflow-y: hidden;
}

/* Welcome section - blue gradient */
.welcome-section {
    background: #CDEBFA;
    border-radius: 20px;
    border-top: 2px solid #1CB0F6;
    border-bottom: 4px solid #1CB0F6;
    border-left: 2px solid #1CB0F6;
    border-right: 2px solid #1CB0F6;
    color: #2d3436;
    padding: 20px 30px;
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: fadeInDown 0.8s ease-out;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 50px;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.welcome-content h1 {
    font-size: 24px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: #2d3436;
}

.welcome-date {
    display: flex;
    align-items: center;
    font-size: 12px;
    color: #1CB0F6;
    margin-bottom: 4px;
}

.welcome-date::before {
    content: "📅";
    margin-right: 6px;
}

.welcome-subtitle {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

/* Character section */
.character-section {
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
}

.speech-bubble {
    background: rgba(255,255,255,0.8);
    border-radius: 15px;
    padding: 12px 16px;
    font-size: 13px;
    position: relative;
    color: #2d3436;
    border: 1px solid rgba(255,255,255,0.9);
}

.speech-bubble::after {
    content: "";
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%);
    border: 8px solid transparent;
    border-left-color: rgba(255,255,255,0.8);
}

.character-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #ff7675;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    border: 2px solid rgba(255,255,255,0.2);
}

/* Main content area - with sidebar layout */
.dashboard-content {
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    display: flex;
    gap: 20px;
    padding-bottom: 40px;
}

.main-content {
    flex: 1;
    min-width: 0;
}

.sidebar {
    width: 240px;
    flex-shrink: 0;
}

/* Assessment tabs */
.assessment-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
}

.tab-button {
    background: #e9ecef;
    color: #6c757d;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tab-button.active {
    background: #1CB0F6;
    color: white;
}

.tab-button:hover {
    background: #1CB0F6;
    color: white;
}

/* Tab content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Assessment cards container */
.assessment-cards {
    display: flex;
    gap: 10px;
    margin-bottom: 6px;
    position: relative;
    animation: slideInUp 0.6s ease-out;
    padding-right: 50px; /* Space for navigation arrow */
}

/* Past assessment cards container - specific styling */
#content-past .assessment-cards {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: flex-start;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.assessment-card {
    background: white;
    border-radius: 12px;
    border-right: 4px solid #1CB0F6;
    padding: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    flex: 1;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
}

.assessment-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Past assessment card styles */
.past-assessment-card {
    background: white;
    border-radius: 12px;
    border-right: 4px solid #1CB0F6;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    width: 400px;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
}

.past-assessment-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.score-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.score-number {
    font-size: 18px;
    font-weight: bold;
    color: #0984e3;
}

.score-label {
    font-size: 12px;
    color: #666;
}

.completion-date {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.btn-view-report {
    background: #58CC02;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
    width: auto;
    text-align: center;
    float: right;
}

.btn-view-report:hover {
    background: #4fb802;
    color: white;
    text-decoration: none;
}

.assessment-timer {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #f8f9fa;
    border-radius: 15px;
    padding: 4px 10px;
    font-size: 12px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 4px;
}

.assessment-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: #2d3436;
    padding-right: 80px;
}

.assessment-description {
    color: #636e72;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 20px;
}

/* Buttons */
.btn-start {
    background: #58CC02;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
}

.btn-start:hover {
    background: #4fb802;
    color: white;
    text-decoration: none;
}

.btn-locked {
    background: #636e72;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 600;
    cursor: not-allowed;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Locked assessment cards should have grey right border */
.assessment-card:has(.btn-locked) {
    border-right-color: #636e72;
}

.assessment-card.locked {
    border-right-color: #636e72;
}

/* Arrow navigation */
.nav-arrow {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: #0984e3;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
    animation: pulse 2s infinite;
}

.nav-arrow:hover {
    background: #0770d0;
    transform: translateY(-50%) scale(1.1);
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(9, 132, 227, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(9, 132, 227, 0); }
    100% { box-shadow: 0 0 0 0 rgba(9, 132, 227, 0); }
}

/* Calendar widget - smaller and positioned in sidebar */
.calendar-widget {
    background: white;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    animation: slideInRight 0.7s ease-out;
    width: 100%;
    margin-bottom: 20px;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.calendar-header {
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
    color: #2d3436;
    font-size: 16px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    text-align: center;
}

.calendar-day {
    padding: 8px 4px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
}

.calendar-day.header {
    font-weight: 600;
    color: #636e72;
    cursor: default;
}

.calendar-day.today {
    background: #0984e3;
    color: white;
    font-weight: 600;
}

.calendar-day.other-month {
    color: #ddd;
}

.calendar-day:hover:not(.header):not(.today) {
    background: #f1f3f4;
}

.calendar-day.selected {
    background: #74b9ff;
    color: white;
    font-weight: 600;
}

/* Add loading animation */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Dynamic content animations */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Assessment Instructions */
.instructions-section {
    margin-top: 50px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.instructions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    border: none;
    padding: 8px 20px;
    cursor: pointer;
    transition: background 0.3s;
}

.instructions-header:hover {
    background: #f8f9fa;
}

.instructions-title {
    color: #1CB0F6;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.instructions-arrow {
    color: #636e72;
    font-size: 18px;
    transition: transform 0.3s;
}

.instructions-arrow.expanded {
    transform: rotate(180deg);
}

.instructions-content {
    display: none;
    padding: 4px 20px 4px;
    border-top: 1px solid #e9ecef;
}

.instructions-content.active {
    display: block;
}

.instruction-item {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    font-size: 13px;
    color: #2d3436;
    line-height: 1.2;
}

.instruction-icon {
    font-size: 13px;
    width: 14px;
}

.take-assessment-btn {
    background: #58CC02;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
    margin-top: 4px;
    font-size: 13px;
    width: auto;
    text-align: center;
    float: right;
}

.take-assessment-btn:hover {
    background: #4fb802;
    color: white;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 1200px) {
    .student-dashboard {
        padding: 20px 25px;
    }
    
    .dashboard-content {
        max-width: none;
        gap: 20px;
    }
    
    .sidebar {
        width: 220px;
    }
}

@media (max-width: 768px) {
    .student-dashboard {
        padding: 15px 20px;
    }
    
    .dashboard-content {
        flex-direction: column;
        gap: 20px;
    }
    
    .sidebar {
        width: 100%;
    }
    
    .assessment-cards {
        flex-direction: column;
        padding-right: 0;
    }
    
    .nav-arrow {
        display: none;
    }
    
    .welcome-section {
        padding: 20px 25px;
    }
}
';
echo html_writer::end_tag('style');

// Main dashboard container
echo html_writer::start_div('student-dashboard');

// Welcome Section
echo html_writer::start_div('welcome-section');

echo html_writer::start_div('welcome-content');
echo html_writer::tag('h1', 'Welcome Back, ' . $USER->firstname);
echo html_writer::tag('div', date('D, d F Y'), ['class' => 'welcome-date']);
echo html_writer::tag('p', 'Continue your learning journey and take your upcoming assessments', ['class' => 'welcome-subtitle']);
echo html_writer::end_div();

echo html_writer::start_div('character-section');
echo html_writer::div('Good to see you back, ' . $USER->firstname . '. Ready to learn?', 'speech-bubble');
echo html_writer::div('🧑‍🎓', 'character-avatar');
echo html_writer::end_div();

echo html_writer::end_div(); // End welcome section

// Dashboard content
echo html_writer::start_div('dashboard-content');

// Main content
echo html_writer::start_div('main-content');

// Assessment tabs
echo html_writer::start_div('assessment-tabs');
echo html_writer::tag('button', 'Upcoming Assessment', ['class' => 'tab-button active', 'onclick' => 'switchTab("upcoming")', 'id' => 'tab-upcoming']);
echo html_writer::tag('button', 'Past Assessment', ['class' => 'tab-button', 'onclick' => 'switchTab("past")', 'id' => 'tab-past']);
echo html_writer::end_div();

// Upcoming Assessment content
echo html_writer::start_div('tab-content active', ['id' => 'content-upcoming']);
echo html_writer::start_div('assessment-cards');

// Java Basics Test (Available)
echo html_writer::start_div('assessment-card');
echo html_writer::start_div('assessment-timer');
echo html_writer::span('⏱️', '');
echo html_writer::span('50m remaining', '');
echo html_writer::end_div();
echo html_writer::div('Java Basics Test', 'assessment-title');
echo html_writer::div('Lorem Ipsum is simply dummy text of the printing and typesetting industry', 'assessment-description');
echo html_writer::link(new moodle_url('/my/courses.php'), 'Start Assessment', ['class' => 'btn-start']);
echo html_writer::end_div();

// Advanced Python Test (Locked)
echo html_writer::start_div('assessment-card locked');
echo html_writer::start_div('assessment-timer');
echo html_writer::span('⏱️', '');
echo html_writer::span('50m remaining', '');
echo html_writer::end_div();
echo html_writer::div('Advanced Python Test', 'assessment-title');
echo html_writer::div('Lorem Ipsum is simply dummy text of the printing and typesetting industry', 'assessment-description');
echo html_writer::span('🔒 Locked Assessment', 'btn-locked');
echo html_writer::end_div();

// React Test (Locked)
echo html_writer::start_div('assessment-card locked');
echo html_writer::start_div('assessment-timer');
echo html_writer::span('⏱️', '');
echo html_writer::span('50m remaining', '');
echo html_writer::end_div();
echo html_writer::div('React Test', 'assessment-title');
echo html_writer::div('Lorem Ipsum is simply dummy text of the printing and typesetting industry', 'assessment-description');
echo html_writer::span('🔒 Locked Assessment', 'btn-locked');
echo html_writer::end_div();

// Navigation arrow
echo html_writer::div('→', 'nav-arrow');

echo html_writer::end_div(); // End assessment cards
echo html_writer::end_div(); // End upcoming content

// Past Assessment content
echo html_writer::start_div('tab-content', ['id' => 'content-past']);
echo html_writer::start_div('assessment-cards');

// Past assessment cards
echo html_writer::start_div('past-assessment-card');
echo html_writer::start_div('score-badge');
echo html_writer::span('📊', '');
echo html_writer::span('18/20', 'score-number');
echo html_writer::span('Score', 'score-label');
echo html_writer::end_div();
echo html_writer::div('Java Basics Test', 'assessment-title');
echo html_writer::div('Completed on: 20 July 2025', 'completion-date');
echo html_writer::link('#', 'View Report', ['class' => 'btn-view-report']);
echo html_writer::end_div();

echo html_writer::start_div('past-assessment-card');
echo html_writer::start_div('score-badge');
echo html_writer::span('📊', '');
echo html_writer::span('18/20', 'score-number');
echo html_writer::span('Score', 'score-label');
echo html_writer::end_div();
echo html_writer::div('Java Basics Test', 'assessment-title');
echo html_writer::div('Completed on: 20 July 2025', 'completion-date');
echo html_writer::link('#', 'View Report', ['class' => 'btn-view-report']);
echo html_writer::end_div();

// Navigation arrow for past assessments
echo html_writer::div('→', 'nav-arrow');

echo html_writer::end_div(); // End past assessment cards
echo html_writer::end_div(); // End past content

// Assessment Instructions section
echo html_writer::start_div('instructions-section');
echo html_writer::start_div('instructions-header', ['onclick' => 'toggleInstructions()']);
echo html_writer::tag('h3', 'Assessment Instruction', ['class' => 'instructions-title']);
echo html_writer::span('∧', 'instructions-arrow', ['id' => 'instructions-arrow']);
echo html_writer::end_div();

// Instructions content (hidden by default)
echo html_writer::start_div('instructions-content active', ['id' => 'instructions-content']);

echo html_writer::start_div('instruction-item');
echo html_writer::span('📝', 'instruction-icon');
echo html_writer::span('Total Questions: 22');
echo html_writer::end_div();

echo html_writer::start_div('instruction-item');
echo html_writer::span('⏱️', 'instruction-icon');
echo html_writer::span('Duration: 50 minutes');
echo html_writer::end_div();

echo html_writer::start_div('instruction-item');
echo html_writer::span('✅', 'instruction-icon');
echo html_writer::span('Attempts Allowed: 1');
echo html_writer::end_div();

echo html_writer::start_div('instruction-item');
echo html_writer::span('⚡', 'instruction-icon');
echo html_writer::span('Auto-submit: Test will end automatically when time is up');
echo html_writer::end_div();

echo html_writer::start_div('instruction-item');
echo html_writer::span('🚫', 'instruction-icon');
echo html_writer::span('Rules: No external materials allowed');
echo html_writer::end_div();

// Take Assessment button
echo html_writer::link(new moodle_url('/my/courses.php'), 'Take Assessment', ['class' => 'take-assessment-btn']);

echo html_writer::end_div(); // End instructions content
echo html_writer::end_div(); // End instructions section

echo html_writer::end_div(); // End main content

// Sidebar
echo html_writer::start_div('sidebar');

// Calendar widget
echo html_writer::start_div('calendar-widget');
echo html_writer::div('September', 'calendar-header');

// Generate calendar
$currentMonth = 9; // September
$currentYear = 2025;
$daysInMonth = 30;
$firstDay = 0; // Sunday

echo html_writer::start_div('calendar-grid');

// Days of week headers
$daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
foreach ($daysOfWeek as $day) {
    echo html_writer::div($day, 'calendar-day header');
}

// Empty cells for days before month starts
for ($i = 0; $i < $firstDay; $i++) {
    echo html_writer::div('', 'calendar-day');
}

// Days of current month
$today = (int)date('j'); // Get current day of month
for ($day = 1; $day <= $daysInMonth; $day++) {
    $class = 'calendar-day';
    if ($day == $today) { // Highlight today's date
        $class .= ' today';
    }
    echo html_writer::div($day, $class);
}

echo html_writer::end_div(); // End calendar grid
echo html_writer::end_div(); // End calendar widget
echo html_writer::end_div(); // End sidebar

echo html_writer::end_div(); // End dashboard content
echo html_writer::end_div(); // End main container

// Add JavaScript for dynamic interactions
echo html_writer::start_tag('script');
echo '
document.addEventListener("DOMContentLoaded", function() {
    // Add click handlers for assessment cards
    const cards = document.querySelectorAll(".assessment-card");
    cards.forEach(function(card, index) {
        card.addEventListener("click", function() {
            if (index === 0) {
                // First card is clickable
                window.location.href = "/moodle42/moodle/my/courses.php";
            } else {
                // Show locked message
                alert("This assessment is locked. Complete the previous assessments first!");
            }
        });
    });
    
    // Add hover effect to calendar days
    const calendarDays = document.querySelectorAll(".calendar-day:not(.header)");
    calendarDays.forEach(function(day) {
        day.addEventListener("click", function() {
            if (!day.classList.contains("today")) {
                // Remove previous selection
                document.querySelectorAll(".calendar-day.selected").forEach(function(selected) {
                    selected.classList.remove("selected");
                });
                // Add selection to clicked day
                day.classList.add("selected");
            }
        });
    });
    
    // Instructions functionality handled by toggleInstructions function
    
    // Add navigation arrow functionality
    const navArrows = document.querySelectorAll(".nav-arrow");
    navArrows.forEach(function(navArrow) {
        navArrow.addEventListener("click", function() {
            alert("Next set of assessments would load here!");
        });
    });
    
    // Update time every minute for timers
    setInterval(function() {
        const timers = document.querySelectorAll(".assessment-timer span:last-child");
        timers.forEach(function(timer) {
            // Could update real countdown here
            console.log("Timer update: " + timer.textContent);
        });
    }, 60000);
});

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    const contents = document.querySelectorAll(".tab-content");
    contents.forEach(function(content) {
        content.classList.remove("active");
    });
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll(".tab-button");
    tabs.forEach(function(tab) {
        tab.classList.remove("active");
    });
    
    // Show selected tab content
    document.getElementById("content-" + tabName).classList.add("active");
    
    // Add active class to selected tab
    document.getElementById("tab-" + tabName).classList.add("active");
    
    // Show/hide assessment instruction based on tab
    const instructionSection = document.querySelector(".instructions-section");
    if (instructionSection) {
        if (tabName === "upcoming") {
            instructionSection.style.display = "block";
        } else {
            instructionSection.style.display = "none";
        }
    }
}

// Toggle instructions functionality
function toggleInstructions() {
    const content = document.getElementById("instructions-content");
    const arrow = document.getElementById("instructions-arrow");
    
    if (content.classList.contains("active")) {
        content.classList.remove("active");
        arrow.textContent = "⌄";
        arrow.classList.remove("expanded");
    } else {
        content.classList.add("active");
        arrow.textContent = "∧";
        arrow.classList.add("expanded");
    }
}

';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();