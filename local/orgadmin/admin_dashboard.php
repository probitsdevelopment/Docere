<?php
// local/orgadmin/admin_dashboard.php - Admin Dashboard (Exact Screenshot Match)
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/admin_dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('Admin Dashboard');
$PAGE->set_heading('');

// Verify user should access admin dashboard
if (!orgadmin_role_detector::should_show_admin_dashboard()) {
    // Redirect non-admin users to appropriate dashboard
    $dashboardurl = orgadmin_role_detector::get_dashboard_url();
    redirect($dashboardurl);
}

// Get parameters for search and pagination
$search = optional_param('search', '', PARAM_TEXT);
$role_filter = optional_param('role', 'all', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 10;

// Get real data from Moodle system
$statistics = orgadmin_role_detector::get_admin_statistics();
$user_data = orgadmin_role_detector::get_admin_users($page, $perpage, $search, $role_filter);

echo $OUTPUT->header();

// Include Google Material Icons
echo html_writer::empty_tag('link', [
    'rel' => 'stylesheet',
    'href' => 'https://fonts.googleapis.com/icon?family=Material+Icons'
]);

// Get current user's name for welcome message
$username = $USER->firstname ?: 'Admin';

// Custom CSS for Admin Dashboard (Exact Screenshot Match)
echo html_writer::start_tag('style');
echo '
/* Reset and base styles */
html, body {
    background-color: #ffffff !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0 !important;
    padding: 0 !important;
}

/* Override Moodle container constraints */
#page-wrapper, #page, #page-content, .container-fluid, #region-main-box, #region-main, .row {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Hide default page headings */
.page-header-headings {
    display: none !important;
}

/* Main Container */
.admin-container {
    width: 100%;
    padding: 20px 30px;
    background: #ffffff;
    min-height: calc(100vh - 70px);
}

/* Welcome Banner - Exact Screenshot Match */
.admin-welcome-banner {
    background: linear-gradient(135deg, #CDEBFA 0%, #A8DCFA 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 20px;
    position: relative;
    height: 156px !important;
    min-height: 156px !important;
    max-height: 156px !important;
    overflow: hidden;
    border: 3px solid #149EDF;
}

.admin-welcome-content {
    position: relative;
    z-index: 2;
}

.admin-welcome-title {
    font-size: 2.2em;
    font-weight: 700;
    color: #1a365d;
    margin: 0 0 10px 0;
}

.admin-welcome-date {
    color: #2d3748;
    font-size: 1em;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-welcome-subtitle {
    color: #2d3748;
    font-size: 1.1em;
    margin: 0;
}

/* Character and Speech Bubble */
// .admin-character {
//     position: absolute;
//     right: 150px;
//     bottom: -20px;
//     width: 120px;
//     height: 140px;
//     background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 120 140\'%3E%3Cg%3E%3Ccircle cx=\'60\' cy=\'45\' r=\'35\' fill=\'%23f4b99d\'/%3E%3Ccircle cx=\'50\' cy=\'40\' r=\'3\' fill=\'%23333\'/%3E%3Ccircle cx=\'70\' cy=\'40\' r=\'3\' fill=\'%23333\'/%3E%3Cpath d=\'M45 50 Q60 60 75 50\' stroke=\'%23333\' stroke-width=\'2\' fill=\'none\'/%3E%3Crect x=\'35\' y=\'75\' width=\'50\' height=\'60\' rx=\'5\' fill=\'%234a90e2\'/%3E%3Cpath d=\'M30 20 Q15 5 35 10 Q55 15 45 30\' fill=\'%23d4931a\'/%3E%3Cpath d=\'M90 20 Q105 5 85 10 Q65 15 75 30\' fill=\'%23d4931a\'/%3E%3C/g%3E%3C/svg%3E") no-repeat center center;
//     background-size: contain;
// }

.admin-speech-bubble {
    position: absolute;
    right: 280px;
    top: 20px;
    background: white;
    border-radius: 15px;
    padding: 15px 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    font-size: 13px;
    color: #2d3748;
    max-width: 180px;
    z-index: 3;
    border: 1px solid #e2e8f0;
}

.admin-speech-bubble::after {
    content: "";
    position: absolute;
    right: -8px;
    top: 25px;
    width: 0;
    height: 0;
    border-left: 8px solid white;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
}

/* Action Buttons - Below Banner */
.admin-action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-bottom: 25px;
}

.admin-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.admin-btn.add-user {
    background: #58CC02;
    color: white;
}

.admin-btn.add-org {
    background: #1CB0F6;
    color: white;
}

/* Statistics Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.admin-stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    position: relative;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.admin-stat-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
}

.admin-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.admin-stat-icon .material-icons {
    font-size: 20px;
    color: white;
}

.admin-stat-card.total-users .admin-stat-icon {
    background: #1CB0F6;
}

.admin-stat-card.trainers .admin-stat-icon {
    background: #58CC02;
}

.admin-stat-card.organizations .admin-stat-icon {
    background: #f6ad55;
}

.admin-stat-card.lnd .admin-stat-icon {
    background: #9f7aea;
}

.admin-stat-title {
    font-size: 12px;
    color: #718096;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-stat-value {
    font-size: 2em;
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.admin-stat-card.total-users .admin-stat-value {
    color: #1a202c;
}

.admin-stat-card.trainers .admin-stat-value {
    color: #58CC02;
}

.admin-stat-card.organizations .admin-stat-value {
    color: #f6ad55;
}

.admin-stat-card.lnd .admin-stat-value {
    color: #9f7aea;
}

/* User Directory */
.admin-user-directory {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    max-height: 500px;
}

.admin-user-table-container {
    max-height: 350px;
    overflow-y: auto;
}

.admin-directory-header {
    padding: 25px 30px 20px 30px;
    border-bottom: 1px solid #e2e8f0;
}

.admin-directory-title {
    font-size: 1.5em;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 20px 0;
}

.admin-directory-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.admin-search-container {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.admin-search-input {
    width: 100%;
    padding: 12px 16px 12px 45px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #f9fafb;
}

.admin-search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 20px;
}

.admin-role-filter {
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

/* User Table */
.admin-user-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-user-table th {
    background: #f8fafc;
    padding: 15px 20px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
}

.admin-user-table td {
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.admin-user-table tbody tr:hover {
    background: #f8fafc;
}

.admin-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: #e5e7eb;
}

.admin-user-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.admin-role-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
}

.admin-role-badge.trainer {
    background: #fbb6ce;
    color: #97266d;
}

.admin-role-badge.student {
    background: #dbeafe;
    color: #1e40af;
}

.admin-role-badge.lnd {
    background: #c7d2fe;
    color: #4338ca;
}

.admin-role-badge.manager {
    background: #fed7aa;
    color: #c2410c;
}

.admin-role-badge.user {
    background: #f3f4f6;
    color: #374151;
}

.admin-role-badge.organization {
    background: #bfdbfe;
    color: #1e40af;
}

.admin-actions {
    display: flex;
    gap: 8px;
}

.admin-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.admin-action-btn.edit {
    background: #f3f4f6;
    color: #6b7280;
}

.admin-action-btn.edit:hover {
    background: #e5e7eb;
}

.admin-action-btn.delete {
    background: #f3f4f6;
    color: #6b7280;
}

.admin-action-btn.delete:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* Pagination */
.admin-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
}

.admin-pagination-info {
    color: #6b7280;
    font-size: 14px;
}

.admin-pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.admin-page-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #374151;
    font-size: 14px;
}

