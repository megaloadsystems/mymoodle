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
 * AJAX endpoint for room presence, signaling, and moderation.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Outputs JSON and exits.
 *
 * @param array $payload
 * @param int $status
 */
function vrclassroom_send_json(array $payload, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    die();
}

/**
 * Reads a float param safely.
 *
 * @param string $name
 * @param float $default
 * @return float
 */
function vrclassroom_optional_float(string $name, float $default = 0.0): float {
    $raw = optional_param($name, (string)$default, PARAM_RAW_TRIMMED);
    if (!is_numeric($raw)) {
        return $default;
    }
    return (float)$raw;
}

try {
    $action = required_param('action', PARAM_ALPHAEXT);
    $id = required_param('id', PARAM_INT);

    $cm = get_coursemodule_from_id('vrclassroom', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $vrclassroom = $DB->get_record('vrclassroom', ['id' => $cm->instance], '*', MUST_EXIST);

    require_course_login($course, true, $cm);
    require_sesskey();

    $context = context_module::instance($cm->id);

    $roomactions = ['heartbeat', 'poll_signals', 'send_signal', 'set_handraise', 'set_spotlight'];

    if ($action === 'set_muteall' || $action === 'set_spotlight') {
        require_capability('mod/vrclassroom:moderate', $context);
    } else {
        require_capability('mod/vrclassroom:join', $context);
    }

    if (in_array($action, $roomactions, true) && !vrclassroom_is_available($vrclassroom)) {
        throw new moodle_exception('sessionunavailable', 'vrclassroom');
    }

    $now = time();

    switch ($action) {
        case 'heartbeat':
            $x = vrclassroom_optional_float('x', 0.0);
            $y = vrclassroom_optional_float('y', 1.6);
            $z = vrclassroom_optional_float('z', 0.0);
            $ry = vrclassroom_optional_float('ry', 0.0);

            vrclassroom_cleanup_ephemeral_data($vrclassroom->id);

            $presence = $DB->get_record('vrclassroom_presence', [
                'vrclassroomid' => $vrclassroom->id,
                'userid' => $USER->id,
            ]);

            if ($presence) {
                $presence->x = $x;
                $presence->y = $y;
                $presence->z = $z;
                $presence->ry = $ry;
                $presence->timemodified = $now;
                $DB->update_record('vrclassroom_presence', $presence);
            } else {
                $presence = (object)[
                    'vrclassroomid' => $vrclassroom->id,
                    'userid' => $USER->id,
                    'x' => $x,
                    'y' => $y,
                    'z' => $z,
                    'ry' => $ry,
                    'timemodified' => $now,
                ];
                $DB->insert_record('vrclassroom_presence', $presence);
            }

            vrclassroom_touch_visit($vrclassroom->id, $USER->id, $now);

            $sql = "SELECT p.userid, p.x, p.y, p.z, p.ry, p.timemodified, u.firstname, u.lastname
                            , COALESCE(v.timejoined, 0) AS timejoined
                            , COALESCE(h.raised, 0) AS handraised
                      FROM {vrclassroom_presence} p
                      JOIN {user} u
                        ON u.id = p.userid
                 LEFT JOIN {vrclassroom_visit} v
                        ON v.vrclassroomid = p.vrclassroomid
                       AND v.userid = p.userid
                       AND v.timeleft = 0
                 LEFT JOIN {vrclassroom_handraise} h
                        ON h.vrclassroomid = p.vrclassroomid
                       AND h.userid = p.userid
                     WHERE p.vrclassroomid = :vrclassroomid
                  ORDER BY COALESCE(v.timejoined, 0) ASC, p.userid ASC";
            $records = $DB->get_records_sql($sql, ['vrclassroomid' => $vrclassroom->id]);

            $participants = [];
            $studentseatindex = 0;
            $myseatindex = null;
            $myisteacher = has_capability('mod/vrclassroom:moderate', $context);
            $presentuserids = [];
            foreach ($records as $record) {
                $isteacher = has_capability('mod/vrclassroom:moderate', $context, $record->userid);
                $seatindex = null;
                if (!$isteacher) {
                    $seatindex = $studentseatindex;
                    $studentseatindex++;
                }

                if ((int)$record->userid === (int)$USER->id) {
                    $myseatindex = $seatindex;
                    $myisteacher = $isteacher;
                }

                $participants[] = [
                    'userid' => (int)$record->userid,
                    'name' => fullname($record),
                    'x' => (float)$record->x,
                    'y' => (float)$record->y,
                    'z' => (float)$record->z,
                    'ry' => (float)$record->ry,
                    'canmoderate' => $isteacher,
                    'isteacher' => $isteacher,
                    'seatindex' => $seatindex,
                    'handraised' => !empty($record->handraised),
                    'timemodified' => (int)$record->timemodified,
                ];
                $presentuserids[(int)$record->userid] = true;
            }

            $state = vrclassroom_get_room_state($vrclassroom->id);
            $statechanged = false;
            if (!empty($state->spotlightuserid) && empty($presentuserids[(int)$state->spotlightuserid])) {
                $state->spotlightuserid = 0;
                $statechanged = true;
            }
            if (!empty($state->allowedspeakerid) && empty($presentuserids[(int)$state->allowedspeakerid])) {
                $state->allowedspeakerid = 0;
                $statechanged = true;
            }
            if ($statechanged) {
                $state->timemodified = $now;
                $DB->update_record('vrclassroom_state', $state);
            }

            vrclassroom_send_json([
                'ok' => true,
                'now' => $now,
                'muteall' => (bool)$state->muteall,
                'spotlightuserid' => (int)$state->spotlightuserid,
                'allowedspeakerid' => (int)$state->allowedspeakerid,
                'myseatindex' => $myseatindex,
                'myisteacher' => $myisteacher,
                'myhandraised' => vrclassroom_get_handraise($vrclassroom->id, $USER->id),
                'participants' => $participants,
            ]);
            break;

        case 'set_handraise':
            $raised = required_param('raised', PARAM_BOOL) ? 1 : 0;
            vrclassroom_set_handraise($vrclassroom->id, $USER->id, (bool)$raised);
            vrclassroom_send_json([
                'ok' => true,
                'raised' => (bool)$raised,
            ]);
            break;

        case 'poll_signals':
            vrclassroom_cleanup_ephemeral_data($vrclassroom->id);

            $signals = $DB->get_records_select(
                'vrclassroom_signal',
                'vrclassroomid = :vrclassroomid AND touserid = :touserid',
                [
                    'vrclassroomid' => $vrclassroom->id,
                    'touserid' => $USER->id,
                ],
                'id ASC',
                '*',
                0,
                250
            );

            $messages = [];
            $signalids = [];
            foreach ($signals as $signal) {
                $messages[] = [
                    'id' => (int)$signal->id,
                    'fromuserid' => (int)$signal->fromuserid,
                    'type' => $signal->messagetype,
                    'payload' => $signal->payload,
                ];
                $signalids[] = $signal->id;
            }

            if (!empty($signalids)) {
                $DB->delete_records_list('vrclassroom_signal', 'id', $signalids);
            }

            vrclassroom_send_json([
                'ok' => true,
                'signals' => $messages,
            ]);
            break;

        case 'send_signal':
            $touserid = required_param('touserid', PARAM_INT);
            $type = required_param('type', PARAM_ALPHAEXT);
            $payload = optional_param('payload', '', PARAM_RAW);

            $allowedtypes = ['offer', 'answer', 'candidate', 'leave'];
            if (!in_array($type, $allowedtypes, true)) {
                throw new moodle_exception('invalidsignalmessagetype', 'vrclassroom');
            }

            if ($touserid <= 0) {
                throw new moodle_exception('invalidtargetuser', 'vrclassroom');
            }

            $message = (object)[
                'vrclassroomid' => $vrclassroom->id,
                'fromuserid' => $USER->id,
                'touserid' => $touserid,
                'messagetype' => $type,
                'payload' => $payload,
                'timecreated' => $now,
            ];
            $DB->insert_record('vrclassroom_signal', $message);

            vrclassroom_send_json(['ok' => true]);
            break;

        case 'set_muteall':
            $mute = required_param('mute', PARAM_BOOL) ? 1 : 0;

            $state = vrclassroom_get_room_state($vrclassroom->id);
            $state->muteall = $mute;
            $state->updatedby = $USER->id;
            $state->timemodified = $now;
            $DB->update_record('vrclassroom_state', $state);

            vrclassroom_send_json([
                'ok' => true,
                'muteall' => (bool)$state->muteall,
            ]);
            break;

        case 'set_spotlight':
            $targetuserid = required_param('userid', PARAM_INT);
            $state = vrclassroom_get_room_state($vrclassroom->id);

            if ($targetuserid <= 0) {
                $state->spotlightuserid = 0;
                $state->allowedspeakerid = 0;
            } else {
                $targetpresence = $DB->get_record('vrclassroom_presence', [
                    'vrclassroomid' => $vrclassroom->id,
                    'userid' => $targetuserid,
                ]);
                $targetisteacher = has_capability('mod/vrclassroom:moderate', $context, $targetuserid);
                if (!$targetpresence || $targetisteacher) {
                    throw new moodle_exception('invalidtargetuser', 'vrclassroom');
                }

                if ((int)$state->spotlightuserid === $targetuserid && (int)$state->allowedspeakerid === $targetuserid) {
                    $state->spotlightuserid = 0;
                    $state->allowedspeakerid = 0;
                } else {
                    $state->spotlightuserid = $targetuserid;
                    $state->allowedspeakerid = $targetuserid;
                }
            }

            $state->updatedby = $USER->id;
            $state->timemodified = $now;
            $DB->update_record('vrclassroom_state', $state);

            vrclassroom_send_json([
                'ok' => true,
                'spotlightuserid' => (int)$state->spotlightuserid,
                'allowedspeakerid' => (int)$state->allowedspeakerid,
            ]);
            break;

        case 'leave':
            vrclassroom_close_active_visits($vrclassroom->id, $USER->id, 'leave', $now);
            vrclassroom_set_handraise($vrclassroom->id, $USER->id, false);
            $state = vrclassroom_get_room_state($vrclassroom->id);
            if ((int)$state->spotlightuserid === (int)$USER->id || (int)$state->allowedspeakerid === (int)$USER->id) {
                $state->spotlightuserid = 0;
                $state->allowedspeakerid = 0;
                $state->timemodified = $now;
                $DB->update_record('vrclassroom_state', $state);
            }
            $DB->delete_records('vrclassroom_presence', [
                'vrclassroomid' => $vrclassroom->id,
                'userid' => $USER->id,
            ]);
            $DB->delete_records_select('vrclassroom_signal',
                'vrclassroomid = :vrclassroomid AND (fromuserid = :fromuserid OR touserid = :touserid)',
                [
                    'vrclassroomid' => $vrclassroom->id,
                    'fromuserid' => $USER->id,
                    'touserid' => $USER->id,
                ]
            );

            vrclassroom_send_json(['ok' => true]);
            break;

        default:
            throw new moodle_exception('unknownaction', 'vrclassroom');
    }
} catch (Throwable $e) {
    vrclassroom_send_json([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 400);
}
