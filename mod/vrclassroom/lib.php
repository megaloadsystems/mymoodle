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
 * Library of interface functions and constants for mod_vrclassroom.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed
 */
function vrclassroom_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => false,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_BACKUP_MOODLE2 => false,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_OTHER,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COMMUNICATION,
        FEATURE_MODEDIT_DEFAULT_COMPLETION => true,
        default => null,
    };
}

/**
 * Adds a new instance.
 *
 * @param stdClass $vrclassroom
 * @param mod_vrclassroom_mod_form|null $mform
 * @return int
 */
function vrclassroom_add_instance($vrclassroom, $mform = null) {
    global $DB;

    $now = time();
    $vrclassroom->timeopen = empty($vrclassroom->timeopen) ? 0 : $vrclassroom->timeopen;
    $vrclassroom->timeclose = empty($vrclassroom->timeclose) ? 0 : $vrclassroom->timeclose;
    $vrclassroom->spatialradius = empty($vrclassroom->spatialradius) ? 8 : (float)$vrclassroom->spatialradius;
    $vrclassroom->defaultmuteall = empty($vrclassroom->defaultmuteall) ? 0 : 1;
    $vrclassroom->timecreated = $now;
    $vrclassroom->timemodified = $now;

    $id = $DB->insert_record('vrclassroom', $vrclassroom);

    $state = (object)[
        'vrclassroomid' => $id,
        'muteall' => $vrclassroom->defaultmuteall,
        'spotlightuserid' => 0,
        'allowedspeakerid' => 0,
        'updatedby' => 0,
        'timemodified' => $now,
    ];
    $DB->insert_record('vrclassroom_state', $state);

    $completionexpected = !empty($vrclassroom->completionexpected) ? $vrclassroom->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $vrclassroom->coursemodule,
        'vrclassroom',
        $id,
        $completionexpected
    );

    return $id;
}

/**
 * Updates an existing instance.
 *
 * @param stdClass $vrclassroom
 * @param mod_vrclassroom_mod_form|null $mform
 * @return bool
 */
function vrclassroom_update_instance($vrclassroom, $mform = null) {
    global $DB;

    $now = time();
    $vrclassroom->id = $vrclassroom->instance;
    $vrclassroom->timeopen = empty($vrclassroom->timeopen) ? 0 : $vrclassroom->timeopen;
    $vrclassroom->timeclose = empty($vrclassroom->timeclose) ? 0 : $vrclassroom->timeclose;
    $vrclassroom->spatialradius = empty($vrclassroom->spatialradius) ? 8 : (float)$vrclassroom->spatialradius;
    $vrclassroom->defaultmuteall = empty($vrclassroom->defaultmuteall) ? 0 : 1;
    $vrclassroom->timemodified = $now;

    $updated = $DB->update_record('vrclassroom', $vrclassroom);

    if ($state = $DB->get_record('vrclassroom_state', ['vrclassroomid' => $vrclassroom->id])) {
        // Keep explicit moderator state unless it has never been changed by a user.
        if ((int)$state->updatedby === 0) {
            $state->muteall = $vrclassroom->defaultmuteall;
        }
        if (!isset($state->spotlightuserid)) {
            $state->spotlightuserid = 0;
        }
        if (!isset($state->allowedspeakerid)) {
            $state->allowedspeakerid = 0;
        }
        $state->timemodified = $now;
        $DB->update_record('vrclassroom_state', $state);
    } else {
        $state = (object)[
            'vrclassroomid' => $vrclassroom->id,
            'muteall' => $vrclassroom->defaultmuteall,
            'spotlightuserid' => 0,
            'allowedspeakerid' => 0,
            'updatedby' => 0,
            'timemodified' => $now,
        ];
        $DB->insert_record('vrclassroom_state', $state);
    }

    $completionexpected = !empty($vrclassroom->completionexpected) ? $vrclassroom->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $vrclassroom->coursemodule,
        'vrclassroom',
        $vrclassroom->id,
        $completionexpected
    );

    return $updated;
}

/**
 * Deletes an instance.
 *
 * @param int $id
 * @return bool
 */
