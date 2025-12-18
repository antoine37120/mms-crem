<div class="rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-900 shadow-inner">
    @php
        // $record is passed to the view by Filament's ViewEntry
        $code = $record->code ?? null;
        $mime = $record->file_type ?? '';
        $isVideo = str_contains($mime, 'video');
        $isAudio = str_contains($mime, 'audio');
    @endphp

    @if($code)
        <div class="flex justify-center p-4">
            @if($isVideo)
                <video
                    controls
                    class="w-full max-h-[500px] rounded-lg shadow-lg"
                    src="{{ route('media.master', ['code' => $code]) }}">
                    Votre navigateur ne supporte pas la lecture de vidéos.
                </video>
            @elseif($isAudio)
                <audio
                    controls
                    class="w-full"
                    src="{{ route('media.master', ['code' => $code]) }}">
                    Votre navigateur ne supporte pas l'élément audio.
                </audio>
            @else
                <div class="text-gray-500 text-sm italic">
                    Pas d'aperçu disponible pour ce type de fichier.
                </div>
            @endif
        </div>
    @endif
</div>
