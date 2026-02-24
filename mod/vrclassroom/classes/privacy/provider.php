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
 * Privacy provider.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vrclassroom\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation for mod_vrclassroom.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('vrclassroom_presence', [
            'userid' => 'privacy:metadata:vrclassroom_presence:userid',
            'x' => 'privacy:metadata:vrclassroom_presence:x',
            'y' => 'privacy:metadata:vrclassroom_presence:y',
            'z' => 'privacy:metadata:vrclassroom_presence:z',
            'ry' => 'privacy:metadata:vrclassroom_presence:ry',
            'timemodified' => 'privacy:metadata:vrclassroom_presence:timemodified',
        ], 'privacy:metadata:vrclassroom_presence');

        $collection->add_database_table('vrclassroom_signal', [
            'fromuserid' => 'privacy:metadata:vrclassroom_signal:fromuserid',
            'touserid' => 'privacy:metadata:vrclassroom_signal:touserid',
            'messagetype' => 'privacy:metadata:vrclassroom_signal:messagetype',
            'payload' => 'privacy:metadata:vrclassroom_signal:payload',
            'timecreated' => 'privacy:metadata:vrclassroom_signal:timecreated',
        ], 'privacy:metadata:vrclassroom_signal');

        $collection->add_database_table('vrclassroom_visit', [
            'userid' => 'privacy:metadata:vrclassroom_visit:userid',
            'timejoined' => 'privacy:metadata:vrclassroom_visit:timejoined',
            'timelastseen' => 'privacy:metadata:vrclassroom_visit:timelastseen',
            'timeleft' => 'privacy:metadata:vrclassroom_visit:timeleft',
            'duration' => 'privacy:metadata:vrclassroom_visit:duration',
            'closemethod' => 'privacy:metadata:vrclassroom_visit:closemethod',
        ], 'privacy:metadata:vrclassroom_visit');

        $collection->add_database_table('vrclassroom_handraise', [
            'userid' => 'privacy:metadata:vrclassroom_handraise:userid',
            'raised' => 'privacy:metadata:vrclassroom_handraise:raised',
            'timemodified' => 'privacy:metadata:vrclassroom_handraise:timemodified',
        ], 'privacy:metadata:vrclassroom_handraise');

        $collection->add_database_table('vrclassroom_state', [
            'muteall' => 'privacy:metadata:vrclassroom_state:muteall',
            'spotlightuserid' => 'privacy:metadata:vrclassroom_state:spotlightuserid',
            'allowedspeakerid' => 'privacy:metadata:vrclassroom_state:allowedspeakerid',
            'updatedby' => 'privacy:metadata:vrclassroom_state:updatedby',
            'timemodified' => 'privacy:metadata:vrclassroom_state:timemodified',
        ], 'privacy:metadata:vrclassroom_state');

        return $collection;
    }

    /**
     * Returns contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {vrclassroom} v
                  JOIN {modules} m
                    ON m.name = :modulename
                  JOIN {course_modules} cm
                    ON cm.instance = v.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :contextlevel
             LEFT JOIN {vrclassroom_presence} p
                    ON p.vrclassroomid = v.id
                   AND p.userid = :useridpresence
             LEFT JOIN {vrclassroom_signal} sf
                    ON sf.vrclassroomid = v.id
                   AND sf.fromuserid = :useridfrom
             LEFT JOIN {vrclassroom_signal} st
                    ON st.vrclassroomid = v.id
                   AND st.touserid = :useridto
             LEFT JOIN {vrclassroom_visit} vv
                    ON vv.vrclassroomid = v.id
                   AND vv.userid = :useridvisit
             LEFT JOIN {vrclassroom_handraise} vh
                    ON vh.vrclassroomid = v.id
                   AND vh.userid = :useridhandraise
             LEFT JOIN {vrclassroom_state} s
                    ON s.vrclassroomid = v.id
                   AND (s.updatedby = :useridstateupdated
                     OR s.spotlightuserid = :useridstatespotlight
                     OR s.allowedspeakerid = :useridstatespeaker)
                 WHERE p.id IS NOT NULL
                    OR sf.id IS NOT NULL
                    OR st.id IS NOT NULL
                    OR vv.id IS NOT NULL
                    OR vh.id IS NOT NULL
                    OR s.id IS NOT NULL";

        $params = [
            'modulename' => 'vrclassroom',
            'contextlevel' => CONTEXT_MODULE,
            'useridpresence' => $userid,
            'useridfrom' => $userid,
            'useridto' => $userid,
            'useridvisit' => $userid,
            'useridhandraise' => $userid,
            'useridstateupdated' => $userid,
            'useridstatespotlight' => $userid,
            'useridstatespeaker' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Gets users who have data in a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $instanceid = self::get_instanceid_from_context($context);
        if (!$instanceid) {
            return;
        }

        $sql = "SELECT DISTINCT userid
                  FROM (
                        SELECT p.userid AS userid
                          FROM {vrclassroom_presence} p
                         WHERE p.vrclassroomid = :vrclassroomidpresence
                        UNION
                        SELECT s.fromuserid AS userid
                          FROM {vrclassroom_signal} s
                         WHERE s.vrclassroomid = :vrclassroomidsent
                        UNION
                        SELECT s.touserid AS userid
                          FROM {vrclassroom_signal} s
                         WHERE s.vrclassroomid = :vrclassroomidreceived
                        UNION
                        SELECT v.userid AS userid
                          FROM {vrclassroom_visit} v
                         WHERE v.vrclassroomid = :vrclassroomidvisit
                        UNION
                        SELECT h.userid AS userid
                          FROM {vrclassroom_handraise} h
                         WHERE h.vrclassroomid = :vrclassroomidhandraise
                        UNION
                        SELECT st.updatedby AS userid
                          FROM {vrclassroom_state} st
                         WHERE st.vrclassroomid = :vrclassroomidstate
                        UNION
                        SELECT st.spotlightuserid AS userid
                          FROM {vrclassroom_state} st
                         WHERE st.vrclassroomid = :vrclassroomidstatespotlight
                        UNION
                        SELECT st.allowedspeakerid AS userid
                          FROM {vrclassroom_state} st
                         WHERE st.vrclassroomid = :vrclassroomidstatespeaker
                       ) x
                 WHERE userid > 0";

        $params = [
            'vrclassroomidpresence' => $instanceid,
            'vrclassroomidsent' => $instanceid,
            'vrclassroomidreceived' => $instanceid,
            'vrclassroomidvisit' => $instanceid,
            'vrclassroomidhandraise' => $instanceid,
            'vrclassroomidstate' => $instanceid,
            'vrclassroomidstatespotlight' => $instanceid,
            'vrclassroomidstatespeaker' => $instanceid,
        ];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Exports user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $instanceid = self::get_instanceid_from_context($context);
            if (!$instanceid) {
                continue;
            }

            $presence = $DB->get_records('vrclassroom_presence', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ], 'timemodified ASC');

            $signalssent = $DB->get_records('vrclassroom_signal', [
                'vrclassroomid' => $instanceid,
                'fromuserid' => $userid,
            ], 'timecreated ASC');

            $signalsreceived = $DB->get_records('vrclassroom_signal', [
                'vrclassroomid' => $instanceid,
                'touserid' => $userid,
            ], 'timecreated ASC');

            $visitlogs = $DB->get_records('vrclassroom_visit', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ], 'timejoined ASC');

            $handraise = $DB->get_records('vrclassroom_handraise', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ], 'timemodified ASC');

            $statechanges = $DB->get_records_select('vrclassroom_state',
                'vrclassroomid = :vrclassroomid AND (updatedby = :updatedby OR spotlightuserid = :spotlightuserid OR allowedspeakerid = :allowedspeakerid)',
                [
                    'vrclassroomid' => $instanceid,
                    'updatedby' => $userid,
                    'spotlightuserid' => $userid,
                    'allowedspeakerid' => $userid,
                ],
                'timemodified ASC'
            );

            $presenceexport = [];
            foreach ($presence as $record) {
                $presenceexport[] = [
                    'x' => (float)$record->x,
                    'y' => (float)$record->y,
                    'z' => (float)$record->z,
                    'ry' => (float)$record->ry,
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            $sentexport = [];
            foreach ($signalssent as $record) {
                $sentexport[] = [
                    'to_userid' => (int)$record->touserid,
                    'type' => $record->messagetype,
                    'payload' => $record->payload,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }

            $receivedexport = [];
            foreach ($signalsreceived as $record) {
                $receivedexport[] = [
                    'from_userid' => (int)$record->fromuserid,
                    'type' => $record->messagetype,
                    'payload' => $record->payload,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }

            $visitexport = [];
            foreach ($visitlogs as $record) {
                $visitexport[] = [
                    'timejoined' => transform::datetime($record->timejoined),
                    'timelastseen' => transform::datetime($record->timelastseen),
                    'timeleft' => $record->timeleft > 0 ? transform::datetime($record->timeleft) : null,
                    'duration_seconds' => (int)$record->duration,
                    'closemethod' => $record->closemethod,
                ];
            }

            $handraiseexport = [];
            foreach ($handraise as $record) {
                $handraiseexport[] = [
                    'raised' => transform::yesno($record->raised),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            $stateexport = [];
            foreach ($statechanges as $record) {
                $stateexport[] = [
                    'muteall' => transform::yesno($record->muteall),
                    'spotlight_userid' => (int)$record->spotlightuserid,
                    'allowed_speaker_userid' => (int)$record->allowedspeakerid,
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            $plugincontextdata = (object)[
                'presence' => $presenceexport,
                'signals_sent' => $sentexport,
                'signals_received' => $receivedexport,
                'visits' => $visitexport,
                'handraise' => $handraiseexport,
                'moderation_changes' => $stateexport,
            ];

            $contextdata = helper::get_context_data($context, $user);
            $data = (object)array_merge((array)$contextdata, (array)$plugincontextdata);
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Delete all user data in context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $instanceid = self::get_instanceid_from_context($context);
        if (!$instanceid) {
            return;
        }

        $DB->delete_records('vrclassroom_presence', ['vrclassroomid' => $instanceid]);
        $DB->delete_records('vrclassroom_signal', ['vrclassroomid' => $instanceid]);
        $DB->delete_records('vrclassroom_visit', ['vrclassroomid' => $instanceid]);
        $DB->delete_records('vrclassroom_handraise', ['vrclassroomid' => $instanceid]);
        $DB->delete_records('vrclassroom_state', ['vrclassroomid' => $instanceid]);
    }

    /**
     * Delete one user's data in approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $instanceid = self::get_instanceid_from_context($context);
            if (!$instanceid) {
                continue;
            }

            $DB->delete_records('vrclassroom_presence', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ]);

            $DB->delete_records('vrclassroom_visit', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('vrclassroom_handraise', [
                'vrclassroomid' => $instanceid,
                'userid' => $userid,
            ]);

            $DB->delete_records_select('vrclassroom_signal',
                'vrclassroomid = :vrclassroomid AND (fromuserid = :fromuserid OR touserid = :touserid)',
                [
                    'vrclassroomid' => $instanceid,
                    'fromuserid' => $userid,
                    'touserid' => $userid,
                ]
            );

            $DB->set_field('vrclassroom_state', 'updatedby', 0, [
                'vrclassroomid' => $instanceid,
                'updatedby' => $userid,
            ]);
            $DB->set_field('vrclassroom_state', 'spotlightuserid', 0, [
                'vrclassroomid' => $instanceid,
                'spotlightuserid' => $userid,
            ]);
            $DB->set_field('vrclassroom_state', 'allowedspeakerid', 0, [
                'vrclassroomid' => $instanceid,
                'allowedspeakerid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $instanceid = self::get_instanceid_from_context($context);
        if (!$instanceid) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $presenceparams = array_merge(['vrclassroomid' => $instanceid], $userparams);

        $DB->delete_records_select('vrclassroom_presence',
            "vrclassroomid = :vrclassroomid AND userid {$usersql}",
            $presenceparams
        );

        $DB->delete_records_select('vrclassroom_visit',
            "vrclassroomid = :vrclassroomid AND userid {$usersql}",
            $presenceparams
        );
        $DB->delete_records_select('vrclassroom_handraise',
            "vrclassroomid = :vrclassroomid AND userid {$usersql}",
            $presenceparams
        );

        list($fromsql, $fromparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'from');
        list($tosql, $toparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'to');
        $signalparams = array_merge(['vrclassroomid' => $instanceid], $fromparams, $toparams);

        $DB->delete_records_select('vrclassroom_signal',
            "vrclassroomid = :vrclassroomid AND (fromuserid {$fromsql} OR touserid {$tosql})",
            $signalparams
        );

        $DB->execute(
            "UPDATE {vrclassroom_state}
                SET updatedby = 0
              WHERE vrclassroomid = :vrclassroomid
                AND updatedby {$usersql}",
            $presenceparams
        );
        $DB->execute(
            "UPDATE {vrclassroom_state}
                SET spotlightuserid = 0
              WHERE vrclassroomid = :vrclassroomid
                AND spotlightuserid {$usersql}",
            $presenceparams
        );
        $DB->execute(
            "UPDATE {vrclassroom_state}
                SET allowedspeakerid = 0
              WHERE vrclassroomid = :vrclassroomid
                AND allowedspeakerid {$usersql}",
            $presenceparams
        );
    }

    /**
     * Resolves a vrclassroom instance id from module context.
     *
     * @param context_module $context
     * @return int
     */
    protected static function get_instanceid_from_context(context_module $context): int {
        $cm = get_coursemodule_from_id('vrclassroom', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return 0;
        }
        return (int)$cm->instance;
    }
}
