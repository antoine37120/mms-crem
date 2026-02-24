<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div {{ $getExtraAttributeBag() }}>
        @php
            $fond = $record;
        @endphp

        <div class="space-y-6">
            {{-- Statistiques générales --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $fond->corpuses()->count() }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">Corpus</div>
                </div>

                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $fond->secondaryItems()->count() }}</div>
                    <div class="text-sm text-green-700 dark:text-green-300">Médias associés</div>
                </div>

                <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $fond->corpuses()->withCount('collections')->get()->sum('collections_count') }}</div>
                    <div class="text-sm text-purple-700 dark:text-purple-300">Collections</div>
                </div>

                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg text-center">
                    @php
                        $totalSize = $fond->items()->sum('file_size') +
                                   $fond->corpuses()->with(['items'])->get()->sum(function($corpus) {
                                       return $corpus->items->sum('file_size');
                                   });

                        $formattedSize = $totalSize > 0 ?
                            (function($bytes) {
                                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                                $power = floor(log($bytes, 1024));
                                $power = min($power, count($units) - 1);
                                return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
                            })($totalSize) : '0 B';
                    @endphp
                    <div class="text-2xl font-bold text-orange-600">{{ $formattedSize }}</div>
                    <div class="text-sm text-orange-700 dark:text-orange-300">Stockage Total</div>
                </div>
            </div>

            {{-- Timeline d'activité --}}
            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    Activité des 30 derniers jours
                </h3>

                @php
                    $activities = collect();

                    // Corpus créés
                    $recentCorpuses = $fond->corpuses()
                        ->where('corpuses.created_at', '>=', now()->subDays(30))
                        ->with('creator')
                        ->orderBy('created_at', 'desc')
                        ->get();

                    foreach($recentCorpuses as $corpus) {
                        $activities->push([
                            'date' => $corpus->created_at,
                            'type' => 'corpus',
                            'message' => "Nouveau corpus : {$corpus->code}",
                            'user' => $corpus->creator->name,
                        ]);
                    }

                    // Items uploadés
                    $recentItems = $fond->items()
                        ->where('created_at', '>=', now()->subDays(30))
                        ->with(['uploader'])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    foreach($recentItems as $item) {
                        $activities->push([
                            'date' => $item->created_at,
                            'type' => 'item',
                            'message' => "Nouveau fichier : {$item->file_name}",
                            'user' => $item->uploader->name ?? 'Utilisateur inconnu',
                        ]);
                    }

                    $activities = $activities->sortByDesc('date')->take(10);
                @endphp

                @if($activities->count() > 0)
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        @foreach($activities as $activity)
                            <div class="flex items-start space-x-3 p-3 bg-white dark:bg-gray-700 rounded border-l-4
                                {{ $activity['type'] === 'corpus' ? 'border-blue-400' : 'border-green-400' }}">
                                <span class="text-lg"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $activity['message'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $activity['date']->format('d/m/Y à H:i') }} • Par {{ $activity['user'] }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 italic text-center py-8">Aucune activité récente</p>
                @endif
            </div>
        </div>

    </div>
</x-dynamic-component>
