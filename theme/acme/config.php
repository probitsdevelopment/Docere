
<?php
defined('MOODLE_INTERNAL') || die();
$THEME->name = 'acme';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->scss = function($theme) {
    return theme_acme_get_main_scss_content($theme);
};

$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
