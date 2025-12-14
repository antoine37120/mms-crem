<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-6">
        {{-- Sidebar de navigation --}}
        <div class="w-full md:w-64 flex-shrink-0">
            <x-filament::section>
                <nav class="flex flex-col space-y-1">
                    @foreach($sections as $key => $title)
                        <button
                            wire:click="setSection('{{ $key }}')"
                            type="button"
                            class="text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors w-full
                                {{ $currentSection === $key
                                    ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400'
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5'
                                }}"
                        >
                            {{ $title }}
                        </button>
                    @endforeach
                </nav>
            </x-filament::section>
        </div>

        {{-- Contenu principal --}}
        <div class="flex-1">
            <x-filament::section>
                <div class="prose dark:prose-invert max-w-none">
                    {!! $this->content !!}
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