function vrclassroom_delete_instance($id) {
    global $DB;

    if (!$vrclassroom = $DB->get_record('vrclassroom', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('vrclassroom_presence', ['vrclassroomid' => $vrclassroom->id]);
    $DB->delete_records('vrclassroom_signal', ['vrclassroomid' => $vrclassroom->id]);
    $DB->delete_records('vrclassroom_visit', ['vrclassroomid' => $vrclassroom->id]);
    $DB->delete_records('vrclassroom_handraise', ['vrclassroomid' => $vrclassroom->id]);
    $DB->delete_records('vrclassroom_state', ['vrclassroomid' => $vrclassroom->id]);
    $DB->delete_records('vrclassroom', ['id' => $vrclassroom->id]);

    return true;
}

/**
 * Trigger module viewed event and completion.
 *
 * @param stdClass $vrclassroom
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param context_module $context
 */
function vrclassroom_view($vrclassroom, $course, $cm, $context) {
    $event = \mod_vrclassroom\event\course_module_viewed::create([
        'objectid' => $vrclassroom->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('vrclassroom', $vrclassroom);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Returns module info to be shown in course page.
 *
 * @param cm_info $cm
 * @return cached_cm_info|false
 */
function vrclassroom_get_coursemodule_info($cm) {
    global $DB;

    if (!$vrclassroom = $DB->get_record('vrclassroom', ['id' => $cm->instance],
            'id, name, intro, introformat')) {
        return false;
    }

    $cminfo = new cached_cm_info();
    $cminfo->name = $vrclassroom->name;

    if ($cm->showdescription) {
        $cminfo->content = format_module_intro('vrclassroom', $vrclassroom, $cm->id, false);
    }

    return $cminfo;
}

/**
 * File serving callback.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context_module $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 */
function vrclassroom_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    if ($filearea !== 'intro') {
        send_file_not_found();
    }

    $itemid = 0;
    $filepath = '/';
    $filename = array_pop($args);
    if (!empty($args)) {
        $filepath .= implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_vrclassroom', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Whether the VR classroom session is currently available.
 *
 * @param stdClass $vrclassroom
 * @param int|null $timestamp
 * @return bool
 */
function vrclassroom_is_available($vrclassroom, $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }

    if (!empty($vrclassroom->timeopen) && $timestamp < (int)$vrclassroom->timeopen) {
        return false;
    }

    if (!empty($vrclassroom->timeclose) && $timestamp > (int)$vrclassroom->timeclose) {
        return false;
    }

    return true;
}

/**
 * Returns user-facing message when the room is unavailable.
 *
 * @param stdClass $vrclassroom
 * @param int|null $timestamp
 * @return string|null
 */
function vrclassroom_get_availability_message($vrclassroom, $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }

    if (!empty($vrclassroom->timeopen) && $timestamp < (int)$vrclassroom->timeopen) {
        return get_string('notopenyet', 'vrclassroom', userdate($vrclassroom->timeopen));
    }

    if (!empty($vrclassroom->timeclose) && $timestamp > (int)$vrclassroom->timeclose) {
        return get_string('closed', 'vrclassroom', userdate($vrclassroom->timeclose));
    }

    return null;
}

/**
 * Room state helper.
 *
 * @param int $vrclassroomid
 * @return stdClass
 */
function vrclassroom_get_room_state($vrclassroomid) {
    global $DB;

    if ($state = $DB->get_record('vrclassroom_state', ['vrclassroomid' => $vrclassroomid])) {
        $changed = false;
        if (!isset($state->spotlightuserid)) {
            $state->spotlightuserid = 0;
            $changed = true;
        }
        if (!isset($state->allowedspeakerid)) {
            $state->allowedspeakerid = 0;
            $changed = true;
        }
        if ($changed) {
            $DB->update_record('vrclassroom_state', $state);
        }
        return $state;
    }

    $state = (object)[
        'vrclassroomid' => $vrclassroomid,
        'muteall' => 0,
        'spotlightuserid' => 0,
        'allowedspeakerid' => 0,
        'updatedby' => 0,
        'timemodified' => time(),
    ];
    $state->id = $DB->insert_record('vrclassroom_state', $state);
    return $state;
}

/**
 * Start or update an active room visit for attendance logs.
 *
 * @param int $vrclassroomid
 * @param int $userid
 * @param int|null $timestamp
 */
function vrclassroom_touch_visit($vrclassroomid, $userid, $timestamp = null) {
    global $DB;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $activevisits = $DB->get_records('vrclassroom_visit', [
        'vrclassroomid' => $vrclassroomid,
        'userid' => $userid,
        'timeleft' => 0,
    ], 'id ASC');

    if (empty($activevisits)) {
        $visit = (object)[
            'vrclassroomid' => $vrclassroomid,
            'userid' => $userid,
            'timejoined' => $timestamp,
            'timelastseen' => $timestamp,
            'timeleft' => 0,
            'duration' => 0,
            'closemethod' => '',
        ];
        $DB->insert_record('vrclassroom_visit', $visit);
        return;
    }

    $primary = array_shift($activevisits);
    if ((int)$primary->timelastseen !== (int)$timestamp) {
        $primary->timelastseen = $timestamp;
        $DB->update_record('vrclassroom_visit', $primary);
    }

    // Defensive cleanup in case multiple active rows exist.
    foreach ($activevisits as $extra) {
        $endtime = (int)$extra->timelastseen > 0 ? (int)$extra->timelastseen : $timestamp;
        if ($endtime < (int)$extra->timejoined) {
            $endtime = (int)$extra->timejoined;
        }
        $extra->timeleft = $endtime;
        $extra->duration = $extra->timeleft - (int)$extra->timejoined;
        $extra->closemethod = 'dedupe';
        $DB->update_record('vrclassroom_visit', $extra);
    }
}

/**
 * Close active room visits for a user.
 *
 * @param int $vrclassroomid
 * @param int $userid
 * @param string $closemethod
 * @param int|null $timestamp
 */
function vrclassroom_close_active_visits($vrclassroomid, $userid, $closemethod = 'leave', $timestamp = null) {
    global $DB;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $activevisits = $DB->get_records('vrclassroom_visit', [
        'vrclassroomid' => $vrclassroomid,
        'userid' => $userid,
        'timeleft' => 0,
    ], 'id ASC');

    foreach ($activevisits as $visit) {
        $endtime = $timestamp;
        if ($closemethod === 'timeout' && (int)$visit->timelastseen > 0) {
            $endtime = (int)$visit->timelastseen;
        }
        if ($endtime < (int)$visit->timejoined) {
            $endtime = (int)$visit->timejoined;
        }

        $visit->timeleft = $endtime;
        $visit->duration = $visit->timeleft - (int)$visit->timejoined;
        $visit->closemethod = $closemethod;
        $DB->update_record('vrclassroom_visit', $visit);
    }
}

/**
 * Close stale open visits when presence expires.
 *
 * @param int $vrclassroomid
 * @param int $presencewindow
 * @param int|null $timestamp
 */
function vrclassroom_close_stale_visits($vrclassroomid, $presencewindow = 120, $timestamp = null) {
    global $DB;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $cutoff = $timestamp - $presencewindow;
    $sql = "SELECT v.id, v.timejoined, v.timelastseen
              FROM {vrclassroom_visit} v
         LEFT JOIN {vrclassroom_presence} p
                ON p.vrclassroomid = v.vrclassroomid
               AND p.userid = v.userid
             WHERE v.vrclassroomid = :vrclassroomid
               AND v.timeleft = 0
               AND (p.id IS NULL OR p.timemodified < :cutoff)";
    $visits = $DB->get_records_sql($sql, [
        'vrclassroomid' => $vrclassroomid,
        'cutoff' => $cutoff,
    ]);

    foreach ($visits as $visit) {
        $endtime = (int)$visit->timelastseen > 0 ? (int)$visit->timelastseen : $cutoff;
        if ($endtime > $timestamp) {
            $endtime = $timestamp;
        }
        if ($endtime < (int)$visit->timejoined) {
            $endtime = (int)$visit->timejoined;
        }

        $record = (object)[
            'id' => $visit->id,
            'timeleft' => $endtime,
            'duration' => $endtime - (int)$visit->timejoined,
            'closemethod' => 'timeout',
        ];
        $DB->update_record('vrclassroom_visit', $record);
    }
}

/**
 * Get current hand raise flag for one user.
 *
 * @param int $vrclassroomid
 * @param int $userid
 * @return bool
 */
function vrclassroom_get_handraise($vrclassroomid, $userid) {
    global $DB;

    if ($record = $DB->get_record('vrclassroom_handraise', ['vrclassroomid' => $vrclassroomid, 'userid' => $userid])) {
        return !empty($record->raised);
    }
    return false;
}

/**
 * Set hand raise flag for one user.
 *
 * @param int $vrclassroomid
 * @param int $userid
 * @param bool $raised
 */
function vrclassroom_set_handraise($vrclassroomid, $userid, $raised) {
    global $DB;

    $raisedint = $raised ? 1 : 0;
    $now = time();
    $record = $DB->get_record('vrclassroom_handraise', ['vrclassroomid' => $vrclassroomid, 'userid' => $userid]);
    if ($record) {
        $record->raised = $raisedint;
        $record->timemodified = $now;
        $DB->update_record('vrclassroom_handraise', $record);
        return;
    }

    $record = (object)[
        'vrclassroomid' => $vrclassroomid,
        'userid' => $userid,
        'raised' => $raisedint,
        'timemodified' => $now,
    ];
    $DB->insert_record('vrclassroom_handraise', $record);
}

/**
 * Remove stale presence and signaling records.
 *
 * @param int $vrclassroomid
 * @param int $presencewindow
 * @param int $signalwindow
 */
function vrclassroom_cleanup_ephemeral_data($vrclassroomid, $presencewindow = 120, $signalwindow = 300) {
    global $DB;

    $now = time();
    vrclassroom_close_stale_visits($vrclassroomid, $presencewindow, $now);

    $DB->delete_records_select('vrclassroom_presence', 'vrclassroomid = :vrclassroomid AND timemodified < :cutoff', [
        'vrclassroomid' => $vrclassroomid,
        'cutoff' => $now - $presencewindow,
    ]);
    $DB->delete_records_select('vrclassroom_signal', 'vrclassroomid = :vrclassroomid AND timecreated < :cutoff', [
        'vrclassroomid' => $vrclassroomid,
        'cutoff' => $now - $signalwindow,
    ]);
}
