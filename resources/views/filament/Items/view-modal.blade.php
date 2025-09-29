
<div class="space-y-6">
    {{-- En-tête avec breadcrumb --}}
    <div class="border-b border-gray-200 pb-4">
        <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
            @php
                //$record = $getRecord();
                $breadcrumb = [];

                switch ($record->itemable_type) {
                    case 'App\Models\Collection':
                        $collection = $record->itemable;
                        $breadcrumb = [
                            '🏛️ ' . $collection->corpus->fond->code,
                            '📚 ' . $collection->corpus->code,
                            '📦 ' . $collection->code
                        ];
                        break;
                    case 'App\Models\Corpus':
                        $corpus = $record->itemable;
                        $breadcrumb = [
                            '🏛️ ' . $corpus->fond->code,
                            '📚 ' . $corpus->code
                        ];
                        break;
                    case 'App\Models\Fond':
                        $fond = $record->itemable;
                        $breadcrumb = ['🏛️ ' . $fond->code];
                        break;
                    case 'App\Models\Item':
                        $parentItem = $record->itemable;
                        $breadcrumb = ['🎵 ' . $parentItem->code . ' (parent)'];
                        break;
                }
            @endphp

            @foreach($breadcrumb as $crumb)
                <span>{{ $crumb }}</span>
                @if(!$loop->last)
                    <span>›</span>
                @endif
            @endforeach
        </div>

        <h3 class="text-lg font-semibold text-gray-900">
            @if($record->item_type_id === null)
                🎵 {{ $record->code }}
            @else
                📄 {{ $record->code }}
            @endif
        </h3>

        @if($record->title)
            <p class="text-gray-600 mt-1">{{ $record->title }}</p>
        @endif
    </div>

    {{-- Informations techniques --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">
                {{ strtoupper($record->file_extension ?? 'N/A') }}
            </div>
            <div class="text-sm text-gray-500">Format</div>
        </div>

        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-green-600">
                @if($record->file_size)
                    @php
                        $units = ['B', 'KB', 'MB', 'GB'];
                        $power = floor(log($record->file_size, 1024));
                        $power = min($power, count($units) - 1);
                        $size = round($record->file_size / pow(1024, $power), 2);
                    @endphp
                    {{ $size }} {{ $units[$power] }}
                @else
                    N/A
                @endif
            </div>
            <div class="text-sm text-gray-500">Taille</div>
        </div>

        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">
                @if($record->duration)
                    {{ floor($record->duration / 60) }}:{{ sprintf('%02d', $record->duration % 60) }}
                @else
                    -
                @endif
            </div>
            <div class="text-sm text-gray-500">Durée</div>
        </div>

        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-orange-600">
                {{ $record->itemType?->name ?? 'Principal' }}
            </div>
            <div class="text-sm text-gray-500">Type</div>
        </div>
    </div>

    {{-- Player audio/vidéo si applicable --}}
    @if(in_array(strtolower($record->file_extension ?? ''), ['wav', 'mp3', 'mp4', 'avi']))
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold mb-3">🎵 Lecteur multimédia</h4>
            @if(in_array(strtolower($record->file_extension ?? ''), ['wav', 'mp3']))
                <audio controls class="w-full">
                    <source src="{{ asset('storage/' . $record->file_path) }}" type="audio/{{ $record->file_extension }}">
                    Votre navigateur ne supporte pas l'audio HTML5.
                </audio>
            @else
                <video controls class="w-full max-h-64">
                    <source src="{{ asset('storage/' . $record->file_path) }}" type="video/{{ $record->file_extension }}">
                    Votre navigateur ne supporte pas la vidéo HTML5.
                </video>
            @endif
        </div>
    @endif

    {{-- Métadonnées détaillées --}}
    <div class="space-y-4">
        <h4 class="font-semibold text-gray-900">📋 Détails techniques</h4>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div>
                <dt class="font-medium text-gray-500">Code complet:</dt>
                <dd class="text-gray-900 font-mono">{{ $record->code }}</dd>
            </div>

            <div>
                <dt class="font-medium text-gray-500">Nom du fichier:</dt>
                <dd class="text-gray-900">{{ $record->file_name ?? 'N/A' }}</dd>
            </div>

            @if($record->language_code)
                <div>
                    <dt class="font-medium text-gray-500">Langue:</dt>
                    <dd class="text-gray-900">{{ strtoupper($record->language_code) }}</dd>
                </div>
            @endif

            <div>
                <dt class="font-medium text-gray-500">Date d'upload:</dt>
                <dd class="text-gray-900">{{ $record->upload_date?->format('d/m/Y à H:i') ?? 'N/A' }}</dd>
            </div>

            <div>
                <dt class="font-medium text-gray-500">Uploadé par:</dt>
                <dd class="text-gray-900">{{ $record->uploader?->name ?? 'N/A' }}</dd>
            </div>

            <div>
                <dt class="font-medium text-gray-500">Créé par:</dt>
                <dd class="text-gray-900">{{ $record->creator?->name ?? 'N/A' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Items enfants si applicable --}}
    @if($record->items()->exists())
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-900">📎 Items associés</h4>
            <div class="space-y-2">
                @foreach($record->items as $childItem)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">📄</span>
                            <div>
                                <div class="font-medium">{{ $childItem->code }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $childItem->itemType?->name ?? 'N/A' }}
                                    @if($childItem->language_code)
                                        • {{ strtoupper($childItem->language_code) }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button type="button" class="text-blue-600 hover:text-blue-800 text-sm">Voir</button>
                            <button type="button" class="text-green-600 hover:text-green-800 text-sm">Télécharger</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
