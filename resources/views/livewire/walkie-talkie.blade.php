<div class="flex flex-col min-h-screen bg-gray-100 relative" x-data="walkieTalkie">
    <!-- Header -->
    <header class="flex items-center justify-between px-6 py-4 bg-white shadow">
        <div class="text-xl font-bold text-indigo-700">LaraTalkie</div>
        <div class="text-gray-700 font-medium">{{ $username }}</div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="ml-4 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Cerrar
                sesión</button>
        </form>
    </header>

    <!-- Main -->
    <main class="flex-1 flex flex-col items-center justify-center">
        <div class="mb-4">
            @if($isReceiving && $talkingUser)
                <div class="text-indigo-700 font-semibold mb-2">{{ $talkingUser }} está hablando...</div>
            @elseif($isTalking)
                <div class="text-green-600 font-semibold mb-2">Transmitiendo (máx 1 min)...</div>
            @endif
            <div x-show="isRecording" class="text-green-600 font-semibold mb-2" style="display: none;">Transmitiendo...
            </div>
        </div>

        <button id="mic-btn"
            class="w-32 h-32 bg-indigo-600 text-white rounded-full flex items-center justify-center shadow-lg text-4xl hover:bg-indigo-700 focus:outline-none disabled:bg-gray-400 disabled:cursor-not-allowed transform active:scale-95 transition-transform"
            :class="{'bg-red-600 hover:bg-red-700': isRecording}" @mousedown.prevent="startRecording"
            @mouseup.prevent="stopRecording" @mouseleave="stopRecording" @touchstart.prevent="startRecording"
            @touchend.prevent="stopRecording" :disabled="isReceiving">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                class="w-16 h-16">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 18v.01M8 21h8a2 2 0 002-2v-7a6 6 0 10-12 0v7a2 2 0 002 2z" />
            </svg>
        </button>
    </main>

    <!-- Canal Slider -->
    <div class="absolute top-0 right-0 h-full flex flex-col items-center justify-center pr-4">
        <div class="flex flex-col items-center bg-white rounded-lg shadow p-2 mt-20 mb-20">
            <label for="channel-slider" class="mb-2 font-semibold text-indigo-700">Canal</label>
            <input id="channel-slider" type="range" min="1" max="8" step="1" wire:model.live="channel"
                class="w-1 h-48 rotate-180 accent-indigo-600"
                style="writing-mode: bt-lr; -webkit-appearance: slider-vertical;">
            <div class="mt-2 text-lg font-bold text-indigo-700">{{ $channel }}</div>
        </div>
    </div>

    <audio x-ref="remoteAudio" style="display: none;"></audio>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('walkieTalkie', () => ({
                channel: @entangle('channel'),
                isRecording: false,
                mediaRecorder: null,
                username: @js($username),
                prevChannel: null,
                isReceiving: @entangle('isReceiving'),

                // Audio Playback State
                remoteStreamId: null,
                mediaSource: null,
                sourceBuffer: null,
                audioQueue: [],
                isAppending: false,

                // Recording State
                currentStreamId: null,

                init() {
                    this.joinChannel();
                    this.$watch('channel', () => this.joinChannel());
                },

                joinChannel() {
                    if (window.Echo) {
                        if (this.prevChannel) {
                            window.Echo.leave('talkie.channel.' + this.prevChannel);
                        }
                        this.prevChannel = this.channel;

                        window.Echo.join('talkie.channel.' + this.channel)
                            .listen('.VoiceBroadcast', (e) => {
                                if (e.username !== this.username) {
                                    this.handleIncomingAudio(e);
                                }
                            });
                    }
                },

                handleIncomingAudio(e) {
                    if (this.remoteStreamId !== e.streamId) {
                        this.remoteStreamId = e.streamId;
                        this.setupMediaSource();
                    }

                    // Convert base64 to ArrayBuffer
                    const binaryString = atob(e.audio);
                    const len = binaryString.length;
                    const bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) {
                        bytes[i] = binaryString.charCodeAt(i);
                    }

                    this.audioQueue.push(bytes.buffer);
                    this.processAudioQueue();
                },

                setupMediaSource() {
                    this.audioQueue = [];
                    this.isAppending = false;

                    const audioEl = this.$refs.remoteAudio;

                    // Cleanup old MediaSource if needed
                    if (this.mediaSource && this.mediaSource.readyState === 'open') {
                        try { this.mediaSource.endOfStream(); } catch (e) { }
                    }

                    this.mediaSource = new MediaSource();
                    audioEl.src = URL.createObjectURL(this.mediaSource);

                    // Attempt to play
                    audioEl.play().catch(e => console.log('Autoplay blocked/waiting', e));

                    this.mediaSource.addEventListener('sourceopen', () => {
                        const mime = 'audio/webm; codecs=opus';
                        if (MediaSource.isTypeSupported(mime)) {
                            try {
                                this.sourceBuffer = this.mediaSource.addSourceBuffer(mime);
                                this.sourceBuffer.mode = 'sequence';
                                this.sourceBuffer.addEventListener('updateend', () => {
                                    this.isAppending = false;
                                    this.processAudioQueue();
                                });
                            } catch (e) {
                                console.error('Error adding SourceBuffer', e);
                            }
                        } else {
                            console.error('MIME type not supported: ' + mime);
                        }
                    });
                },

                processAudioQueue() {
                    if (this.isAppending || this.audioQueue.length === 0 || !this.sourceBuffer || this.sourceBuffer.updating) return;

                    if (this.mediaSource.readyState !== 'open') return;

                    this.isAppending = true;
                    const chunk = this.audioQueue.shift();
                    try {
                        this.sourceBuffer.appendBuffer(chunk);
                    } catch (err) {
                        console.error('Error appending buffer', err);
                        this.isAppending = false;
                    }
                },

                async startRecording() {
                    if (this.isRecording) return;
                    this.isRecording = true;
                    this.currentStreamId = Date.now().toString();

                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            alert("Microphone access is not supported in this context. Please ensure you are using HTTPS or localhost.");
                            this.isRecording = false;
                            return;
                        }

                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        const mimeType = 'audio/webm; codecs=opus';
                        const options = MediaRecorder.isTypeSupported(mimeType) ? { mimeType } : {};

                        this.mediaRecorder = new MediaRecorder(stream, options);

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data.size > 0) {
                                this.sendAudioChunk(e.data);
                            }
                        };

                        // Send chunks every 250ms
                        this.mediaRecorder.start(100);

                        // Max 1 minute
                        setTimeout(() => {
                            if (this.isRecording) this.stopRecording();
                        }, 60000);

                    } catch (err) {
                        console.error('Mic error:', err);
                        this.isRecording = false;
                    }
                },

                stopRecording() {
                    if (!this.isRecording) return;
                    this.isRecording = false;
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.mediaRecorder.stream?.getTracks().forEach(track => track.stop());
                },

                sendAudioChunk(blob) {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        const base64 = reader.result.split(',')[1];
                        fetch('/api/talkie/audio', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                            },
                            body: JSON.stringify({
                                channel: this.channel,
                                username: this.username,
                                audio: base64,
                                stream_id: this.currentStreamId
                            })
                        });
                    };
                    reader.readAsDataURL(blob);
                }
            }));
        });
    </script>
</div>