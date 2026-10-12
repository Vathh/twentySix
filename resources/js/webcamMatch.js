import Pusher from 'pusher-js';

const BOARD_KEY = 'webcam-board-device';
const FACE_KEY = 'webcam-face-device';

function normalizeState(payload) {
    if (payload == null) {
        return null;
    }
    if (typeof payload === 'string') {
        try {
            payload = JSON.parse(payload);
        } catch {
            return null;
        }
    }
    if (payload.state && typeof payload.state === 'object') {
        return payload.state;
    }
    return payload;
}

export function registerWebcamMatch(Alpine) {
    Alpine.data('webcamMatch', (config) => ({
        state: config.state,
        userId: config.userId,
        csrf: config.csrf,
        devices: [],
        boardDeviceId: '',
        faceDeviceId: '',
        camError: '',
        error: '',
        busy: false,
        connection: 'idle',
        checkoutOpen: false,
        bustOpen: false,
        checkoutDarts: 3,
        input: '',
        localReady: false,
        socketReady: false,
        offering: false,
        pc: null,
        pusher: null,
        pendingIce: [],
        pendingOffer: null,
        remoteRoles: {},
        remoteTrackCount: 0,
        localBoardStream: null,
        localFaceStream: null,
        pollTimer: null,

        get isOfferer() {
            return this.userId < config.opponentId;
        },

        get me() {
            return (this.state.players || []).find((player) => player.userId === this.userId) ?? null;
        },

        get opponent() {
            return (this.state.players || []).find((player) => player.userId !== this.userId) ?? null;
        },

        get myTurn() {
            return this.state.status === 'live' && this.state.currentTurnUserId === this.userId;
        },

        get canUndo() {
            return this.state.status !== 'lobby' && this.state.lastVisitUserId === this.userId;
        },

        get legVisits() {
            return (this.state.visits || []).filter((visit) => visit.legNumber === this.state.legNumber);
        },

        get sameCamera() {
            return this.boardDeviceId !== '' && this.boardDeviceId === this.faceDeviceId;
        },

        async init() {
            this.connectSocket();
            await this.loadDevices();
            await this.startLocal();
            this.pollTimer = setInterval(() => this.refreshState(), 8000);
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
            this.stopLocal();
            this.closePc();
            if (this.pusher) {
                this.pusher.disconnect();
                this.pusher = null;
            }
        },

        playerName(userId) {
            return (this.state.players || []).find((player) => player.userId === userId)?.name ?? 'Gracz';
        },

        visitLabel(visit) {
            if (visit.bust) {
                return 'bust';
            }
            if (visit.closedLeg) {
                return `${visit.score} · leg`;
            }
            return String(visit.score);
        },

        async loadDevices() {
            if (!navigator.mediaDevices?.getUserMedia) {
                this.camError = 'Ta przeglądarka nie obsługuje kamer.';
                return;
            }

            let probe = null;
            try {
                probe = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            } catch {
                this.camError = 'Przeglądarka nie dostała dostępu do kamery.';
                return;
            } finally {
                probe?.getTracks().forEach((track) => track.stop());
            }

            const all = await navigator.mediaDevices.enumerateDevices();
            this.devices = all
                .filter((device) => device.kind === 'videoinput')
                .map((device, index) => ({
                    id: device.deviceId,
                    label: device.label || `Kamera ${index + 1}`,
                }));

            const ids = this.devices.map((device) => device.id);
            const storedBoard = localStorage.getItem(BOARD_KEY);
            const storedFace = localStorage.getItem(FACE_KEY);
            this.boardDeviceId = ids.includes(storedBoard) ? storedBoard : (ids[0] || '');
            this.faceDeviceId = ids.includes(storedFace) ? storedFace : (ids[1] || ids[0] || '');
            if (this.devices.length < 2) {
                this.camError = 'Podłącz drugą kamerkę — jedną na tarczę, drugą na siebie.';
            }
        },

        async onDeviceChange() {
            localStorage.setItem(BOARD_KEY, this.boardDeviceId);
            localStorage.setItem(FACE_KEY, this.faceDeviceId);
            this.closePc();
            await this.startLocal();
        },

        async startLocal() {
            this.stopLocal();
            this.camError = this.devices.length < 2
                ? 'Podłącz drugą kamerkę — jedną na tarczę, drugą na siebie.'
                : '';
            if (!this.boardDeviceId) {
                return;
            }

            try {
                this.localBoardStream = await this.openCamera(this.boardDeviceId, 'board');
                this.$refs.localBoard.srcObject = this.localBoardStream;
                if (this.faceDeviceId && this.faceDeviceId !== this.boardDeviceId) {
                    this.localFaceStream = await this.openCamera(this.faceDeviceId, 'face');
                    this.$refs.localFace.srcObject = this.localFaceStream;
                } else if (this.devices.length >= 2) {
                    this.camError = 'Wybierz dwie różne kamerki: tarczę i siebie.';
                }
            } catch {
                this.camError = 'Nie udało się włączyć wybranej kamerki.';
                this.localReady = false;
                return;
            }

            this.localReady = Boolean(this.localBoardStream && this.localFaceStream);
            if (!this.localReady) {
                return;
            }
            if (this.pendingOffer) {
                const offer = this.pendingOffer;
                this.pendingOffer = null;
                await this.acceptOffer(offer);
            }
            await this.maybeOffer();
        },

        async openCamera(deviceId, role) {
            const ideal = {
                deviceId: { exact: deviceId },
                width: { ideal: role === 'board' ? 1280 : 640 },
                height: { ideal: role === 'board' ? 720 : 360 },
            };
            try {
                return await navigator.mediaDevices.getUserMedia({ video: ideal, audio: false });
            } catch {
                return navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { ideal: deviceId } },
                    audio: false,
                });
            }
        },

        stopLocal() {
            this.localBoardStream?.getTracks().forEach((track) => track.stop());
            this.localFaceStream?.getTracks().forEach((track) => track.stop());
            this.localBoardStream = null;
            this.localFaceStream = null;
            this.localReady = false;
        },

        connectSocket() {
            if (!config.reverb?.key) {
                this.connection = 'offline';
                return;
            }

            const useTls = config.reverb.scheme === 'https';
            this.pusher = new Pusher(config.reverb.key, {
                cluster: 'reverb',
                wsHost: config.reverb.host,
                wsPort: config.reverb.port,
                wssPort: config.reverb.port,
                forceTLS: useTls,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
                authEndpoint: config.authEndpoint,
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                },
            });

            const channel = this.pusher.subscribe(`private-webcam-match.${config.matchId}`);
            channel.bind('pusher:subscription_succeeded', () => {
                this.socketReady = true;
                this.connection = 'live';
                this.maybeOffer();
            });
            channel.bind('pusher:subscription_error', () => {
                this.connection = 'error';
            });
            ['state', '.state'].forEach((name) => {
                channel.bind(name, (payload) => {
                    const next = normalizeState(payload);
                    if (next) {
                        this.state = next;
                    }
                });
            });
            ['signal', '.signal'].forEach((name) => {
                channel.bind(name, (payload) => this.onSignal(payload));
            });
        },

        async refreshState() {
            try {
                const response = await fetch(config.stateUrl, { headers: { Accept: 'application/json' } });
                if (response.ok) {
                    this.state = await response.json();
                }
            } catch {
                // wynik i tak wróci następnym razem
            }
        },

        async postJson(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Nie udało się zapisać.');
            }
            return data;
        },

        async markReady() {
            this.error = '';
            this.busy = true;
            try {
                this.state = await this.postJson(config.readyUrl);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        submitInput() {
            this.error = '';
            const score = Number.parseInt(this.input, 10);
            if (!Number.isFinite(score) || score < 0 || score > 180) {
                this.error = 'Suma wizyty musi być od 0 do 180.';
                return;
            }
            const before = this.me?.remaining ?? 0;
            if (score > before || before - score === 1) {
                this.bustOpen = true;
                return;
            }
            if (score === before) {
                this.checkoutDarts = 3;
                this.checkoutOpen = true;
                return;
            }
            this.sendVisit({
                score,
                bust: false,
                closedLeg: false,
                darts: 3,
                remainingAfter: before - score,
            });
        },

        confirmBust() {
            this.bustOpen = false;
            const before = this.me?.remaining ?? 0;
            this.sendVisit({
                score: 0,
                bust: true,
                closedLeg: false,
                darts: 3,
                remainingAfter: before,
            });
        },

        confirmCheckout() {
            const before = this.me?.remaining ?? 0;
            this.checkoutOpen = false;
            this.sendVisit({
                score: before,
                bust: false,
                closedLeg: true,
                darts: this.checkoutDarts,
                remainingAfter: 0,
            });
        },

        async sendVisit({ score, bust, closedLeg, darts, remainingAfter }) {
            const before = this.me?.remaining ?? 0;
            this.busy = true;
            this.error = '';
            try {
                this.state = await this.postJson(config.visitUrl, {
                    score,
                    remaining_before: before,
                    remaining_after: remainingAfter,
                    darts_in_visit: darts,
                    bust,
                    closed_leg: closedLeg,
                });
                this.input = '';
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        async undo() {
            this.busy = true;
            this.error = '';
            try {
                this.state = await this.postJson(config.undoUrl);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        async maybeOffer() {
            if (!this.isOfferer || !this.socketReady || !this.localReady || this.pc || this.offering) {
                return;
            }
            this.offering = true;
            try {
                await this.createOffer();
            } catch {
                this.connection = 'error';
                this.camError = this.camError || 'Nie udało się zestawić obrazu.';
            } finally {
                this.offering = false;
            }
        },

        async reconnectVideo() {
            this.pendingIce = [];
            this.pendingOffer = null;
            this.closePc();
            this.connection = 'connecting';
            if (this.isOfferer) {
                await this.maybeOffer();
                return;
            }
            try {
                await this.postSignal('restart', {});
            } catch (error) {
                this.error = error.message;
            }
        },

        ensurePc() {
            if (this.pc) {
                return;
            }
            this.remoteTrackCount = 0;
            this.pc = new RTCPeerConnection({ iceServers: config.iceServers || [] });
            this.localBoardStream?.getTracks().forEach((track) => this.pc.addTrack(track, this.localBoardStream));
            this.localFaceStream?.getTracks().forEach((track) => this.pc.addTrack(track, this.localFaceStream));
            this.pc.onicecandidate = (event) => {
                if (event.candidate) {
                    this.postSignal('ice', { candidate: event.candidate.toJSON() });
                }
            };
            this.pc.ontrack = (event) => this.attachRemote(event);
            this.pc.onconnectionstatechange = () => {
                const value = this.pc?.connectionState;
                if (value === 'connected') {
                    this.connection = 'live';
                } else if (value === 'failed' || value === 'disconnected') {
                    this.connection = 'error';
                }
            };
        },

        localRoles() {
            const boardTrack = this.localBoardStream?.getVideoTracks()[0];
            const roles = {};
            this.pc?.getTransceivers().forEach((transceiver) => {
                if (!transceiver.mid || !transceiver.sender.track) {
                    return;
                }
                roles[transceiver.mid] = transceiver.sender.track === boardTrack ? 'board' : 'face';
            });
            return roles;
        },

        async createOffer() {
            this.ensurePc();
            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);
            await this.postSignal('offer', {
                type: 'offer',
                sdp: this.pc.localDescription.sdp,
                roles: this.localRoles(),
            });
        },

        async onSignal(message) {
            if (!message || message.fromUserId === this.userId) {
                return;
            }
            const payload = message.payload || {};
            if (message.kind === 'offer') {
                if (!this.localReady) {
                    this.pendingOffer = payload;
                    return;
                }
                await this.acceptOffer(payload);
                return;
            }
            if (message.kind === 'restart' && this.isOfferer) {
                this.pendingIce = [];
                this.closePc();
                await this.maybeOffer();
                return;
            }
            if (message.kind === 'answer') {
                this.remoteRoles = payload.roles || this.remoteRoles;
                if (!this.pc) {
                    return;
                }
                await this.pc.setRemoteDescription({ type: 'answer', sdp: payload.sdp });
                await this.flushIce();
                return;
            }
            if (message.kind === 'ice' && payload.candidate) {
                if (!this.pc || !this.pc.remoteDescription) {
                    this.pendingIce.push(payload.candidate);
                    return;
                }
                try {
                    await this.pc.addIceCandidate(payload.candidate);
                } catch {
                    // stary kandydat po ponownym połączeniu
                }
            }
        },

        async acceptOffer(payload) {
            if (this.isOfferer) {
                return;
            }
            if (!this.localReady) {
                this.pendingOffer = payload;
                return;
            }
            const earlyIce = this.pendingIce.splice(0);
            this.closePc();
            this.pendingIce = earlyIce;
            this.remoteRoles = payload.roles || {};
            this.ensurePc();
            await this.pc.setRemoteDescription({ type: 'offer', sdp: payload.sdp });
            await this.flushIce();
            const answer = await this.pc.createAnswer();
            await this.pc.setLocalDescription(answer);
            await this.postSignal('answer', {
                type: 'answer',
                sdp: this.pc.localDescription.sdp,
                roles: this.localRoles(),
            });
        },

        async flushIce() {
            const queued = this.pendingIce.splice(0);
            for (const candidate of queued) {
                try {
                    await this.pc.addIceCandidate(candidate);
                } catch {
                    // pomijamy kandydata, który już nie pasuje
                }
            }
        },

        attachRemote(event) {
            const mid = event.transceiver?.mid;
            const role = (mid && this.remoteRoles[mid]) || (this.remoteTrackCount++ === 0 ? 'board' : 'face');
            const element = role === 'face' ? this.$refs.remoteFace : this.$refs.remoteBoard;
            if (!element) {
                return;
            }
            element.srcObject = event.streams?.[0] || new MediaStream([event.track]);
        },

        async postSignal(kind, payload) {
            await this.postJson(config.signalUrl, { kind, payload });
        },

        closePc() {
            this.pc?.close();
            this.pc = null;
            this.remoteRoles = {};
            if (this.$refs.remoteBoard) {
                this.$refs.remoteBoard.srcObject = null;
            }
            if (this.$refs.remoteFace) {
                this.$refs.remoteFace.srcObject = null;
            }
        },
    }));
}
