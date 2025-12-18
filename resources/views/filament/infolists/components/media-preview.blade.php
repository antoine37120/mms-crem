@php
    $state = $getRecord();
    $code = $state->code ?? null;
    $type = $state->file_type ?? '';
    $isAudio = str_starts_with($type, 'audio/');
    $isVideo = str_starts_with($type, 'video/');
@endphp

@if($code && ($isAudio || $isVideo))
    <div class="w-full rounded-lg overflow-hidden bg-black/5 dark:bg-white/5 p-4">
        @if($isVideo)
            <video
                controls
                class="w-full max-h-[400px] mx-auto rounded"
                src="{{ route('media.master', ['code' => $code]) }}"
            >
                Votre navigateur ne supporte pas la lecture de vidéos.
            </video>
        @elseif($isAudio)
            <audio
                controls
                class="w-full mt-2"
                src="{{ route('media.master', ['code' => $code]) }}"
            >
                Votre navigateur ne supporte pas la lecture audio.
            </audio>
        @endif
    </div>
@else
    <div class="text-sm text-gray-500 italic p-4 text-center">
        Aucun aperçu disponible.
    </div>
@endif
