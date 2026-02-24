# mod_vrclassroom (prototype)

This activity module provides an in-browser A-Frame room with:

- per-activity opening and optional closing time
- live participant avatars
- real-time WebRTC audio
- spatial audio attenuation by distance
- lecturer moderation (`Mute all students`)
- student hand raise button (live in-room state)
- attendance reporting (join time, leave time, and duration per student session)

## Important architecture notes

- Position updates and WebRTC signaling are exchanged through Moodle AJAX requests.
- Audio is peer-to-peer mesh WebRTC.
- A-Frame is bundled locally at `mod/vrclassroom/js/aframe-1.6.0.min.js`.
- For reliable production use (NAT/firewall), configure TURN servers in:
  - Site administration -> Plugins -> Activity modules -> VR classroom -> `ICE servers JSON`

Default ICE JSON:

```json
[{"urls":"stun:stun.l.google.com:19302"}]
```

Example with TURN:

```json
[
  {"urls":"stun:stun.l.google.com:19302"},
  {
    "urls":"turn:turn.example.com:3478",
    "username":"turn-user",
    "credential":"turn-password"
  }
]
```

## Current scope

This MVP is intended for around 10-30 participants per room. For larger cohorts, move signaling/presence to WebSocket infrastructure and use an SFU media backend.