.admin-page-btn:hover {
    background: #1CB0F6;
    color: white;
    border-color: #1CB0F6;
}

.admin-page-btn.active {
    background: #1CB0F6;
    color: white;
    border-color: #1CB0F6;
}
';
echo html_writer::end_tag('style');

// Main Admin Dashboard Container
echo html_writer::start_div('admin-container');

// Welcome Banner
echo html_writer::start_div('admin-welcome-banner');
echo html_writer::start_div('admin-welcome-content');
echo html_writer::tag('h1', "Welcome Back, $username", ['class' => 'admin-welcome-title']);
echo html_writer::start_div('admin-welcome-date');
echo html_writer::tag('i', 'event', ['class' => 'material-icons', 'style' => 'font-size: 18px;']);
echo html_writer::span(date('D, d M Y'));
echo html_writer::end_div();
echo html_writer::tag('p', 'Continue your learning journey and take your upcoming assessments', ['class' => 'admin-welcome-subtitle']);
echo html_writer::end_div();

// Character and Speech Bubble
echo html_writer::div('<img src="Gray and Blue Gradient Man 3D Avatar.png" alt="Admin Avatar" style="height: 120px; width: 120px; object-fit: contain; position: absolute; right: 140px; top: 20px;">', 'admin-character');
echo html_writer::div("Good to see you back, $username.<br>Ready to learn?", 'admin-speech-bubble');
echo html_writer::end_div();

// Action Buttons - Below welcome banner
echo html_writer::start_div('admin-action-buttons');
echo html_writer::start_tag('button', ['class' => 'admin-btn add-user', 'onclick' => 'addUser()']);
echo html_writer::tag('i', 'add', ['class' => 'material-icons', 'style' => 'font-size: 18px;']);
echo html_writer::span('Add User');
echo html_writer::end_tag('button');

