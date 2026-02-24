define([], function() {
    'use strict';

    let state = null;

    const TEACHER_POSE = {x: 0, y: 1.6, z: -10.8, ry: 180};
    const BASE_STUDENT_SEATS = [
        {x: -3.0, z: -6.0, ry: 0},
        {x: 3.0, z: -6.0, ry: 0},
        {x: -3.0, z: -3.0, ry: 0},
        {x: 3.0, z: -3.0, ry: 0},
        {x: -3.0, z: 0.0, ry: 0},
        {x: 3.0, z: 0.0, ry: 0},
        {x: -3.0, z: 3.0, ry: 0},
        {x: 3.0, z: 3.0, ry: 0},
        {x: -3.0, z: 6.0, ry: 0},
        {x: 3.0, z: 6.0, ry: 0},
    ];

    function getStudentSeatPose(seatindex) {
        const index = Number.isInteger(seatindex) ? seatindex : 0;
        if (index >= 0 && index < BASE_STUDENT_SEATS.length) {
            return BASE_STUDENT_SEATS[index];
        }

        const overflow = Math.max(0, index - BASE_STUDENT_SEATS.length);
        const row = Math.floor(overflow / 2);
        const side = overflow % 2;
        return {
            x: side === 0 ? -3.0 : 3.0,
            z: 9.0 + (row * 3.0),
            ry: 0,
        };
    }

    function getStudentCameraPose(seatindex) {
        const seat = getStudentSeatPose(seatindex);
        return {
            x: seat.x,
            y: 1.22,
            z: seat.z + 0.2,
            ry: seat.ry,
        };
    }

    function buildRequestBody(action, extra) {
        const params = new URLSearchParams();
        params.set('action', action);
        params.set('id', String(state.cmid));
        params.set('sesskey', state.sesskey);

        Object.keys(extra || {}).forEach(function(key) {
            const value = extra[key];
            if (value !== null && value !== undefined) {
                params.set(key, String(value));
            }
        });

        return params.toString();
    }

    async function post(action, extra) {
        const response = await fetch(state.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: buildRequestBody(action, extra),
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (e) {
            throw new Error(state.strings.joinfailed);
        }

        if (!response.ok || payload.ok === false) {
            throw new Error(payload.error || state.strings.joinfailed);
        }

        return payload;
    }

    function setStatus(message) {
        if (state.statusNode) {
            state.statusNode.textContent = message;
        }
    }

    function updateMicButton() {
        if (!state.micButton) {
            return;
        }

        if (!state.audioReady) {
            state.micButton.textContent = state.strings.micoff;
            state.micButton.disabled = true;
            return;
        }

        const enabled = state.localmicenabled && !state.forcemuted;
        state.micButton.textContent = enabled ? state.strings.micon : state.strings.micoff;
        state.micButton.disabled = state.forcemuted;
    }

    function updateMuteAllButton() {
        if (!state.muteAllButton) {
            return;
        }

        state.muteAllButton.textContent = state.muteall ? state.strings.mutealloff : state.strings.muteallon;
    }

    function updateHandRaiseButton() {
        if (!state.handRaiseButton) {
            return;
        }
        state.handRaiseButton.textContent = state.myhandraised ? state.strings.lowerhand : state.strings.raisehand;
    }

    function getParticipantName(userid) {
        if (userid === state.userid) {
            return state.displayname || ('User ' + userid);
        }
        const participant = state.participants.get(userid);
        return participant ? participant.name : ('User ' + userid);
    }

    function applyLocalMicState() {
        if (!state.audioReady) {
            updateMicButton();
            setStatus(state.strings.audionotsupported);
            return;
        }

        if (state.localstream) {
            const enabled = state.localmicenabled && !state.forcemuted;
            state.localstream.getAudioTracks().forEach(function(track) {
                track.enabled = enabled;
            });
        }

        updateMicButton();

        if (state.forcemuted) {
            setStatus(state.strings.forcemuted);
        } else if (state.muteall && !state.isteacher && state.allowedspeakerid === state.userid) {
            setStatus(state.strings.youcanspeak);
        } else if (state.ready) {
            setStatus(state.strings.connected);
        }
    }

    function applyRoomModerationState(muteall, spotlightuserid, allowedspeakerid) {
        state.muteall = Boolean(muteall);
        state.spotlightuserid = Number(spotlightuserid) > 0 ? Number(spotlightuserid) : 0;
        state.allowedspeakerid = Number(allowedspeakerid) > 0 ? Number(allowedspeakerid) : 0;
        state.forcemuted = state.muteall && !state.isteacher && state.userid !== state.allowedspeakerid;
        applyLocalMicState();
        updateMuteAllButton();
    }

    function setupToolbar() {
        state.statusNode = document.getElementById('vrclassroom-status');
        state.micButton = document.getElementById('vrclassroom-toggle-mic');
        state.handRaiseButton = document.getElementById('vrclassroom-toggle-handraise');
        state.muteAllButton = document.getElementById('vrclassroom-toggle-muteall');

        if (state.micButton) {
            state.micButton.addEventListener('click', function() {
                if (!state.audioReady || state.forcemuted) {
                    return;
                }
                state.localmicenabled = !state.localmicenabled;
                applyLocalMicState();
            });
        }

        if (state.muteAllButton) {
            state.muteAllButton.addEventListener('click', async function() {
                if (state.pendingMuteToggle) {
                    return;
                }

                state.pendingMuteToggle = true;
                state.muteAllButton.disabled = true;
                try {
                    const result = await post('set_muteall', {
                        mute: state.muteall ? 0 : 1,
                    });
                    applyRoomModerationState(result.muteall, state.spotlightuserid, state.allowedspeakerid);
                } catch (error) {
                    setStatus(error.message);
                } finally {
                    state.pendingMuteToggle = false;
                    state.muteAllButton.disabled = false;
                }
            });
        }

        if (state.handRaiseButton) {
            state.handRaiseButton.addEventListener('click', async function() {
                if (state.pendingHandRaiseToggle) {
                    return;
                }
                state.pendingHandRaiseToggle = true;
                state.handRaiseButton.disabled = true;
                try {
                    const result = await post('set_handraise', {
                        raised: state.myhandraised ? 0 : 1,
                    });
                    state.myhandraised = Boolean(result.raised);
                    updateHandRaiseButton();
                } catch (error) {
                    setStatus(error.message);
                } finally {
                    state.pendingHandRaiseToggle = false;
                    state.handRaiseButton.disabled = false;
                }
            });
        }

        updateMicButton();
        updateMuteAllButton();
        updateHandRaiseButton();
    }

    function waitForAframe() {
        return new Promise(function(resolve, reject) {
            const started = Date.now();
            const timer = window.setInterval(function() {
                if (window.AFRAME) {
                    window.clearInterval(timer);
                    resolve();
                    return;
                }

                if (Date.now() - started > 12000) {
                    window.clearInterval(timer);
                    reject(new Error('A-Frame did not load in time.'));
                }
            }, 100);
        });
    }

    function buildStaticClassroomHtml() {
        let desks = '';
        for (let i = 0; i < BASE_STUDENT_SEATS.length; i++) {
            const seat = BASE_STUDENT_SEATS[i];
            const deskz = seat.z - 0.65;
            const chairz = seat.z + 0.55;
            desks += '' +
                '<a-box color="#b78f62" width="2.1" height="0.1" depth="1.0" position="' + seat.x + ' 0.78 ' + deskz + '"></a-box>' +
                '<a-box color="#6f5638" width="0.08" height="0.7" depth="0.08" position="' + (seat.x - 0.95) + ' 0.39 ' + (deskz - 0.4) + '"></a-box>' +
                '<a-box color="#6f5638" width="0.08" height="0.7" depth="0.08" position="' + (seat.x + 0.95) + ' 0.39 ' + (deskz - 0.4) + '"></a-box>' +
                '<a-box color="#6f5638" width="0.08" height="0.7" depth="0.08" position="' + (seat.x - 0.95) + ' 0.39 ' + (deskz + 0.4) + '"></a-box>' +
                '<a-box color="#6f5638" width="0.08" height="0.7" depth="0.08" position="' + (seat.x + 0.95) + ' 0.39 ' + (deskz + 0.4) + '"></a-box>' +
                '<a-box color="#445264" width="0.9" height="0.08" depth="0.9" position="' + seat.x + ' 0.45 ' + chairz + '"></a-box>' +
                '<a-box color="#445264" width="0.9" height="0.75" depth="0.08" position="' + seat.x + ' 0.85 ' + (chairz + 0.38) + '"></a-box>';
        }

        return '' +
            '<a-sky color="#d8e7f8"></a-sky>' +
            '<a-plane color="#d8dddf" width="24" height="32" rotation="-90 0 0"></a-plane>' +
            '<a-box color="#e7edf4" width="24" height="5.5" depth="0.2" position="0 2.75 10"></a-box>' +
            '<a-box color="#f1f5f9" width="24" height="5.5" depth="0.2" position="0 2.75 -15"></a-box>' +
            '<a-box color="#e7edf4" width="0.2" height="5.5" depth="32" position="-12 2.75 -2.5"></a-box>' +
            '<a-box color="#e7edf4" width="0.2" height="5.5" depth="32" position="12 2.75 -2.5"></a-box>' +
            '<a-box color="#f8fafc" width="24" height="0.1" depth="32" position="0 5.5 -2.5"></a-box>' +
            '<a-entity light="type: ambient; intensity: 0.72"></a-entity>' +
            '<a-entity light="type: directional; intensity: 0.8" position="-4 8 6"></a-entity>' +
            '<a-box color="#f7fbff" width="8" height="2.2" depth="0.08" position="0 2.55 -14.15"></a-box>' +
            '<a-box color="#eef4ff" width="4.2" height="1.8" depth="0.08" position="0 3.85 -14.2"></a-box>' +
            '<a-box color="#b88b62" width="3.8" height="0.12" depth="1.4" position="0 0.85 -10.45"></a-box>' +
            '<a-box color="#7b6048" width="3.8" height="1.0" depth="0.08" position="0 0.46 -11.1"></a-box>' +
            '<a-box color="#4e6072" width="1.0" height="0.1" depth="1.0" position="0 0.5 -9.55"></a-box>' +
            '<a-box color="#4e6072" width="1.0" height="0.9" depth="0.1" position="0 0.95 -10.0"></a-box>' +
            desks;
    }

    function createScene() {
        const root = document.getElementById('vrclassroom-root');
        if (!root) {
            throw new Error('Missing VR root element.');
        }

        const movementControls = state.isteacher ? ' wasd-controls="acceleration: 45"' : '';
        const teacherClickControls = state.canmoderate ? ' cursor="rayOrigin: mouse" raycaster="objects: .vrclassroom-clickable"' : '';

        root.innerHTML = '' +
            '<a-scene embedded renderer="antialias: true; colorManagement: true" vr-mode-ui="enabled: false">' +
                buildStaticClassroomHtml() +
                '<a-entity id="vrclassroom-rig" position="0 1.6 0" rotation="0 0 0">' +
                    '<a-entity id="vrclassroom-camera" camera look-controls' + movementControls + teacherClickControls + '></a-entity>' +
                '</a-entity>' +
            '</a-scene>';

        state.scene = root.querySelector('a-scene');
        state.rig = document.getElementById('vrclassroom-rig');
        state.camera = document.getElementById('vrclassroom-camera');

        if (!state.scene || !state.rig || !state.camera) {
            throw new Error('Unable to initialize scene entities.');
        }
    }

    function applyLocalRolePose(force) {
        if (!state.rig || !state.rig.object3D) {
            return;
        }

        if (state.isteacher) {
            if (!state.localPoseInitialized || force) {
                state.rig.object3D.position.set(TEACHER_POSE.x, TEACHER_POSE.y, TEACHER_POSE.z);
                state.rig.object3D.rotation.set(0, TEACHER_POSE.ry * Math.PI / 180, 0);
                state.localPoseInitialized = true;
            }
            return;
        }

        if (!Number.isInteger(state.myseatindex)) {
            return;
        }

        const pose = getStudentCameraPose(state.myseatindex);
        state.rig.object3D.position.set(pose.x, pose.y, pose.z);
        state.rig.object3D.rotation.set(0, pose.ry * Math.PI / 180, 0);
        state.localPoseInitialized = true;
    }

    function getLocalTransform() {
        if (!state.rig || !state.rig.object3D) {
            return {
                x: 0,
                y: 1.6,
                z: 0,
                ry: 0,
            };
        }

        const position = state.rig.object3D.position;
        const rotation = state.rig.object3D.rotation;

        return {
            x: Number(position.x.toFixed(4)),
            y: Number(position.y.toFixed(4)),
            z: Number(position.z.toFixed(4)),
            ry: Number((rotation.y * 180 / Math.PI).toFixed(4)),
        };
    }

    async function setSpotlightForUser(userid) {
        if (!state.canmoderate || state.pendingSpotlightToggle || !Number.isInteger(userid) || userid <= 0) {
            return;
        }

        state.pendingSpotlightToggle = true;
        try {
            const result = await post('set_spotlight', {userid: userid});
            applyRoomModerationState(state.muteall, result.spotlightuserid, result.allowedspeakerid);
            state.participants.forEach(function(participant) {
                updateAvatar(participant);
            });
            if (state.spotlightuserid > 0) {
                setStatus(state.strings.spotlightactive + ': ' + getParticipantName(state.spotlightuserid));
            } else {
                setStatus(state.strings.spotlightcleared);
            }
        } catch (error) {
            setStatus(error.message);
        } finally {
            state.pendingSpotlightToggle = false;
        }
    }

    function createAvatar(participant) {
        const avatar = document.createElement('a-entity');
        avatar.id = 'vrclassroom-avatar-' + participant.userid;
        avatar.dataset.userid = String(participant.userid);
        if (!participant.isteacher) {
            avatar.classList.add('vrclassroom-clickable');
        }

        const body = document.createElement('a-cylinder');
        body.setAttribute('radius', participant.isteacher ? '0.25' : '0.21');
        body.setAttribute('height', participant.isteacher ? '1.25' : '1.0');
        body.setAttribute('position', participant.isteacher ? '0 0.62 0' : '0 0.5 0');
        body.setAttribute('color', participant.isteacher ? '#7b5ea5' : '#2e74b9');

        const head = document.createElement('a-sphere');
        head.setAttribute('radius', '0.2');
        head.setAttribute('position', participant.isteacher ? '0 1.36 0' : '0 1.15 0');
        head.setAttribute('color', '#f4d8b4');

        const label = document.createElement('a-text');
        label.setAttribute('value', participant.name);
        label.setAttribute('align', 'center');
        label.setAttribute('position', participant.isteacher ? '0 1.85 0' : '0 1.65 0');
        label.setAttribute('width', '5');
        label.setAttribute('color', '#1d2d40');

        avatar.appendChild(body);
        avatar.appendChild(head);
        avatar.appendChild(label);

        const hand = document.createElement('a-text');
        hand.className = 'vrclassroom-hand';
        hand.setAttribute('value', '');
        hand.setAttribute('align', 'center');
        hand.setAttribute('position', participant.isteacher ? '0 2.2 0' : '0 2.0 0');
        hand.setAttribute('width', '4');
        hand.setAttribute('color', '#cc7a00');
        avatar.appendChild(hand);

        const spotlight = document.createElement('a-ring');
        spotlight.className = 'vrclassroom-spotlight';
        spotlight.setAttribute('radius-inner', participant.isteacher ? '0.35' : '0.3');
        spotlight.setAttribute('radius-outer', participant.isteacher ? '0.42' : '0.38');
        spotlight.setAttribute('rotation', '-90 0 0');
        spotlight.setAttribute('position', '0 0.02 0');
        spotlight.setAttribute('color', '#f7b500');
        spotlight.setAttribute('visible', 'false');
        avatar.appendChild(spotlight);

        if (state.canmoderate && !participant.isteacher) {
            avatar.addEventListener('click', function() {
                const targetuserid = Number(avatar.dataset.userid || 0);
                if (targetuserid > 0) {
                    setSpotlightForUser(targetuserid);
                }
            });
            body.classList.add('vrclassroom-clickable');
            head.classList.add('vrclassroom-clickable');
            label.classList.add('vrclassroom-clickable');
            hand.classList.add('vrclassroom-clickable');
            spotlight.classList.add('vrclassroom-clickable');
        }

        state.scene.appendChild(avatar);
        return avatar;
    }

    function updateAvatar(participant) {
        let avatar = state.avatars.get(participant.userid);
        if (!avatar) {
            avatar = createAvatar(participant);
            state.avatars.set(participant.userid, avatar);
        }

        if (avatar.object3D) {
            avatar.object3D.position.set(participant.x, 0, participant.z);
            avatar.object3D.rotation.set(0, participant.ry * Math.PI / 180, 0);
        }

        const isspotlight = Number(participant.userid) === Number(state.spotlightuserid);
        const body = avatar.querySelector('a-cylinder');
        if (body) {
            const color = participant.isteacher ? '#7b5ea5' : (isspotlight ? '#f7b500' : '#2e74b9');
            body.setAttribute('color', color);
        }

        const hand = avatar.querySelector('.vrclassroom-hand');
        if (hand) {
            hand.setAttribute('value', participant.handraised ? 'Hand raised' : '');
        }

        const spotlight = avatar.querySelector('.vrclassroom-spotlight');
        if (spotlight) {
            spotlight.setAttribute('visible', isspotlight ? 'true' : 'false');
        }
    }

    function removeAvatar(userid) {
        const avatar = state.avatars.get(userid);
        if (!avatar) {
            return;
        }

        avatar.remove();
        state.avatars.delete(userid);
    }

    async function ensureLocalMedia() {
        if (state.localstream) {
            return;
        }

        state.localstream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
            video: false,
        });

        state.audioReady = true;
        applyLocalMicState();
    }

    function attachRemoteStream(remoteid, stream) {
        const peer = state.peers.get(remoteid);
        if (!peer) {
            return;
        }

        if (!peer.audio) {
            const audio = document.createElement('audio');
            audio.autoplay = true;
            audio.playsInline = true;
            audio.volume = 0;
            audio.dataset.userid = String(remoteid);
            document.body.appendChild(audio);
            peer.audio = audio;
        }

        peer.audio.srcObject = stream;
        peer.audio.play().catch(function() {
            // Autoplay may require user interaction in some browsers.
        });
    }

    function closePeer(remoteid) {
        const peer = state.peers.get(remoteid);
        if (!peer) {
            return;
        }

        if (peer.pc) {
            try {
                peer.pc.onicecandidate = null;
                peer.pc.ontrack = null;
                peer.pc.onconnectionstatechange = null;
                peer.pc.close();
            } catch (e) {
                // Ignore close errors.
            }
        }

        if (peer.audio) {
            peer.audio.srcObject = null;
            peer.audio.remove();
        }

        state.peers.delete(remoteid);
    }

    function flushQueuedCandidates(peer) {
        if (!peer.pendingcandidates.length || !peer.pc.remoteDescription) {
            return Promise.resolve();
        }

        const tasks = peer.pendingcandidates.splice(0).map(function(candidate) {
            return peer.pc.addIceCandidate(candidate).catch(function() {
                // Ignore stale candidates.
            });
        });

        return Promise.all(tasks).then(function() {
            return undefined;
        });
    }

    function enqueuePeerTask(remoteid, task) {
        const peer = state.peers.get(remoteid);
        if (!peer) {
            return Promise.resolve();
        }

        peer.queue = peer.queue.then(task).catch(function() {
            // Keep queue chain alive.
        });

        return peer.queue;
    }

    async function sendSignal(touserid, type, payload) {
        await post('send_signal', {
            touserid: touserid,
            type: type,
            payload: payload || '',
        });
    }

    async function ensurePeer(remoteid, shouldinitiate) {
        if (!state.audioReady) {
            return null;
        }

        if (remoteid === state.userid) {
            return null;
        }

        if (state.peers.has(remoteid)) {
            return state.peers.get(remoteid);
        }

        const peer = {
            pc: null,
            audio: null,
            pendingcandidates: [],
            queue: Promise.resolve(),
        };
        state.peers.set(remoteid, peer);

        const pc = new RTCPeerConnection({iceServers: state.iceservers});
        peer.pc = pc;

        state.localstream.getTracks().forEach(function(track) {
            pc.addTrack(track, state.localstream);
        });

        pc.onicecandidate = function(event) {
            if (!event.candidate) {
                return;
            }

            sendSignal(remoteid, 'candidate', JSON.stringify(event.candidate)).catch(function() {
                // Ignore transient signaling failures.
            });
        };

        pc.ontrack = function(event) {
            if (event.streams && event.streams[0]) {
                attachRemoteStream(remoteid, event.streams[0]);
            }
        };

        pc.onconnectionstatechange = function() {
            const disconnected = ['disconnected', 'failed', 'closed'];
            if (disconnected.indexOf(pc.connectionState) !== -1) {
                closePeer(remoteid);
                removeAvatar(remoteid);
                state.participants.delete(remoteid);
            }
        };

        if (shouldinitiate) {
            enqueuePeerTask(remoteid, async function() {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await sendSignal(remoteid, 'offer', JSON.stringify(offer));
            });
        }

        return peer;
    }

    async function handleSignal(signal) {
        if (!state.audioReady) {
            return;
        }

        const remoteid = Number(signal.fromuserid);
        if (!remoteid || remoteid === state.userid) {
            return;
        }

        if (signal.type === 'leave') {
            closePeer(remoteid);
            removeAvatar(remoteid);
            state.participants.delete(remoteid);
            return;
        }

        const peer = await ensurePeer(remoteid, false);
        if (!peer) {
            return;
        }

        enqueuePeerTask(remoteid, async function() {
            if (signal.type === 'offer') {
                const remoteOffer = JSON.parse(signal.payload);
                await peer.pc.setRemoteDescription(new RTCSessionDescription(remoteOffer));
                await flushQueuedCandidates(peer);
                const answer = await peer.pc.createAnswer();
                await peer.pc.setLocalDescription(answer);
                await sendSignal(remoteid, 'answer', JSON.stringify(answer));
                return;
            }

            if (signal.type === 'answer') {
                const remoteAnswer = JSON.parse(signal.payload);
                await peer.pc.setRemoteDescription(new RTCSessionDescription(remoteAnswer));
                await flushQueuedCandidates(peer);
                return;
            }

            if (signal.type === 'candidate') {
                const candidate = new RTCIceCandidate(JSON.parse(signal.payload));
                if (peer.pc.remoteDescription && peer.pc.remoteDescription.type) {
                    await peer.pc.addIceCandidate(candidate).catch(function() {
                        // Ignore stale candidates.
                    });
                } else {
                    peer.pendingcandidates.push(candidate);
                }
            }
        });
    }

    function ensurePeerMesh() {
        if (!state.audioReady) {
            return;
        }

        state.participants.forEach(function(participant, userid) {
            if (userid === state.userid) {
                return;
            }
            const shouldinitiate = state.userid < userid;
            ensurePeer(userid, shouldinitiate).catch(function() {
                // Ignore individual connection failures.
            });
        });
    }

    function normalizeParticipantPose(participant) {
        if (participant.isteacher) {
            return participant;
        }

        const seat = getStudentSeatPose(participant.seatindex);
        return Object.assign({}, participant, {
            x: seat.x,
            y: 1.22,
            z: seat.z,
            ry: seat.ry,
        });
    }

    function syncParticipants(participants) {
        const seen = new Set();

        participants.forEach(function(participant) {
            const userid = Number(participant.userid);
            if (!userid) {
                return;
            }

            seen.add(userid);

            if (userid === state.userid) {
                return;
            }

            const participantmodel = normalizeParticipantPose({
                userid: userid,
                name: participant.name || ('User ' + userid),
                x: Number(participant.x || 0),
                y: Number(participant.y || 1.6),
                z: Number(participant.z || 0),
                ry: Number(participant.ry || 0),
                canmoderate: Boolean(participant.canmoderate),
                isteacher: Boolean(participant.isteacher),
                seatindex: Number.isInteger(participant.seatindex) ? participant.seatindex : null,
                handraised: Boolean(participant.handraised),
            });

            state.participants.set(userid, participantmodel);
            updateAvatar(participantmodel);
        });

        const toRemove = [];
        state.participants.forEach(function(unused, userid) {
            if (!seen.has(userid)) {
                toRemove.push(userid);
            }
        });

        toRemove.forEach(function(userid) {
            state.participants.delete(userid);
            removeAvatar(userid);
            closePeer(userid);
        });

        ensurePeerMesh();
    }

    async function heartbeat() {
        if (state.pendingHeartbeat) {
            return;
        }

        state.pendingHeartbeat = true;

        try {
            if (!state.isteacher) {
                applyLocalRolePose(true);
            }

            const transform = getLocalTransform();
            const response = await post('heartbeat', transform);
            applyRoomModerationState(response.muteall, response.spotlightuserid, response.allowedspeakerid);

            if (typeof response.myisteacher !== 'undefined') {
                state.isteacher = Boolean(response.myisteacher);
            }
            if (typeof response.myseatindex !== 'undefined') {
                state.myseatindex = response.myseatindex === null ? null : Number(response.myseatindex);
            }
            applyLocalRolePose(!state.isteacher);

            if (typeof response.myhandraised !== 'undefined') {
                state.myhandraised = Boolean(response.myhandraised);
                updateHandRaiseButton();
            }

            syncParticipants(response.participants || []);
            state.ready = true;

            if (!state.audioReady) {
                setStatus(state.strings.audionotsupported);
            } else if (state.forcemuted) {
                setStatus(state.strings.forcemuted);
            } else if (state.canmoderate) {
                setStatus(state.strings.spotlighthint);
            } else if (state.muteall && state.allowedspeakerid === state.userid) {
                setStatus(state.strings.youcanspeak);
            } else {
                setStatus(state.strings.connected);
            }
        } catch (error) {
            setStatus(error.message);
        } finally {
            state.pendingHeartbeat = false;
        }
    }

    async function pollSignals() {
        if (!state.audioReady || state.pendingSignalPoll) {
            return;
        }

        state.pendingSignalPoll = true;
        try {
            const response = await post('poll_signals', {});
            const signals = response.signals || [];
            for (let i = 0; i < signals.length; i++) {
                await handleSignal(signals[i]);
            }
        } catch (error) {
            setStatus(error.message);
        } finally {
            state.pendingSignalPoll = false;
        }
    }

    function applySpatialAudio() {
        if (!state.audioReady || !state.rig || !state.rig.object3D) {
            return;
        }

        const listenerPosition = state.rig.object3D.position;
        const radius = Math.max(state.spatialradius > 0 ? state.spatialradius : 8, 8);

        state.peers.forEach(function(peer, userid) {
            if (!peer.audio) {
                return;
            }

            const participant = state.participants.get(userid);
            if (!participant) {
                peer.audio.volume = 0;
                return;
            }

            const dx = participant.x - listenerPosition.x;
            const dy = participant.y - listenerPosition.y;
            const dz = participant.z - listenerPosition.z;
            const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);

            let gain = 1 - (distance / radius);
            if (gain < 0) {
                gain = 0;
            }

            peer.audio.volume = gain * gain;
        });
    }

    function stopAll() {
        state.timers.forEach(function(timer) {
            window.clearInterval(timer);
        });
        state.timers = [];

        if (state.localstream) {
            state.localstream.getTracks().forEach(function(track) {
                track.stop();
            });
            state.localstream = null;
        }

        const peerids = Array.from(state.peers.keys());
        peerids.forEach(function(userid) {
            closePeer(userid);
        });
    }

    function leaveRoom() {
        if (state.leftroom) {
            return;
        }

        state.leftroom = true;

        fetch(state.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: buildRequestBody('leave', {}),
        }).catch(function() {
            // Ignore unload network errors.
        });
    }

    async function boot() {
        await waitForAframe();
        createScene();
        applyLocalRolePose(true);
        applyRoomModerationState(state.muteall, state.spotlightuserid, state.allowedspeakerid);

        setStatus(state.strings.connecting);

        if (state.audioSupported) {
            try {
                await ensureLocalMedia();
            } catch (error) {
                state.audioReady = false;
                updateMicButton();
            }
        }

        await heartbeat();
        if (state.audioReady) {
            await pollSignals();
        } else {
            setStatus(state.strings.audionotsupported);
        }

        state.timers.push(window.setInterval(heartbeat, state.heartbeatinterval));
        if (state.audioReady) {
            state.timers.push(window.setInterval(pollSignals, state.signalinterval));
            state.timers.push(window.setInterval(applySpatialAudio, 200));
        }
    }

    function init(config) {
        state = {
            cmid: Number(config.cmid),
            instanceid: Number(config.instanceid),
            userid: Number(config.userid),
            displayname: config.displayname || '',
            ajaxurl: config.ajaxurl,
            sesskey: config.sesskey,
            canmoderate: Boolean(config.canmoderate),
            isteacher: Boolean(config.myisteacher),
            myseatindex: config.myseatindex === null ? null : Number(config.myseatindex),
            spatialradius: Number(config.spatialradius || 8),
            muteall: Boolean(config.muteall),
            spotlightuserid: Number(config.spotlightuserid || 0),
            allowedspeakerid: Number(config.allowedspeakerid || 0),
            myhandraised: Boolean(config.myhandraised),
            iceservers: Array.isArray(config.iceservers) ? config.iceservers : [{urls: 'stun:stun.l.google.com:19302'}],
            heartbeatinterval: Number(config.heartbeatinterval || 1000),
            signalinterval: Number(config.signalinterval || 700),
            audioSupported: Boolean(window.RTCPeerConnection && navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            audioReady: false,
            strings: Object.assign({
                micon: 'Mic on',
                micoff: 'Mic off',
                muteallon: 'Mute all',
                mutealloff: 'Unmute all',
                forcemuted: 'You are muted by lecturer.',
                joinfailed: 'Could not join room.',
                connecting: 'Connecting...',
                connected: 'Connected',
                audionotsupported: 'Audio is not supported in this browser.',
                raisehand: 'Raise hand',
                lowerhand: 'Lower hand',
                spotlightactive: 'Spotlight active',
                spotlightcleared: 'Spotlight cleared',
                youcanspeak: 'You are spotlighted and can speak',
                spotlighthint: 'Click a student avatar to spotlight and allow speaking',
            }, config.strings || {}),
            scene: null,
            rig: null,
            camera: null,
            statusNode: null,
            micButton: null,
            handRaiseButton: null,
            muteAllButton: null,
            localstream: null,
            localmicenabled: true,
            forcemuted: false,
            pendingHeartbeat: false,
            pendingSignalPoll: false,
            pendingMuteToggle: false,
            pendingHandRaiseToggle: false,
            pendingSpotlightToggle: false,
            ready: false,
            leftroom: false,
            localPoseInitialized: false,
            timers: [],
            peers: new Map(),
            participants: new Map(),
            avatars: new Map(),
        };

        setupToolbar();

        boot().catch(function(error) {
            setStatus(error.message || state.strings.joinfailed);
            stopAll();
        });

        window.addEventListener('beforeunload', function() {
            leaveRoom();
            stopAll();
        });

        window.addEventListener('pagehide', function() {
            leaveRoom();
            stopAll();
        });
    }

    return {
        init: init,
    };
});
