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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('walkieTalkie', () => ({
                channel: @entangle('channel'),
                isRecording: false,
                mediaRecorder: null,
                audioChunks: [],
                username: @js($username),
                prevChannel: null,
                isReceiving: @entangle('isReceiving'),

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
                                    const audio = new Audio('data:audio/webm;base64,' + e.audio);
                                    audio.play();
                                }
                            });
                    }
                },

                async startRecording() {
                    if (this.isRecording) return;
                    this.isRecording = true;

                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            alert("Microphone access is not supported in this context. Please ensure you are using HTTPS or localhost.");
                            this.isRecording = false;
                            return;
                        }
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data.size > 0) this.audioChunks.push(e.data);
                        };

                        this.mediaRecorder.onstop = async () => {
                            if (this.audioChunks.length) {
                                const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
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
                                            audio: base64
                                        })
                                    });
                                };
                                reader.readAsDataURL(blob);
                                this.audioChunks = [];
                            }
                        };

                        this.mediaRecorder.start();
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
                }
            }));
        });
    </script>
</div>