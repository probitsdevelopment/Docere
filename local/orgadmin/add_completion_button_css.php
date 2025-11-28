<?php
/**
 * Add circular completion button CSS to Boost theme
 * 
 * Run this once: php add_completion_button_css.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

// Custom CSS for circular completion button
$custom_css = "
/* Circular Checkmark Button for Course Completion */
.path-course-view li.activity form.togglecompletion .btn {
    padding: 0 !important;
    margin: 0 !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background-color: #e0e0e0 !important;
    border: 2px solid #e0e0e0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.3s ease !important;
}

.path-course-view li.activity form.togglecompletion .btn:hover {
    background-color: #d0d0d0 !important;
    border-color: #d0d0d0 !important;
}

.path-course-view li.activity form.togglecompletion img {
    max-width: none !important;
    width: 18px !important;
    height: 18px !important;
    filter: brightness(0) invert(1) !important;
}

.path-course-view li.activity.completioninfo_complete form.togglecompletion .btn {
    background-color: #1CB0F6 !important;
    border-color: #1CB0F6 !important;
}

.path-course-view li.activity.completioninfo_complete form.togglecompletion .btn:hover {
    background-color: #1A9BD8 !important;
    border-color: #1A9BD8 !important;
}
";

// Get current custom SCSS
$current_scss = get_config('theme_boost', 'scss');

// Check if our custom CSS is already there
if (strpos($current_scss, 'Circular Checkmark Button for Course Completion') !== false) {
    echo "✓ Circular completion button CSS already exists in theme settings.\n";
} else {
    // Append our custom CSS
    $new_scss = $current_scss . "\n" . $custom_css;
    
    // Save to theme config
    set_config('scss', $new_scss, 'theme_boost');
    
    // Purge theme cache to apply changes
    theme_reset_all_caches();
    
    echo "✓ Circular completion button CSS added successfully!\n";
    echo "✓ Theme cache purged.\n";
    echo "\nRefresh your browser (Ctrl+Shift+R) to see the changes.\n";
    echo "The 'Mark as done' button should now appear as:\n";
    echo "  - Grey circle (32x32px) when unchecked\n";
    echo "  - Blue circle (#1CB0F6) when checked\n";
    echo "  - White checkmark icon inside\n";
}
