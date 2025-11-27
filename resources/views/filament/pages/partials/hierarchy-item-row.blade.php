@php $hasChildren = $item->childItems && $item->childItems->count() > 0; @endphp
<div wire:key="item-{{ $item->id }}">
    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $item->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
            wire:click="selectItem({{ $item->id }})"
            @if($selectedItemId == $item->id) x-ref="selectedItem" @endif>

        {{-- Icône de dépliant si enfants --}}
        @if($hasChildren)
            <button
                wire:click.stop="toggleItem({{ $item->id }})"
                class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($item->id, $expandedItems) ? 'rotate-180' : '' }}">
                <x-heroicon-o-chevron-up-down class="w-4 h-4" />
            </button>
        @else
            <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                <x-heroicon-o-document class="w-3 h-3 text-gray-400" />
            </span>
        @endif

        <div class="flex-1 min-w-0">
            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                {{ $item->code }}
            </div>
            <div class="text-xs text-gray-500">
                {{ $item->file_extension }} • {{ $this->formatFileSize($item->file_size) }}
            </div>
        </div>

        @if($selectedItemId == $item->id)
            <span class="text-sm text-primary-600 ml-2">◄</span>
        @endif
    </div>

    {{-- Enfants (si expanded) --}}
    @if($hasChildren && in_array($item->id, $expandedItems))
        <div class="ml-6 border-l-2 border-gray-100 dark:border-gray-700 pl-1 mt-1">
            @foreach($item->childItems as $childItem)
                <div wire:key="child-item-{{ $childItem->id }}"
                        class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $childItem->id ? 'bg-primary-50 dark:bg-primary-900/50' : '' }}"
                        wire:click="selectItem({{ $childItem->id }})"
                        @if($selectedItemId == $childItem->id) x-ref="selectedItem" @endif>
                    
                    <span class="flex-shrink-0 w-4 h-4 mr-2"></span>
                    
                    <div class="text-xs text-gray-600 dark:text-gray-400 truncate flex-1">
                        {{ $childItem->code }}
                    </div>
                    
                    @if($selectedItemId == $childItem->id)
                        <span class="text-primary-600 ml-2">◄</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
