<?php
// local/orgadmin/org_admin_dashboard.php - Organization Admin Dashboard

require_once('../../config.php');
require_once('./role_detector.php');

// Require login
require_login();

// Check if user should see organization admin dashboard
if (!orgadmin_role_detector::should_show_org_admin_dashboard()) {
    redirect(new moodle_url('/my/index.php'));
}

// Get parameters for search and pagination
$search = optional_param('search', '', PARAM_TEXT);
$role_filter = optional_param('role', 'all', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 10;

// Get real data from Moodle system
$statistics = orgadmin_role_detector::get_org_admin_statistics();
$users_data = orgadmin_role_detector::get_org_admin_users($page, $perpage, $search, $role_filter);

// Set up page
$PAGE->set_url('/local/orgadmin/org_admin_dashboard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Dashboard');
$PAGE->set_heading('');
// Clean navbar - no additional breadcrumbs needed

// Start output
echo $OUTPUT->header();
?>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

body {
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.admin-container {
    max-width: 1400px;
    margin: -60px auto 0;
    padding: 20px;
    min-height: 100vh;
    overflow-y: auto;
}

.admin-welcome {
    background: linear-gradient(135deg, #CDEBFA 0%, #A8DCFA 100%);
    border-radius: 16px;
    padding: 30px;
    color: #2d3748;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    border: 3px solid #1CB0F6;
}

.admin-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.admin-welcome-content h1 {
    margin: 0 0 10px 0;
    font-size: 2em;
    font-weight: 700;
}

.admin-welcome-date {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    opacity: 0.9;
}

.admin-welcome-date .material-icons {
    margin-right: 8px;
    font-size: 18px;
}

.admin-welcome-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1em;
}

.admin-welcome-character {
    display: none;
}

.admin-character {
    width: 100px;
    height: 100px;
    margin-right: 20px;
}

.admin-speech-bubble {
    background: white;
    color: #333;
    padding: 15px;
    border-radius: 20px;
    position: relative;
    max-width: 200px;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.admin-speech-bubble::before {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 20px;
    width: 0;
    height: 0;
    border-left: 10px solid transparent;
    border-right: 10px solid transparent;
    border-top: 10px solid white;
}

/* Add User Button CSS Removed */

.admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.admin-stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.admin-stat-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    flex: 1;
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

.admin-stat-card.stakeholders .admin-stat-icon {
    background: #fdcb6e;
}

.admin-stat-card.lnd .admin-stat-icon {
    background: #a29bfe;
}

.admin-stat-title {
    font-size: 12px;
    color: #718096;
    margin: 0 0 4px 0;
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
    color: #2d3436;
}

.admin-stat-card.trainers .admin-stat-value {
    color: #00b894;
}

.admin-stat-card.stakeholders .admin-stat-value {
    color: #e17055;
}

.admin-stat-card.lnd .admin-stat-value {
    color: #a29bfe;
}

.admin-directory {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.admin-directory-header {
    padding: 30px 30px 20px;
}

.admin-directory-title {
    font-size: 1.5em;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #2d3748;
}

.admin-directory-controls {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.admin-search {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.admin-search input {
    width: 100%;
    padding: 12px 40px 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: #f7fafc;
}

.admin-search .material-icons {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
    font-size: 20px;
}

.admin-filter select {
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 14px;
    color: #4a5568;
    min-width: 150px;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table thead {
    background: #f7fafc;
}

.admin-table th,
.admin-table td {
    text-align: left;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.admin-table th {
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
}

.admin-table-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-table-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

/* Avatar colors will be assigned dynamically via inline styles */

.admin-table-role {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
}

.admin-table-role.trainer {
    background: #ffeaa7;
    color: #2d3436;
    font-weight: 600;
}

.admin-table-role.stakeholder {
    background: #81ecec;
    color: #2d3436;
    font-weight: 600;
}

.admin-table-role.lnd {
    background: #fd79a8;
    color: #2d3436;
    font-weight: 600;
}

.admin-table-role.student {
    background: #74b9ff;
    color: #2d3436;
    font-weight: 600;
}

.admin-table-actions {
    display: flex;
    gap: 8px;
}

.admin-table-action {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s;
}

.admin-table-action.edit {
    background: #ebf8ff;
    color: #3182ce;
}

.admin-table-action.edit:hover {
    background: #bee3f8;
}

.admin-table-action.delete {
    background: #fed7d7;
    color: #e53e3e;
}

.admin-table-action.delete:hover {
    background: #feb2b2;
}

.admin-pagination {
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f7fafc;
}

.admin-pagination-info {
    color: #718096;
    font-size: 14px;
}

.admin-pagination-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}

.admin-pagination-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
    color: #4a5568;
    transition: all 0.2s;
}

.admin-pagination-btn:hover {
    background: #f7fafc;
    text-decoration: none;
    color: #2d3748;
}

.admin-pagination-btn.active {
    background: #3182ce;
    border-color: #3182ce;
    color: white;
}

.admin-pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<div class="admin-container">
    <!-- Welcome Banner -->
    <div class="admin-welcome">
    <div class="admin-welcome-content" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="flex: 1; text-align: left;">
                <h1>Welcome Back, <?php echo fullname($USER); ?></h1>
                <div class="admin-welcome-date">
                    <span class="material-icons">calendar_today</span>
                    <?php echo date('D, j M Y'); ?>
                </div>
                <p class="admin-welcome-subtitle">Manage your organization users and track their progress</p>
            </div>
            <div style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); display: flex; align-items: center; gap: 16px;">
                <img src="Gray and Blue Gradient Man 3D Avatar.png" alt="Org Admin Avatar" style="height: 100px; width: 100px; object-fit: contain;">
                <div class="admin-speech-bubble">Good to see you back, <?php echo $USER->firstname; ?>!<br>Ready to learn?</div>
            </div>
        </div>
        <div class="admin-welcome-character">
            <div style="display: flex; align-items: center; justify-content: flex-end; height: 100%;">
                <img src="Gray and Blue Gradient Man 3D Avatar.png" alt="Org Admin Avatar" style="height: 100px; width: 100px; object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Add User Button Removed -->

    <!-- Statistics Cards -->
    <div class="admin-stats">
        <div class="admin-stat-card total-users">
            <div class="admin-stat-content">
                <div class="admin-stat-title">Total Students</div>
                <h2 class="admin-stat-value"><?php echo number_format($statistics['students']); ?></h2>
            </div>
            <div class="admin-stat-icon">
                <span class="material-icons">group</span>
            </div>
        </div>
        <div class="admin-stat-card trainers">
            <div class="admin-stat-content">
                <div class="admin-stat-title">Trainers</div>
                <h2 class="admin-stat-value"><?php echo number_format($statistics['trainers']); ?></h2>
            </div>
            <div class="admin-stat-icon">
                <span class="material-icons">school</span>
            </div>
        </div>
        <div class="admin-stat-card stakeholders">
            <div class="admin-stat-content">
                <div class="admin-stat-title">Stakeholder</div>
                <h2 class="admin-stat-value"><?php echo number_format($statistics['stakeholders']); ?></h2>
            </div>
            <div class="admin-stat-icon">
                <span class="material-icons">business</span>
            </div>
        </div>
        <div class="admin-stat-card lnd">
            <div class="admin-stat-content">
                <div class="admin-stat-title">L&D</div>
                <h2 class="admin-stat-value"><?php echo number_format($statistics['lnd']); ?></h2>
            </div>
            <div class="admin-stat-icon">
                <span class="material-icons">edit</span>
            </div>
        </div>
    </div>

    <!-- User Directory -->
    <div class="admin-directory">
        <div class="admin-directory-header">
            <h2 class="admin-directory-title">User Directory</h2>
            <div class="admin-directory-controls">
                <div class="admin-search">
                    <input type="text" id="userSearch" placeholder="Search Users..." value="<?php echo htmlspecialchars($search); ?>">
                    <span class="material-icons">search</span>
                </div>
                <div class="admin-filter">
                    <?php
                    echo html_writer::start_tag('select', [
                        'id' => 'roleFilter',
                        'name' => 'role_filter',
                        'onchange' => 'filterByRole(this.value)'
                    ]);
                    $role_options = [
                        'all' => 'All Roles',
                        'student' => 'Students',
                        'trainer' => 'Trainers',
                        'stakeholder' => 'Stakeholders',
                        'coursecreator' => 'L&D'
                    ];
                    foreach ($role_options as $value => $label) {
                        $selected = ($value === $role_filter) ? ['selected' => 'selected'] : [];
                        echo html_writer::tag('option', $label, array_merge(['value' => $value], $selected));
                    }
                    echo html_writer::end_tag('select');
                    ?>
                </div>
            </div>
        </div>
        
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $avatar_colors = ['#e17055', '#74b9ff', '#fdcb6e', '#a29bfe', '#00b894', '#fd79a8', '#81ecec'];
                $color_index = 0;
                foreach ($users_data['users'] as $user): 
                    $avatar_color = $avatar_colors[$color_index % count($avatar_colors)];
                    $color_index++;
                ?>
                <tr>
                    <td>
                        <div class="admin-table-user">
                            <div class="admin-table-avatar" style="background: <?php echo $avatar_color; ?>;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </div>
                    </td>
                    <td>
                        <span class="admin-table-role <?php echo strtolower($user['role']); ?>">
                            <?php echo htmlspecialchars($user['role']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['registered']); ?></td>
                    <td>
                        <div class="admin-table-actions">
                            <button class="admin-table-action edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')" title="Edit User">
                                <span class="material-icons" style="font-size: 16px;">edit</span>
                            </button>
                            <button class="admin-table-action delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')" title="Delete User">
                                <span class="material-icons" style="font-size: 16px;">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <div class="admin-pagination">
            <div class="admin-pagination-info">
                <?php if ($users_data['total_count'] > 0): ?>
                    Showing <?php echo $users_data['current_page'] * $users_data['per_page'] + 1; ?> 
                    to <?php echo min(($users_data['current_page'] + 1) * $users_data['per_page'], $users_data['total_count']); ?> 
                    of <?php echo $users_data['total_count']; ?> results
                <?php else: ?>
                    No results found
                <?php endif; ?>
            </div>
            <?php if ($users_data['total_count'] > 0): ?>
            <div class="admin-pagination-controls">
                <?php if ($users_data['current_page'] > 0): ?>
                    <a href="?page=<?php echo $users_data['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="admin-pagination-btn">
                        <span class="material-icons" style="font-size: 16px;">chevron_left</span>
                    </a>
                <?php else: ?>
                    <span class="admin-pagination-btn disabled">
                        <span class="material-icons" style="font-size: 16px;">chevron_left</span>
                    </span>
                <?php endif; ?>
                
                <?php 
                // Smart pagination - show page numbers even for single page
                $current = $users_data['current_page'];
                $total = $users_data['total_pages'];
                
                if ($total > 1) {
                    $show_range = 2; // Show 2 pages before and after current
                    
                    if ($total <= 7) {
                        // Show all pages if total is small
                        $start = 0;
                        $end = $total - 1;
                    } else {
                        // Calculate smart range
                        $start = max(0, $current - $show_range);
                        $end = min($total - 1, $current + $show_range);
                        
                        // Adjust if we're near the beginning or end
                        if ($current < $show_range + 1) {
                            $end = min($total - 1, 4);
                        }
                        if ($current > $total - $show_range - 2) {
                            $start = max(0, $total - 5);
                        }
                    }
                    
                    // Show first page if not in range
                    if ($start > 0): ?>
                        <a href="?page=0&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                           class="admin-pagination-btn">1</a>
                        <?php if ($start > 1): ?>
                            <span class="admin-pagination-btn disabled">...</span>
                        <?php endif;
                    endif;
                    
                    // Show page range
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                           class="admin-pagination-btn <?php echo ($i === $current) ? 'active' : ''; ?>">
                            <?php echo $i + 1; ?>
                        </a>
                    <?php endfor;
                    
                    // Show last page if not in range
                    if ($end < $total - 1): 
                        if ($end < $total - 2): ?>
                            <span class="admin-pagination-btn disabled">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $total - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                           class="admin-pagination-btn"><?php echo $total; ?></a>
                    <?php endif;
                } else {
                    // Single page - show page 1 as active
                    ?>
                    <span class="admin-pagination-btn active">1</span>
                <?php } ?>
                
                <?php if ($users_data['current_page'] < $users_data['total_pages'] - 1): ?>
                    <a href="?page=<?php echo $users_data['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="admin-pagination-btn">
                        <span class="material-icons" style="font-size: 16px;">chevron_right</span>
                    </a>
                <?php else: ?>
                    <span class="admin-pagination-btn disabled">
                        <span class="material-icons" style="font-size: 16px;">chevron_right</span>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterByRole(role) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('role', role);
    currentUrl.searchParams.set('page', '0');
    window.location.href = currentUrl.toString();
}

document.getElementById('userSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('search', this.value);
        currentUrl.searchParams.set('page', '0');
        window.location.href = currentUrl.toString();
    }
});

function editUser(userId, userName) {
    const baseUrl = '<?php echo $CFG->wwwroot; ?>';
    
    // Show options to user
    const choice = confirm('Edit options for "' + userName + '":\n\n' +
                         'Click OK to view User Profile\n' +
                         'Click Cancel to go to User Management');
    
    if (choice) {
        // Open user profile page
        window.open(baseUrl + '/user/profile.php?id=' + userId, '_blank');
    } else {
        // Try admin user management page
        window.open(baseUrl + '/admin/user.php?id=' + userId, '_blank');
    }
}

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete user "' + userName + '"?\n\nThis action cannot be undone.')) {
        // Redirect to Moodle's user management page where admin can delete users
        const baseUrl = '<?php echo $CFG->wwwroot; ?>';
        const userManagementUrl = baseUrl + '/admin/user.php';
        
        // Open in same tab to maintain session
        window.location.href = userManagementUrl;
    }
}
</script>

<?php
echo $OUTPUT->footer();
?>