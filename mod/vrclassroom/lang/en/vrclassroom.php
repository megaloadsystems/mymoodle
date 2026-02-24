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
 * Strings for component mod_vrclassroom.
 *
 * @package     mod_vrclassroom
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'VR classroom';
$string['modulename'] = 'VR classroom';
$string['modulenameplural'] = 'VR classrooms';
$string['pluginadministration'] = 'VR classroom administration';
$string['vrclassroomname'] = 'Classroom name';
$string['vrclassroomintro'] = 'Classroom description';
$string['roomsettings'] = 'Room settings';
$string['timeopen'] = 'Open time';
$string['timeclose'] = 'Close time';
$string['defaultmuteall'] = 'Start with students muted';
$string['spatialradius'] = 'Spatial audio radius (meters)';
$string['spatialradius_help'] = 'Approximate distance after which participant audio fades to silent.';
$string['closebeforeopen'] = 'Close time must be after open time.';
$string['invalidspatialradius'] = 'Spatial radius must be a number greater than 0 and no more than 100.';
$string['notopenyet'] = 'This VR classroom opens at {$a}.';
$string['closed'] = 'This VR classroom closed at {$a}.';
$string['status'] = 'Status';
$string['statusopen'] = 'Open';
$string['statusnotopen'] = 'Not open yet';
$string['statusclosed'] = 'Closed';
$string['joinnotallowed'] = 'You do not have permission to join this VR classroom.';
$string['roominstructions'] = 'Teachers can move with W A S D + mouse look. Students are auto-seated and can look around with mouse.';
$string['moderatorhint'] = 'As lecturer/moderator, use "Mute all" for lecture mode and click a student avatar to spotlight/allow speaking.';
$string['micon'] = 'Mic on';
$string['micoff'] = 'Mic off';
$string['raisehand'] = 'Raise hand';
$string['lowerhand'] = 'Lower hand';
$string['spotlightactive'] = 'Spotlight active';
$string['spotlightcleared'] = 'Spotlight cleared';
$string['youcanspeak'] = 'You are spotlighted and can speak';
$string['spotlighthint'] = 'Click a student avatar to spotlight and allow speaking';
$string['muteallon'] = 'Mute all students';
$string['mutealloff'] = 'Unmute students';
$string['forcemuted'] = 'Lecturer has muted students. Your microphone is disabled.';
$string['connecting'] = 'Connecting...';
$string['connected'] = 'Connected';
$string['audionotsupported'] = 'Classroom loaded, but browser audio/WebRTC is not supported here.';
$string['joinfailed'] = 'Could not join the room. Check microphone permissions and try refreshing.';
$string['sessionunavailable'] = 'This classroom session is not currently open.';
$string['unknownaction'] = 'Unknown action.';
$string['invalidsignalmessagetype'] = 'Invalid signaling message type.';
$string['invalidtargetuser'] = 'Invalid target user.';
$string['viewattendance'] = 'View attendance report';
$string['attendancereport'] = 'Attendance and participation report';
$string['attendanceoverview'] = 'Attendance overview';
$string['sessionlog'] = 'Session access log';
$string['backtoroom'] = 'Back to classroom';
$string['participant'] = 'Participant';
$string['attended'] = 'Attended';
$string['sessionscount'] = 'Sessions';
$string['totaltime'] = 'Total time in room';
$string['firstjoined'] = 'First joined';
$string['lastleft'] = 'Last left';
$string['joinedat'] = 'Joined at';
$string['leftat'] = 'Left at';
$string['duration'] = 'Duration';
$string['closemethod'] = 'Close method';
$string['closemethod:leave'] = 'Explicit leave';
$string['closemethod:timeout'] = 'Timed out';
$string['closemethod:dedupe'] = 'Session cleanup';
$string['stillconnected'] = 'Still connected';
$string['inprogress'] = 'In progress';
$string['nostudentstotrack'] = 'No student participants found for attendance tracking.';
$string['nosessionsyet'] = 'No participation sessions have been logged yet.';
$string['iceservers'] = 'ICE servers JSON';
$string['iceservers_desc'] = 'JSON array passed to RTCPeerConnection. Include TURN servers for reliable production audio.';

