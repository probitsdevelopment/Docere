<?php
defined('MOODLE_INTERNAL') || die();

function theme_acme_get_main_scss_content(\theme_config $theme): string {
    $scss = '';

    // Load Boost’s SCSS via its function (not a method).
    $boost = theme_config::load('boost');
    if (function_exists('theme_boost_get_main_scss_content')) {
        $scss .= theme_boost_get_main_scss_content($boost);
    }

    // Append your overrides.
    $custom = __DIR__ . '/scss/custom.scss';
    if (is_readable($custom)) {
        $scss .= "\n" . file_get_contents($custom);
    }
    return $scss;
}
