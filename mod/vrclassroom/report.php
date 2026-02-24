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
 * Attendance and participation report page for VR classroom.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('vrclassroom', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$vrclassroom = $DB->get_record('vrclassroom', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vrclassroom:viewreports', $context);

$PAGE->set_context($context);
$PAGE->set_url('/mod/vrclassroom/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('attendancereport', 'vrclassroom'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

vrclassroom_cleanup_ephemeral_data($vrclassroom->id);

$now = time();
$students = [];

$coursecontext = context_course::instance($course->id);
$studentroles = $DB->get_records('role', ['archetype' => 'student'], 'id ASC', 'id');
foreach ($studentroles as $role) {
    $roleusers = get_role_users((int)$role->id, $coursecontext, false, 'u.id,u.firstname,u.lastname');
    foreach ($roleusers as $user) {
        if (!is_enrolled($context, $user, '', true)) {
            continue;
        }
        if (!has_capability('mod/vrclassroom:join', $context, $user->id)) {
            continue;
        }
        $students[(int)$user->id] = $user;
    }
}

if (empty($students)) {
    $fallback = get_enrolled_users($context, 'mod/vrclassroom:join', 0, 'u.id,u.firstname,u.lastname');
    foreach ($fallback as $user) {
        if (has_capability('mod/vrclassroom:moderate', $context, $user->id)) {
            continue;
        }
        $students[(int)$user->id] = $user;
    }
}

$summary = [];
foreach ($students as $student) {
    $summary[(int)$student->id] = [
        'userid' => (int)$student->id,
        'name' => fullname($student),
        'sessions' => 0,
        'duration' => 0,
        'firstjoined' => 0,
        'lastleft' => 0,
    ];
}

$sql = "SELECT v.*, u.firstname, u.lastname
          FROM {vrclassroom_visit} v
          JOIN {user} u
            ON u.id = v.userid
         WHERE v.vrclassroomid = :vrclassroomid
      ORDER BY v.timejoined DESC";
$visits = $DB->get_records_sql($sql, ['vrclassroomid' => $vrclassroom->id]);

$reportrows = [];
foreach ($visits as $visit) {
    $userid = (int)$visit->userid;
    if (!array_key_exists($userid, $students)) {
        continue;
    }

    $lefttime = (int)$visit->timeleft;
    $duration = (int)$visit->duration;
    if ($lefttime === 0) {
        $lefttime = max((int)$visit->timelastseen, (int)$visit->timejoined, $now);
        $duration = max(0, $lefttime - (int)$visit->timejoined);
    }

    $closemethod = '';
    if ((int)$visit->timeleft > 0) {
        $methodkey = 'closemethod:' . (string)$visit->closemethod;
        if (get_string_manager()->string_exists($methodkey, 'vrclassroom')) {
            $closemethod = get_string($methodkey, 'vrclassroom');
        } else {
            $closemethod = (string)$visit->closemethod;
        }
    }

    $summary[$userid]['sessions']++;
    $summary[$userid]['duration'] += $duration;

    if ($summary[$userid]['firstjoined'] === 0 || (int)$visit->timejoined < $summary[$userid]['firstjoined']) {
        $summary[$userid]['firstjoined'] = (int)$visit->timejoined;
    }
    if ($lefttime > $summary[$userid]['lastleft']) {
        $summary[$userid]['lastleft'] = $lefttime;
    }

    $reportrows[] = [
        'name' => fullname($visit),
        'joined' => userdate((int)$visit->timejoined),
        'left' => (int)$visit->timeleft > 0 ? userdate((int)$visit->timeleft) : get_string('stillconnected', 'vrclassroom'),
        'duration' => format_time($duration),
        'closemethod' => (int)$visit->timeleft > 0 ? $closemethod : get_string('inprogress', 'vrclassroom'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attendancereport', 'vrclassroom'));

echo html_writer::link(
    new moodle_url('/mod/vrclassroom/view.php', ['id' => $cm->id]),
    get_string('backtoroom', 'vrclassroom'),
    ['class' => 'btn btn-outline-secondary mb-3']
);

$attendancetable = new html_table();
$attendancetable->head = [
    get_string('participant', 'vrclassroom'),
    get_string('attended', 'vrclassroom'),
    get_string('sessionscount', 'vrclassroom'),
    get_string('totaltime', 'vrclassroom'),
    get_string('firstjoined', 'vrclassroom'),
    get_string('lastleft', 'vrclassroom'),
];
$attendancetable->align = ['left', 'center', 'center', 'left', 'left', 'left'];

foreach ($summary as $item) {
    $attended = $item['sessions'] > 0 ? get_string('yes') : get_string('no');
    $totaltime = $item['sessions'] > 0 ? format_time($item['duration']) : '-';
    $firstjoined = $item['firstjoined'] > 0 ? userdate($item['firstjoined']) : '-';
    $lastleft = $item['lastleft'] > 0 ? userdate($item['lastleft']) : '-';

    $attendancetable->data[] = [
        $item['name'],
        $attended,
        (string)$item['sessions'],
        $totaltime,
        $firstjoined,
        $lastleft,
    ];
}

if (empty($attendancetable->data)) {
    echo $OUTPUT->notification(get_string('nostudentstotrack', 'vrclassroom'), 'warning');
} else {
    echo $OUTPUT->heading(get_string('attendanceoverview', 'vrclassroom'), 3);
    echo html_writer::table($attendancetable);
}

$sessiontable = new html_table();
$sessiontable->head = [
    get_string('participant', 'vrclassroom'),
    get_string('joinedat', 'vrclassroom'),
    get_string('leftat', 'vrclassroom'),
    get_string('duration', 'vrclassroom'),
    get_string('closemethod', 'vrclassroom'),
];
$sessiontable->align = ['left', 'left', 'left', 'left', 'left'];

foreach ($reportrows as $row) {
    $sessiontable->data[] = [
        $row['name'],
        $row['joined'],
        $row['left'],
        $row['duration'],
        s($row['closemethod']),
    ];
}

echo $OUTPUT->heading(get_string('sessionlog', 'vrclassroom'), 3);
if (empty($sessiontable->data)) {
    echo $OUTPUT->notification(get_string('nosessionsyet', 'vrclassroom'), 'info');
} else {
    echo html_writer::table($sessiontable);
}

echo $OUTPUT->footer();