echo html_writer::start_tag('button', ['class' => 'admin-btn add-org', 'onclick' => 'addOrganization()']);
echo html_writer::span('Add Organization');
echo html_writer::end_tag('button');
echo html_writer::end_div();

// Statistics Grid
echo html_writer::start_div('admin-stats-grid');

// Total Users Card
echo html_writer::start_div('admin-stat-card total-users');
echo html_writer::start_div('admin-stat-content');
echo html_writer::tag('div', 'Total Students', ['class' => 'admin-stat-title']);
echo html_writer::tag('div', number_format($statistics['students']), ['class' => 'admin-stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('admin-stat-icon');
echo html_writer::tag('i', 'group', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Trainers Card
echo html_writer::start_div('admin-stat-card trainers');
echo html_writer::start_div('admin-stat-content');
echo html_writer::tag('div', 'Trainers', ['class' => 'admin-stat-title']);
echo html_writer::tag('div', number_format($statistics['trainers']), ['class' => 'admin-stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('admin-stat-icon');
echo html_writer::tag('i', 'school', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Organizations Card
echo html_writer::start_div('admin-stat-card organizations');
echo html_writer::start_div('admin-stat-content');
echo html_writer::tag('div', 'Organization', ['class' => 'admin-stat-title']);
echo html_writer::tag('div', number_format($statistics['organizations']), ['class' => 'admin-stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('admin-stat-icon');
echo html_writer::tag('i', 'business', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// L&D Card
echo html_writer::start_div('admin-stat-card lnd');
echo html_writer::start_div('admin-stat-content');
echo html_writer::tag('div', 'L&D', ['class' => 'admin-stat-title']);
echo html_writer::tag('div', number_format($statistics['lnd']), ['class' => 'admin-stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('admin-stat-icon');
echo html_writer::tag('i', 'create', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End stats grid

// User Directory
echo html_writer::start_div('admin-user-directory');

// Directory Header
echo html_writer::start_div('admin-directory-header');
echo html_writer::tag('h2', 'User Directory', ['class' => 'admin-directory-title']);

// Directory Controls
echo html_writer::start_div('admin-directory-controls');

// Search Container
echo html_writer::start_div('admin-search-container');
echo html_writer::start_tag('form', ['method' => 'GET', 'style' => 'position: relative;']);
echo html_writer::tag('i', 'search', ['class' => 'material-icons admin-search-icon']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'placeholder' => 'Search Users....',
    'class' => 'admin-search-input',
    'value' => $search
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'role', 'value' => $role_filter]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => '0']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Role Filter
echo html_writer::start_tag('select', [
    'class' => 'admin-role-filter',
    'name' => 'role_filter',
    'onchange' => 'filterByRole(this.value)'
]);
$role_options = [
    'all' => 'All Roles',
    'student' => 'Students',
    'teacher' => 'Trainers',
    'coursecreator' => 'L&D',
    'manager' => 'Managers',
    'organization' => 'Organization'
];
foreach ($role_options as $value => $label) {
    $selected = ($value === $role_filter) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $label, array_merge(['value' => $value], $selected));
}
echo html_writer::end_tag('select');

echo html_writer::end_div(); // End directory controls
echo html_writer::end_div(); // End directory header

// User Table Container (scrollable)
echo html_writer::start_div('admin-user-table-container');
echo html_writer::start_tag('table', ['class' => 'admin-user-table']);

// Table Header
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Name');
echo html_writer::tag('th', 'Role');
echo html_writer::tag('th', 'Email');
echo html_writer::tag('th', 'Registered');
echo html_writer::tag('th', 'Action');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Table Body
echo html_writer::start_tag('tbody');

if (!empty($user_data['users'])) {
    foreach ($user_data['users'] as $user) {
        echo html_writer::start_tag('tr');
        
        // Name column with avatar
        echo html_writer::start_tag('td');
        echo html_writer::start_div('admin-user-info');
        echo html_writer::empty_tag('img', [
            'src' => $user['avatar'],
            'alt' => $user['name'],
            'class' => 'admin-user-avatar'
        ]);
        echo html_writer::span($user['name'], 'admin-user-name');
        echo html_writer::end_div();
        echo html_writer::end_tag('td');
        
        // Role column
        echo html_writer::start_tag('td');
        $role_class = strtolower($user['role']);
        echo html_writer::span($user['role'], "admin-role-badge $role_class");
        echo html_writer::end_tag('td');
        
        // Email column
        echo html_writer::tag('td', $user['email']);
        
        // Registered column
        echo html_writer::tag('td', $user['registered']);
        
        // Actions column
        echo html_writer::start_tag('td');
        echo html_writer::start_div('admin-actions');
        
        echo html_writer::start_tag('button', [
            'class' => 'admin-action-btn edit',
            'onclick' => "editUser({$user['id']})",
            'title' => 'Edit User'
        ]);
        echo html_writer::tag('i', 'edit', ['class' => 'material-icons', 'style' => 'font-size: 16px;']);
        echo html_writer::end_tag('button');
        
        echo html_writer::start_tag('button', [
            'class' => 'admin-action-btn delete',
            'onclick' => "deleteUser({$user['id']}, '{$user['name']}')",
            'title' => 'Delete User'
        ]);
        echo html_writer::tag('i', 'delete', ['class' => 'material-icons', 'style' => 'font-size: 16px;']);
        echo html_writer::end_tag('button');
        
        echo html_writer::end_div();
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
} else {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', 'No users found matching your criteria.', ['colspan' => '5', 'style' => 'text-align: center; padding: 40px; color: #6b7280;']);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div(); // End user table container

// Pagination
if ($user_data['total_pages'] > 1) {
    echo html_writer::start_div('admin-pagination');
    
    // Pagination info
    $start = ($user_data['current_page'] * $user_data['per_page']) + 1;
    $end = min(($user_data['current_page'] + 1) * $user_data['per_page'], $user_data['total_count']);
    echo html_writer::div("Showing $start to $end of {$user_data['total_count']} results", 'admin-pagination-info');
    
    // Pagination controls
    echo html_writer::start_div('admin-pagination-controls');
    
    // Previous button
    $prev_disabled = ($user_data['current_page'] <= 0) ? 'disabled' : '';
    $prev_page = max(0, $user_data['current_page'] - 1);
    echo html_writer::link(
        new moodle_url('/local/orgadmin/admin_dashboard.php', [
            'search' => $search,
            'role' => $role_filter,
            'page' => $prev_page
        ]),
        html_writer::tag('i', 'chevron_left', ['class' => 'material-icons']),
        ['class' => "admin-page-btn $prev_disabled"]
    );
    
    // Page numbers
    $start_page = max(0, $user_data['current_page'] - 2);
    $end_page = min($user_data['total_pages'] - 1, $user_data['current_page'] + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i === $user_data['current_page']) ? 'active' : '';
        echo html_writer::link(
            new moodle_url('/local/orgadmin/admin_dashboard.php', [
                'search' => $search,
                'role' => $role_filter,
                'page' => $i
            ]),
            $i + 1,
            ['class' => "admin-page-btn $active"]
        );
    }
    
    // Next button
    $next_disabled = ($user_data['current_page'] >= $user_data['total_pages'] - 1) ? 'disabled' : '';
    $next_page = min($user_data['total_pages'] - 1, $user_data['current_page'] + 1);
    echo html_writer::link(
        new moodle_url('/local/orgadmin/admin_dashboard.php', [
            'search' => $search,
            'role' => $role_filter,
            'page' => $next_page
        ]),
        html_writer::tag('i', 'chevron_right', ['class' => 'material-icons']),
        ['class' => "admin-page-btn $next_disabled"]
    );
    
    echo html_writer::end_div(); // End pagination controls
    echo html_writer::end_div(); // End pagination
}

echo html_writer::end_div(); // End user directory

echo html_writer::end_div(); // End main container

// JavaScript for Admin Dashboard Functionality
echo html_writer::start_tag('script');
echo '
// Base URL for navigation
var baseURL = "' . $CFG->wwwroot . '";

// Add User Function
function addUser() {
    window.location.href = baseURL + "/user/editadvanced.php?id=-1&returnurl=" + encodeURIComponent(window.location.href);
}

// Add Organization Function
function addOrganization() {
    window.location.href = baseURL + "/course/editcategory.php?parent=0";
}

// Edit User Function
function editUser(userId) {
    window.location.href = baseURL + "/user/editadvanced.php?id=" + userId + "&returnurl=" + encodeURIComponent(window.location.href);
}

// Delete User Function
function deleteUser(userId, userName) {
    if (confirm("Are you sure you want to delete user: " + userName + "?")) {
        // In a real implementation, you would make an AJAX call to delete the user
        alert("User deletion would be processed here");
    }
}

// Filter by Role Function
function filterByRole(role) {
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set("role", role);
    currentUrl.searchParams.set("page", "0");
    window.location.href = currentUrl.toString();
}

// Search functionality
document.addEventListener("DOMContentLoaded", function() {
    var searchForm = document.querySelector("form");
    var searchInput = document.querySelector(".admin-search-input");
    
    if (searchForm && searchInput) {
        searchInput.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                searchForm.submit();
            }
        });
    }
    
    console.log("Admin Dashboard initialized successfully");
});
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
?>