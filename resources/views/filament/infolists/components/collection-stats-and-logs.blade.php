<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div {{ $getExtraAttributeBag() }}>

        @php
            $state = $getState();
        @endphp

        @if($state)
            <div class="space-y-6">
                {{-- Cartes de statistiques --}}
                <div class="grid grid-cols-3 md:grid-cols-3 gap-4">

                    {{-- Items directs --}}
                    <div class="bg-success-50 dark:bg-success-900/20 p-4 rounded-lg text-center">
                        <div class="text-2xl font-semibold text-success-700 dark:text-success-300">{{ $state['items_count'] }}</div>
                        <div class="text-sm font-medium text-success-900 dark:text-success-100">Médias associés</div>
                    </div>

                    {{-- Total items --}}
                    <div class="bg-info-50 dark:bg-info-900/20 p-4 rounded-lg text-center">
                        <div class="text-2xl font-semibold text-info-700 dark:text-info-300">{{ $state['total_items_count'] }}</div>
                        <div class="text-sm font-medium text-info-900 dark:text-info-100">Items</div>
                    </div>

                    {{-- Taille totale --}}
                    <div class="bg-warning-50 dark:bg-warning-900/20 p-4 rounded-lg text-center">
                        <div class="text-2xl font-semibold text-warning-700 dark:text-warning-300">{{ $state['total_size'] }}</div>
                        <div class="text-sm font-medium text-warning-900 dark:text-warning-100">Stockage Total</div>
                    </div>
                </div>

                {{-- Timeline d'activité récente --}}
                @if(count($state['recent_activity']) > 0)
                    <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                            <svg class="w-5 h-5 mr-2 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Activité des 30 derniers jours
                        </h3>

                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach($state['recent_activity'] as $activity)
                                <div class="flex items-start space-x-3 p-3 bg-white dark:bg-gray-700 rounded border-l-4
                    {{ $activity['type'] === 'collection' ? 'border-blue-400' : 'border-green-400' }}
                    hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">



                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            @if(isset($activity['url']))
                                                <a href="{{ $activity['url'] }}" class="text-gray-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 underline decoration-dotted">
                                                    {{ $activity['action'] }}
                                                </a>
                                            @else
                                                {{ $activity['action'] }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $activity['date'] }} • Par {{ $activity['user'] }}
                                        </p>
                                    </div>

                                    @if(isset($activity['url']))
                                        <div class="flex-shrink-0">
                                            <a href="{{ $activity['url'] }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                            <svg class="w-5 h-5 mr-2 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Activité des 30 Derniers Jours
                        </h3>
                        <p class="text-gray-500 italic text-center py-8">Aucune activité récente</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-dynamic-component>
