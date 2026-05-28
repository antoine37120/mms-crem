@php
    $state = $getRecord();
    $code = $state->code ?? null;
    $type = $state->file_type ?? '';
    $isAudio = str_starts_with($type, 'audio/');
    $isVideo = str_starts_with($type, 'video/');
    $isImage = str_starts_with($type, 'image/');
    $isPdf = $type === 'application/pdf';
    $token = $code ? \generate_media_token($state) : '';
    $mediaUrl = $code ? route('media.master', ['code' => $code]) . '?token=' . $token : '';
@endphp

@if($code && ($isAudio || $isVideo || $isImage || $isPdf))
    @if($isAudio || $isVideo)
        <div
            class="w-full rounded-lg bg-black/5 dark:bg-white/5 p-4"
            x-data="{
                player: null,
                hls: null,
                initPlyr() {
                    if (typeof Plyr !== 'undefined' && this.$refs.mediaElement) {
                        this.player = new Plyr(this.$refs.mediaElement, {
                            speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                            i18n: {
                                speed: 'Vitesse',
                                normal: 'Normale',
                            }
                        });
                    } else {
                        let checkInterval = setInterval(() => {
                            if (typeof Plyr !== 'undefined' && this.$refs.mediaElement) {
                                clearInterval(checkInterval);
                                this.player = new Plyr(this.$refs.mediaElement, {
                                    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                                    i18n: {
                                        speed: 'Vitesse',
                                        normal: 'Normale',
                                    }
                                });
                            }
                        }, 100);
                    }
                },
                init() {
                    const mediaEl = this.$refs.mediaElement;
                    const src = mediaEl.getAttribute('data-src');
                    mediaEl.removeAttribute('data-src');

                    if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                        this.hls = new Hls();
                        this.hls.loadSource(src);
                        this.hls.attachMedia(mediaEl);
                        this.hls.on(Hls.Events.MANIFEST_PARSED, () => {
                            this.initPlyr();
                        });
                        this.hls.on(Hls.Events.ERROR, (event, data) => {
                            if (data.fatal) {
                                switch (data.type) {
                                    case Hls.ErrorTypes.NETWORK_ERROR:
                                        this.hls.startLoad();
                                        break;
                                    case Hls.ErrorTypes.MEDIA_ERROR:
                                        this.hls.recoverMediaError();
                                        break;
                                    default:
                                        this.hls.destroy();
                                        break;
                                }
                            }
                        });
                    } else if (mediaEl.canPlayType('application/vnd.apple.mpegurl')) {
                        mediaEl.src = src;
                        this.initPlyr();
                    } else {
                        mediaEl.src = src;
                        this.initPlyr();
                    }
                }
            }"
        >
            @if($isVideo)
                <video
                    x-ref="mediaElement"
                    controls
                    crossorigin
                    playsinline
                    class="w-full max-h-[400px] mx-auto rounded"
                    data-src="{{ $mediaUrl }}"
                >
                    Votre navigateur ne supporte pas la lecture de vidéos.
                </video>
            @elseif($isAudio)
                <audio
                    x-ref="mediaElement"
                    controls
                    crossorigin
                    playsinline
                    class="w-full mt-2"
                    data-src="{{ $mediaUrl }}"
                >
                    Votre navigateur ne supporte pas la lecture audio.
                </audio>
            @endif
        </div>
    @elseif($isImage || $isPdf)
        <div
            class="w-full rounded-lg bg-black/5 dark:bg-white/5 p-4 relative group"
            x-data="{
                fullscreen: false,
                toggleFullscreen() {
                    const el = this.$refs.previewContainer;
                    if (!document.fullscreenElement) {
                        el.requestFullscreen().catch((err) => {
                            console.error(`Erreur de mode plein écran: ${err.message}`);
                        });
                    } else {
                        document.exitFullscreen();
                    }
                }
            }"
            @fullscreenchange.document="fullscreen = !!document.fullscreenElement"
        >
            <div class="absolute top-6 right-6 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="toggleFullscreen" type="button" class="bg-gray-800/80 text-white p-2 rounded hover:bg-gray-700 focus:outline-none backdrop-blur-sm shadow-sm" title="Plein écran">
                    <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="w-5 h-5" x-show="!fullscreen" />
                    <x-filament::icon icon="heroicon-o-arrows-pointing-in" class="w-5 h-5" x-show="fullscreen" x-cloak />
                </button>
            </div>

            <div x-ref="previewContainer" class="w-full flex justify-center items-center bg-transparent" :class="{'bg-gray-100 dark:bg-gray-900 h-full': fullscreen}">
                @if($isImage)
                    <img src="{{ route('media.master', ['code' => $code]) }}?token={{ $token }}" class="max-h-[600px] object-contain w-full rounded" :class="{'max-h-screen h-full': fullscreen}" alt="Aperçu" />
                @elseif($isPdf)
                    <iframe src="{{ route('media.master', ['code' => $code]) }}?token={{ $token }}" class="w-full h-[600px] rounded border-0 bg-white" :class="{'h-screen': fullscreen}"></iframe>
                @endif
            </div>
        </div>
    @endif
@else
    <div class="text-sm text-gray-500 italic p-4 text-center">
        Aucun aperçu disponible.
    </div>
@endif
