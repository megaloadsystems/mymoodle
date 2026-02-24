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
 * Displays list of vrclassroom instances in a course.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/vrclassroom/index.php', ['id' => $id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('modulenameplural', 'vrclassroom'));
$PAGE->set_heading($course->fullname);

$params = [
    'context' => context_course::instance($id),
];
$event = \mod_vrclassroom\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$instances = get_all_instances_in_course('vrclassroom', $course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'vrclassroom'));

if (!$instances) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'vrclassroom')),
        new moodle_url('/course/view.php', ['id' => $course->id]));
    echo $OUTPUT->footer();
    die();
}

$usesections = course_format_uses_sections($course->format);
$table = new html_table();

$headers = [get_string('name')];
$align = ['left'];
if ($usesections) {
    $headers = [get_string('sectionname', 'format_' . $course->format), ...$headers];
    $align = ['center', ...$align];
}
$headers[] = get_string('timeopen', 'vrclassroom');
$headers[] = get_string('timeclose', 'vrclassroom');
$headers[] = get_string('status', 'vrclassroom');
$align[] = 'left';
$align[] = 'left';
$align[] = 'left';

$table->head = $headers;
$table->align = $align;

$currentsection = '';
$now = time();
foreach ($instances as $instance) {
    $name = format_string($instance->name, true);
    if (!$instance->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/vrclassroom/view.php', ['id' => $instance->coursemodule]),
            $name,
            ['class' => 'dimmed']
        );
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/vrclassroom/view.php', ['id' => $instance->coursemodule]),
            $name
        );
    }

    $opentext = !empty($instance->timeopen) ? userdate($instance->timeopen) : '-';
    $closetext = !empty($instance->timeclose) ? userdate($instance->timeclose) : '-';

    if (vrclassroom_is_available($instance, $now)) {
        $status = get_string('statusopen', 'vrclassroom');
    } else if (!empty($instance->timeopen) && $now < (int)$instance->timeopen) {
        $status = get_string('statusnotopen', 'vrclassroom');
    } else {
        $status = get_string('statusclosed', 'vrclassroom');
    }

    $printsection = '';
    if ($usesections && $instance->section !== $currentsection) {
        if ($instance->section) {
            $printsection = get_section_name($course, $instance->section);
        }
        if ($currentsection !== '') {
            $table->data[] = 'hr';
        }
        $currentsection = $instance->section;
    }

    $row = [$link, $opentext, $closetext, $status];
    if ($usesections) {
        $row = [$printsection, ...$row];
    }
    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