$string['vrclassroom:addinstance'] = 'Add a new VR classroom';
$string['vrclassroom:view'] = 'View VR classroom';
$string['vrclassroom:join'] = 'Join VR classroom';
$string['vrclassroom:moderate'] = 'Moderate VR classroom (mute all)';
$string['vrclassroom:viewreports'] = 'View VR classroom attendance reports';

$string['privacy:metadata:vrclassroom_presence'] = 'Stores temporary in-room position state.';
$string['privacy:metadata:vrclassroom_presence:userid'] = 'The user represented by the presence record.';
$string['privacy:metadata:vrclassroom_presence:x'] = 'The user X position in the room.';
$string['privacy:metadata:vrclassroom_presence:y'] = 'The user Y position in the room.';
$string['privacy:metadata:vrclassroom_presence:z'] = 'The user Z position in the room.';
$string['privacy:metadata:vrclassroom_presence:ry'] = 'The user Y-axis rotation in the room.';
$string['privacy:metadata:vrclassroom_presence:timemodified'] = 'When the presence record was last updated.';

$string['privacy:metadata:vrclassroom_signal'] = 'Stores temporary signaling messages required for peer audio connections.';
$string['privacy:metadata:vrclassroom_signal:fromuserid'] = 'User sending the signaling message.';
$string['privacy:metadata:vrclassroom_signal:touserid'] = 'User receiving the signaling message.';
$string['privacy:metadata:vrclassroom_signal:messagetype'] = 'Signaling message type (offer, answer, candidate, leave).';
$string['privacy:metadata:vrclassroom_signal:payload'] = 'Signaling payload content.';
$string['privacy:metadata:vrclassroom_signal:timecreated'] = 'When the signaling message was created.';

$string['privacy:metadata:vrclassroom_visit'] = 'Stores persistent room attendance sessions for reporting.';
$string['privacy:metadata:vrclassroom_visit:userid'] = 'The participant in the room session.';
$string['privacy:metadata:vrclassroom_visit:timejoined'] = 'When the participant joined the room session.';
$string['privacy:metadata:vrclassroom_visit:timelastseen'] = 'The latest activity timestamp seen for the session.';
$string['privacy:metadata:vrclassroom_visit:timeleft'] = 'When the participant left the room session.';
$string['privacy:metadata:vrclassroom_visit:duration'] = 'The session duration in seconds.';
$string['privacy:metadata:vrclassroom_visit:closemethod'] = 'How the session was closed (leave, timeout, dedupe).';

$string['privacy:metadata:vrclassroom_handraise'] = 'Stores current participant hand raise state in the room.';
$string['privacy:metadata:vrclassroom_handraise:userid'] = 'The participant represented by the hand raise state.';
$string['privacy:metadata:vrclassroom_handraise:raised'] = 'Whether the participant currently has their hand raised.';
$string['privacy:metadata:vrclassroom_handraise:timemodified'] = 'When the hand raise state was last updated.';

$string['privacy:metadata:vrclassroom_state'] = 'Stores room-level moderation state.';
$string['privacy:metadata:vrclassroom_state:muteall'] = 'Whether mute-all is currently enabled.';
$string['privacy:metadata:vrclassroom_state:spotlightuserid'] = 'The user currently spotlighted by the lecturer.';
$string['privacy:metadata:vrclassroom_state:allowedspeakerid'] = 'The user explicitly allowed to speak while mute-all is enabled.';
$string['privacy:metadata:vrclassroom_state:updatedby'] = 'The user who last changed room moderation state.';
$string['privacy:metadata:vrclassroom_state:timemodified'] = 'When moderation state was changed.';
