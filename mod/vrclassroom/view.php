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
 * Displays a vrclassroom instance.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('vrclassroom', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $vrclassroom = $DB->get_record('vrclassroom', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $vrclassroom = $DB->get_record('vrclassroom', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $vrclassroom->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('vrclassroom', $vrclassroom->id, $course->id, false, MUST_EXIST);
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vrclassroom:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/mod/vrclassroom/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($vrclassroom->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

$canjoin = has_capability('mod/vrclassroom:join', $context);
$canmoderate = has_capability('mod/vrclassroom:moderate', $context);
$canviewreports = has_capability('mod/vrclassroom:viewreports', $context);
$availabilitymessage = vrclassroom_get_availability_message($vrclassroom);

if ($canjoin && empty($availabilitymessage)) {
    $iceservers = [['urls' => 'stun:stun.l.google.com:19302']];
    $iceserversraw = trim((string)get_config('mod_vrclassroom', 'iceservers'));
    if ($iceserversraw !== '') {
        $decoded = json_decode($iceserversraw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $iceservers = $decoded;
        }
    }

    $state = vrclassroom_get_room_state($vrclassroom->id);

    $config = [
        'cmid' => (int)$cm->id,
        'instanceid' => (int)$vrclassroom->id,
        'userid' => (int)$USER->id,
        'displayname' => fullname($USER),
        'ajaxurl' => (new moodle_url('/mod/vrclassroom/ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'canmoderate' => (bool)$canmoderate,
        'myisteacher' => (bool)$canmoderate,
        'myseatindex' => null,
        'spatialradius' => (float)$vrclassroom->spatialradius,
        'muteall' => (bool)$state->muteall,
        'spotlightuserid' => (int)$state->spotlightuserid,
        'allowedspeakerid' => (int)$state->allowedspeakerid,
        'myhandraised' => vrclassroom_get_handraise($vrclassroom->id, $USER->id),
        'iceservers' => $iceservers,
        'heartbeatinterval' => 1000,
        'signalinterval' => 700,
        'strings' => [
            'micon' => get_string('micon', 'vrclassroom'),
            'micoff' => get_string('micoff', 'vrclassroom'),
            'muteallon' => get_string('muteallon', 'vrclassroom'),
            'mutealloff' => get_string('mutealloff', 'vrclassroom'),
            'forcemuted' => get_string('forcemuted', 'vrclassroom'),
            'joinfailed' => get_string('joinfailed', 'vrclassroom'),
            'connecting' => get_string('connecting', 'vrclassroom'),
            'connected' => get_string('connected', 'vrclassroom'),
            'audionotsupported' => get_string('audionotsupported', 'vrclassroom'),
            'raisehand' => get_string('raisehand', 'vrclassroom'),
            'lowerhand' => get_string('lowerhand', 'vrclassroom'),
            'spotlightactive' => get_string('spotlightactive', 'vrclassroom'),
            'spotlightcleared' => get_string('spotlightcleared', 'vrclassroom'),
            'youcanspeak' => get_string('youcanspeak', 'vrclassroom'),
            'spotlighthint' => get_string('spotlighthint', 'vrclassroom'),
        ],
    ];

    $PAGE->requires->js(new moodle_url('/mod/vrclassroom/js/aframe-1.6.0.min.js'), true);
    $PAGE->requires->js_call_amd('mod_vrclassroom/room', 'init', [$config]);
}

vrclassroom_view($vrclassroom, $course, $cm, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($vrclassroom->name));

if (trim(strip_tags($vrclassroom->intro)) !== '') {
    echo $OUTPUT->box(format_module_intro('vrclassroom', $vrclassroom, $cm->id), 'generalbox mod_introbox', 'vrclassroomintro');
}

if ($canviewreports) {
    echo html_writer::link(
        new moodle_url('/mod/vrclassroom/report.php', ['id' => $cm->id]),
        get_string('viewattendance', 'vrclassroom'),
        ['class' => 'btn btn-outline-primary mb-3']
    );
}

if (!$canjoin) {
    echo $OUTPUT->notification(get_string('joinnotallowed', 'vrclassroom'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if (!empty($availabilitymessage)) {
    echo $OUTPUT->notification($availabilitymessage, 'warning');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(get_string('roominstructions', 'vrclassroom'), 'alert alert-info');

echo html_writer::start_div('vrclassroom-toolbar', ['id' => 'vrclassroom-toolbar']);
echo html_writer::tag('button', get_string('micon', 'vrclassroom'), [
    'type' => 'button',
    'class' => 'btn btn-secondary',
    'id' => 'vrclassroom-toggle-mic',
]);
echo html_writer::tag('button', get_string('raisehand', 'vrclassroom'), [
    'type' => 'button',
    'class' => 'btn btn-info',
    'id' => 'vrclassroom-toggle-handraise',
]);
if ($canmoderate) {
    echo html_writer::tag('button', get_string('muteallon', 'vrclassroom'), [
        'type' => 'button',
        'class' => 'btn btn-danger',
        'id' => 'vrclassroom-toggle-muteall',
    ]);
}
echo html_writer::tag('span', '', [
    'class' => 'vrclassroom-status',
    'id' => 'vrclassroom-status',
]);
echo html_writer::end_div();

echo html_writer::div('', 'vrclassroom-root', ['id' => 'vrclassroom-root']);

if ($canmoderate) {
    echo html_writer::div(get_string('moderatorhint', 'vrclassroom'), 'alert alert-secondary mt-2');
}

echo $OUTPUT->footer();
