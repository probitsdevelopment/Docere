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
 * Form to edit a users profile
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_user
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Class user_edit_form.
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_edit_form extends moodleform {

    /**
     * Define the form.
     */
    public function definition () {
        global $CFG, $COURSE, $USER;

        $mform = $this->_form;
        $editoroptions = null;
        $filemanageroptions = null;
        $usernotfullysetup = user_not_fully_set_up($USER);

        if (!is_array($this->_customdata)) {
            throw new coding_exception('invalid custom data for user_edit_form');
        }
        $editoroptions = $this->_customdata['editoroptions'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $user = $this->_customdata['user'];
        $userid = $user->id;

        if (empty($user->country)) {
            // We must unset the value here so $CFG->country can be used as default one.
            unset($user->country);
        }


        // Accessibility: "Required" is bad legend text.
        $strgeneral  = get_string('general');
        $strrequired = get_string('required');

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);
        // No additional hidden fields needed here - the form continues with the general section header below

    // General section header
    $mform->addElement('header', 'moodle', $strgeneral);

    // Grouped fields: First/Last name
    $mform->addElement("html", "<div class='custom-flex-row'>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_firstname'>" . get_string('firstname') . "</label>");
    $mform->addElement('text', 'firstname', '', 'maxlength="100" size="30"');
    $mform->addRule('firstname', get_string('missingfirstname', 'core'), 'required', null, 'client');
    $mform->setType('firstname', PARAM_NOTAGS);
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_lastname'>" . get_string('lastname') . "</label>");
    $mform->addElement('text', 'lastname', '', 'maxlength="100" size="30"');
    $mform->addRule('lastname', get_string('missinglastname', 'core'), 'required', null, 'client');
    $mform->setType('lastname', PARAM_NOTAGS);
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "</div>");

    // Grouped fields: Email address / Email visibility
    $mform->addElement("html", "<div class='custom-flex-row'>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_email'>" . get_string('email') . "</label>");
    $mform->addElement('text', 'email', '', 'maxlength="100" size="30"');
    $mform->addRule('email', get_string('required'), 'required', null, 'client');
    $mform->setType('email', PARAM_RAW_TRIMMED);
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_maildisplay'>" . get_string('emaildisplay') . "</label>");
    $choices = array(
        '0' => get_string('emaildisplayno'),
        '1' => get_string('emaildisplayyes'),
        '2' => get_string('emaildisplaycourse')
    );
    $mform->addElement('select', 'maildisplay', '', $choices);
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "</div>");

    // Grouped fields: City/town / Select a country
    $mform->addElement("html", "<div class='custom-flex-row'>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_city'>" . get_string('city') . "</label>");
    $mform->addElement('text', 'city', '', 'maxlength="120" size="21"');
    $mform->setType('city', PARAM_TEXT);
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "<div class='custom-flex-label-input'><label for='id_country'>" . get_string('selectacountry') . "</label>");
    $countries = get_string_manager()->get_list_of_countries();
    $countries = array('' => get_string('selectacountry') . '...') + $countries;
    $mform->addElement('select', 'country', '', $countries);
    if (!empty($CFG->country)) {
        $mform->setDefault('country', core_user::get_property_default('country'));
    }
    $mform->addElement("html", "</div>");
    $mform->addElement("html", "</div>");
        // Add shared fields (picture, description, timezone, etc.) but skip optional fields
        // so the optional section stays hidden while picture and other shared elements are shown.
        useredit_shared_definition($mform, $editoroptions, $filemanageroptions, $user,
            array('firstname', 'lastname', 'email', 'maildisplay', 'city', 'country',
                  'idnumber', 'institution', 'department', 'phone1', 'phone2', 'address'));

        // Remove any remaining optional fields if present.
        foreach (['department', 'phone1', 'phone2', 'address'] as $field) {
            if ($mform->elementExists($field)) {
                $mform->removeElement($field);
            }
        }

        // Remove/hide the optional section header if it is present.
        if ($mform->elementExists('moodle_optional')) {
            $mform->removeElement('moodle_optional');
        }

        // Extra settings.
        if (!empty($CFG->disableuserimages) || $usernotfullysetup) {
            $mform->removeElement('deletepicture');
            $mform->removeElement('imagefile');
            $mform->removeElement('imagealt');
        }

        // If the user isn't fully set up, let them know that they will be able to change
        // their profile picture once their profile is complete.
        if ($usernotfullysetup) {
            // Show a static warning message using addElement (supported type).
            $mform->addElement('static', 'userpicturewarning', '', get_string('newpictureusernotsetup'));
            $enabledusernamefields = useredit_get_enabled_name_fields();
            // No need to manually insert, static element will appear in order added.
            // This is expected to exist when the form is submitted.
            $mform->addElement('hidden', 'imagefile');
        }

        // Next the customisable profile fields.
        profile_definition($mform, $userid);

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        global $CFG, $DB, $OUTPUT;

        $mform = $this->_form;
        $userid = $mform->getElementValue('id');

        // Trim required name fields.
        foreach (useredit_get_required_name_fields() as $field) {
            $mform->applyFilter($field, 'trim');
        }

        if ($user = $DB->get_record('user', array('id' => $userid))) {

            // Remove description.
            if (empty($user->description) && !empty($CFG->profilesforenrolledusersonly) && !$DB->record_exists('role_assignments', array('userid' => $userid))) {
                $mform->removeElement('description_editor');
            }

            // Print picture.
            $context = context_user::instance($user->id, MUST_EXIST);
            $fs = get_file_storage();
            $hasuploadedpicture = ($fs->file_exists($context->id, 'user', 'icon', 0, '/', 'f2.png') || $fs->file_exists($context->id, 'user', 'icon', 0, '/', 'f2.jpg'));
            if (!empty($user->picture) && $hasuploadedpicture) {
                $imagevalue = $OUTPUT->user_picture($user, array('courseid' => SITEID, 'size' => 64));
            } else {
                $imagevalue = get_string('none');
            }
            // Remove setValue on static element, value should be set when adding the element in editlib.php

            if ($mform->elementExists('deletepicture') && !$hasuploadedpicture) {
                $mform->removeElement('deletepicture');
            }

            // Disable fields that are locked by auth plugins.
            $fields = get_user_fieldnames();
            $authplugin = get_auth_plugin($user->auth);
            $customfields = $authplugin->get_custom_user_profile_fields();
            $customfieldsdata = profile_user_record($userid, false);
            $fields = array_merge($fields, $customfields);
            foreach ($fields as $field) {
                if ($field === 'description') {
                    // Hard coded hack for description field. See MDL-37704 for details.
                    $formfield = 'description_editor';
                } else {
                    $formfield = $field;
                }
                if (!$mform->elementExists($formfield)) {
                    continue;
                }

                // Get the original value for the field.
                if (in_array($field, $customfields)) {
                    $key = str_replace('profile_field_', '', $field);
                    $value = isset($customfieldsdata->{$key}) ? $customfieldsdata->{$key} : '';
                } else {
                    $value = $user->{$field};
                }

                $configvariable = 'field_lock_' . $field;
                if (isset($authplugin->config->{$configvariable})) {
                    if ($authplugin->config->{$configvariable} === 'locked') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    } else if ($authplugin->config->{$configvariable} === 'unlockedifempty' and $value != '') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    }
                }
            }

            // Next the customisable profile fields.
            profile_definition_after_data($mform, $user->id);

        } else {
            profile_definition_after_data($mform, 0);
        }
    }

    /**
     * Validate incoming form data.
     * @param array $usernew
     * @param array $files
     * @return array
     */
    public function validation($usernew, $files) {
        global $CFG, $DB;

        $errors = parent::validation($usernew, $files);

        $usernew = (object)$usernew;
        $user    = $DB->get_record('user', array('id' => $usernew->id));

        // Validate email.
        if (!isset($usernew->email)) {
            // Mail not confirmed yet.
        } else if (!validate_email($usernew->email)) {
            $errors['email'] = get_string('invalidemail');
        } else if (($usernew->email !== $user->email) && empty($CFG->allowaccountssameemail)) {
            // Make a case-insensitive query for the given email address.
            $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND id <> :userid';
            $params = array(
                'email' => $usernew->email,
                'mnethostid' => $CFG->mnet_localhost_id,
                'userid' => $usernew->id
            );
            // If there are other user(s) that already have the same email, show an error.
            if ($DB->record_exists_select('user', $select, $params)) {
                $errors['email'] = get_string('emailexists');
            }
        }

        if (isset($usernew->email) and $usernew->email === $user->email and over_bounce_threshold($user)) {
            $errors['email'] = get_string('toomanybounces');
        }

        if (isset($usernew->email) and !empty($CFG->verifychangedemail) and !isset($errors['email']) and !has_capability('moodle/user:update', context_system::instance())) {
            $errorstr = email_is_not_allowed($usernew->email);
            if ($errorstr !== false) {
                $errors['email'] = $errorstr;
            }
        }

        // Next the customisable profile fields.
        $errors += profile_validation($usernew, $files);

        return $errors;
    }
}


