<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Module form.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * The module settings form.
 */
class mod_vrclassroom_mod_form extends moodleform_mod {

    /**
     * Defines form elements.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('vrclassroomname', 'vrclassroom'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('vrclassroomintro', 'vrclassroom'));

        $mform->addElement('header', 'availabilityhdr', get_string('availability'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'vrclassroom'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'vrclassroom'), ['optional' => true]);

        $mform->addElement('header', 'roomsettingshdr', get_string('roomsettings', 'vrclassroom'));

        $mform->addElement('advcheckbox', 'defaultmuteall', get_string('defaultmuteall', 'vrclassroom'));
        $mform->setDefault('defaultmuteall', 0);

        $mform->addElement('text', 'spatialradius', get_string('spatialradius', 'vrclassroom'));
        $mform->setType('spatialradius', PARAM_FLOAT);
        $mform->setDefault('spatialradius', 8);
        $mform->addHelpButton('spatialradius', 'spatialradius', 'vrclassroom');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Validation rules.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['timeopen']) && !empty($data['timeclose']) &&
                (int)$data['timeclose'] < (int)$data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'vrclassroom');
        }

        if (!isset($data['spatialradius']) || !is_numeric($data['spatialradius']) ||
                (float)$data['spatialradius'] <= 0 || (float)$data['spatialradius'] > 100) {
            $errors['spatialradius'] = get_string('invalidspatialradius', 'vrclassroom');
        }

        return $errors;
    }
}
