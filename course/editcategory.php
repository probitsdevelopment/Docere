<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page for creating or editing course category name/parent/description.
 *
 * When called with an id parameter, edits the category with that id.
 * Otherwise it creates a new category with default parent from the parent
 * parameter, which may be 0.
 *
 * @package    core_course
 * @copyright  2007 Nicolas Connault
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot . '/course/lib.php');


require_login();

$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/course/editcategory.php');

if ($id) {
    // ===== Edit existing category =====
    $coursecat = core_course_category::get($id, MUST_EXIST, true);
    $category = $coursecat->get_db_record();
    $context = context_coursecat::instance($id);

    navigation_node::override_active_url(new moodle_url('/course/index.php', ['categoryid' => $category->id]));
    $PAGE->navbar->add(get_string('settings'));
    $PAGE->set_primary_active_tab('home');
    $PAGE->set_secondary_active_tab('edit');

    $url->param('id', $id);
    $strtitle = new lang_string('editcategorysettings');
    $itemid = 0; // Files in category description use itemid 0.
    $title = $strtitle;
    $fullname = $coursecat->get_formatted_name();

} else {
    // ===== Create new category =====
    $parent = required_param('parent', PARAM_INT);
    $url->param('parent', $parent);
    $strtitle = get_string('addnewcategory');

    if ($parent) {
        $parentcategory = $DB->get_record('course_categories', ['id' => $parent], '*', MUST_EXIST);
        $context = context_coursecat::instance($parent);
        navigation_node::override_active_url(new moodle_url('/course/index.php', ['categoryid' => $parent]));
        $fullname = format_string($parentcategory->name, true, ['context' => $context]);
        $title = "$fullname: $strtitle";

        $managementurl = new moodle_url('/course/management.php');
        $managementcaps = ['moodle/category:manage', 'moodle/course:create'];
        if (!has_any_capability($managementcaps, context_system::instance())) {
            $managementurl->param('categoryid', $parent);
        }
        $PAGE->set_primary_active_tab('home');
        $PAGE->navbar->add(get_string('coursemgmt', 'admin'), $managementurl);
        $PAGE->navbar->add(get_string('addcategory', 'admin'));
    } else {
        // Top-level new category.
        $context = context_system::instance();
        $fullname = $SITE->fullname;
        $title = $strtitle;
        $PAGE->set_secondary_active_tab('courses');
    }

    $category = new stdClass();
    $category->id = 0;
    $category->parent = $parent;
    $itemid = null; // Prevent loading files from parent into draft.
}

require_capability('moodle/category:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($fullname);

// Build the form.
$mform = new core_course_editcategory_form(null, [
    'categoryid' => $id,
    'parent'     => $category->parent,
    'context'    => $context,
    'itemid'     => $itemid
]);

/**
 * ---- PREPARE DRAFT AREA FOR ORG LOGO (local_orgbranding/orglogo) ----
 * IMPORTANT:
 *  - When editing (id > 0): use the category context + final filearea.
 *  - When creating (id = 0): use system context + TEMP filearea; move on save.
 */
$draftitemid = file_get_submitted_draft_itemid('orglogo_draft');

if (!empty($category->id)) {
    // Editing an existing category.
    $draftctx = context_coursecat::instance($category->id);
    file_prepare_draft_area(
        $draftitemid,
        $draftctx->id,
        'local_orgbranding',
        'orglogo',           // final area
        $category->id,       // itemid = category id
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
    );
} else {
    // Creating a new category (no category id yet).
    $draftctx = context_system::instance();
    file_prepare_draft_area(
        $draftitemid,
        $draftctx->id,
        'local_orgbranding',
        'orglogo_tmp',       // temporary area while id does not exist
        $USER->id,           // stable temporary itemid
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
    );
}

$category->orglogo_draft = $draftitemid;

// Prepare description editor data (core behavior stays unchanged).
$category = file_prepare_standard_editor(
    $category,
    'description',
    $mform->get_description_editor_options(),
    $context,  // ✅ use the SAME context you used to build the form/options
    'coursecat',
    'description',
    $itemid
);

$mform->set_data($category);

// Management URL base.
$manageurl = new moodle_url('/course/management.php');

// Handle form events.
if ($mform->is_cancelled()) {
    if (!empty($id)) {
        $manageurl->param('categoryid', $id);
    } else if (!empty($parent)) {
        $manageurl->param('categoryid', $parent);
    }
    redirect($manageurl);

} else if ($data = $mform->get_data()) {

    // 1) Persist category first (so we have a real id).
    if (!empty($coursecat)) {
        if ((int)$data->parent !== (int)$coursecat->parent && !$coursecat->can_change_parent($data->parent)) {
            throw new moodle_exception('cannotmovecategory');
        }
        $coursecat->update($data, $mform->get_description_editor_options());
        $catid = $coursecat->id;
    } else {
        $newcat = core_course_category::create($data, $mform->get_description_editor_options());
        $catid = $newcat->id;
    }

    // 2) Save/move the org logo from draft to the FINAL area under the category context.
    if (!empty($data->orglogo_draft)) {
        $catctx = context_coursecat::instance($catid);
        file_save_draft_area_files(
            $data->orglogo_draft,
            $catctx->id,
            'local_orgbranding',   // component
            'orglogo',             // final filearea
            $catid,                // itemid = category id
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
    }

    $manageurl->param('categoryid', $catid);
    redirect($manageurl);
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading($strtitle);
$mform->display();
echo $OUTPUT->footer();
