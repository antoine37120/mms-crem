
<div class="space-y-6">
    {{-- Statistiques générales --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ number_format($stats['total']) }}</div>
            <div class="text-sm text-blue-800 font-medium">Total Items</div>
        </div>

        <div class="bg-green-50 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-green-600">{{ number_format($stats['principaux']) }}</div>
            <div class="text-sm text-green-800 font-medium">Items Principaux</div>
        </div>

        <div class="bg-purple-50 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-purple-600">{{ number_format($stats['secondaires']) }}</div>
            <div class="text-sm text-purple-800 font-medium">Items Secondaires</div>
        </div>

        <div class="bg-orange-50 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-orange-600">
                @php
                    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    $bytes = $stats['taille_totale'];
                    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
                    $power = min($power, count($units) - 1);
                    $size = $bytes > 0 ? round($bytes / pow(1024, $power), 2) : 0;
                @endphp
                {{ $size }} {{ $units[$power] }}
            </div>
            <div class="text-sm text-orange-800 font-medium">Taille Totale</div>
        </div>
    </div>

    {{-- Répartition par format --}}
    @if($stats['par_format']->isNotEmpty())
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Répartition par format</h4>
            <div class="space-y-2">
                @foreach($stats['par_format']->sortDesc() as $format => $count)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-200 text-gray-800">
                                {{ strtoupper($format ?: 'N/A') }}
                            </span>
                            <span class="text-gray-900">{{ number_format($count) }} items</span>
                        </div>
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($count / $stats['total']) * 100 }}%"></div>
                        </div>
                        <span class="text-sm text-gray-500 w-12 text-right">
                            {{ number_format(($count / $stats['total']) * 100, 1) }}%
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
