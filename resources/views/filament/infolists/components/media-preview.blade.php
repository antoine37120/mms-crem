<div class="mt-4 space-y-4"
     x-data="{
        player: null,
        initPlayer() {
            if (this.player) return;

            // Wait for videojs to be loaded
            let checkInterval = setInterval(() => {
                if (typeof videojs !== 'undefined') {
                    clearInterval(checkInterval);
                    const element = this.$refs.mediaPlayer;
                    if (element) {
                        this.player = videojs(element, {
                            fluid: true,
                            responsive: true,
                            controls: true,
                            preload: 'metadata',
                            html5: {
                                vhs: {
                                    overrideNative: true
                                },
                                nativeAudioTracks: false,
                                nativeVideoTracks: false
                            }
                        });
                    }
                }
            }, 100);
        }
     }"
     x-init="
        // Load CSS
        if (!document.getElementById('videojs-css')) {
            const link = document.createElement('link');
            link.id = 'videojs-css';
            link.rel = 'stylesheet';
            link.href = 'https://vjs.zencdn.net/8.10.0/video-js.css';
            document.head.appendChild(link);
        }

        // Load JS
        if (!document.getElementById('videojs-js')) {
            const script = document.createElement('script');
            script.id = 'videojs-js';
            script.src = 'https://vjs.zencdn.net/8.10.0/video.min.js';
            script.onload = () => initPlayer();
            document.head.appendChild(script);
        } else {
            initPlayer();
        }
     "
     x-on:livewire:navigating.window="if (player) { player.dispose(); }"
     wire:ignore
>
    @php
        $mediaType = null;
        if (isset($record)) {
            $code = $record->code;
            // Detect based on item methods or mime
            if ($record->isVideo()) {
                $mediaType = 'video';
            } elseif ($record->isAudio()) {
                $mediaType = 'audio';
            }
        } elseif (isset($itemData)) {
            $code = $itemData['code'] ?? null;
            $mime = $itemData['file_type'] ?? '';
            if (str_contains($mime, 'video')) {
                $mediaType = 'video';
            } elseif (str_contains($mime, 'audio')) {
                $mediaType = 'audio';
            }
        }
    @endphp

    @if($code && $mediaType)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 overflow-hidden">
            <div class="p-2 border-b border-gray-200 dark:border-gray-700 font-medium text-sm">
                Aperçu Média
            </div>

            <div class="p-4 flex flex-col items-center justify-center">
                {{-- Video.js requires a <video> class='video-js' for both audio and video usually,
                     or <audio class='video-js'> for audio-only mode. --}}

                @if($mediaType === 'video')
                    <video x-ref="mediaPlayer"
                           class="video-js vjs-big-play-centered vjs-theme-sea"
                           controls
                           preload="auto"
                           data-setup="{}"
                           width="640"
                           height="360">
                        <source src="{{ route('media.master', ['code' => $code]) }}" type="application/x-mpegURL">
                        <p class="vjs-no-js">
                            To view this video please enable JavaScript, and consider upgrading to a web browser that
                            <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                        </p>
                    </video>
                @elseif($mediaType === 'audio')
                    {{-- Using video tag for audio in video.js is often more stable for HLS visualizer,
                         but let's try <audio> first or just <video> with height.
                         For HLS audio, standard video.js player works well (shows poster or black). --}}
                    <audio x-ref="mediaPlayer"
                           class="video-js vjs-big-play-centered vjs-theme-sea"
                           controls
                           preload="auto"
                           data-setup="{}"
                           width="600"
                           height="100">
                        <source src="{{ route('media.master', ['code' => $code]) }}" type="application/x-mpegURL">
                         <p class="vjs-no-js">
                            To listen to this audio please enable JavaScript.
                        </p>
                    </audio>
                @endif
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="p-2 border-b border-gray-200 dark:border-gray-700 font-medium text-sm">
            Liens de téléchargement
        </div>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
            @if(isset($code))
                <li class="p-2 hover:bg-gray-50 dark:hover:bg-gray-700 flex justify-between items-center group">
                    <span class="text-gray-600 dark:text-gray-400">Master / Streaming</span>
                    <a href="{{ route('media.master', ['code' => $code]) }}"
                       target="_blank"
                       class="text-primary-600 hover:text-primary-500 font-medium flex items-center gap-1">
                        Ouvrir
                        <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                    </a>
                </li>

                @if($mediaType === 'audio' || $mediaType === 'video')
                    <li class="p-2 hover:bg-gray-50 dark:hover:bg-gray-700 flex justify-between items-center group">
                        <span class="text-gray-600 dark:text-gray-400">Données Waveform (JSON)</span>
                        <a href="{{ route('media.waveform', ['code' => $code]) }}"
                           target="_blank"
                           class="text-primary-600 hover:text-primary-500 font-medium flex items-center gap-1">
                            JSON
                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                        </a>
                    </li>
                @endif
            @endif
        </ul>
    </div>
</div>
