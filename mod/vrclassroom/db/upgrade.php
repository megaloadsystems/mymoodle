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
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_vrclassroom
 * @category    upgrade
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_vrclassroom upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_vrclassroom_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022401) {
        $table = new xmldb_table('vrclassroom_visit');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('vrclassroomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timejoined', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timelastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeleft', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('closemethod', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_vrclassroom', XMLDB_KEY_FOREIGN, ['vrclassroomid'], 'vrclassroom', ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('instance_user_open', XMLDB_INDEX_NOTUNIQUE, ['vrclassroomid', 'userid', 'timeleft']);
        $table->add_index('timejoined', XMLDB_INDEX_NOTUNIQUE, ['timejoined']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026022401, 'vrclassroom');
    }

    if ($oldversion < 2026022402) {
        $table = new xmldb_table('vrclassroom_handraise');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('vrclassroomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('raised', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_vrclassroom', XMLDB_KEY_FOREIGN, ['vrclassroomid'], 'vrclassroom', ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('instance_user_unique', XMLDB_INDEX_UNIQUE, ['vrclassroomid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026022402, 'vrclassroom');
    }

    if ($oldversion < 2026022403) {
        $table = new xmldb_table('vrclassroom_state');

        $spotlightfield = new xmldb_field('spotlightuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'muteall');
        if (!$dbman->field_exists($table, $spotlightfield)) {
            $dbman->add_field($table, $spotlightfield);
        }

        $speakerfield = new xmldb_field('allowedspeakerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'spotlightuserid');
        if (!$dbman->field_exists($table, $speakerfield)) {
            $dbman->add_field($table, $speakerfield);
        }

        upgrade_mod_savepoint(true, 2026022403, 'vrclassroom');
    }

    return true;
}
