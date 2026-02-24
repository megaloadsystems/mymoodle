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
 * Course module viewed event.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vrclassroom\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event when a classroom is viewed.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Initialize event details.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'vrclassroom';
    }

    /**
     * Mapping for backup restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'vrclassroom', 'restore' => 'vrclassroom'];
    }
}
