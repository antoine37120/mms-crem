<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-6">

        {{-- Contenu principal --}}
        <div class="flex-1">
            <x-filament::section>
                <div class="mms-docs-content prose dark:prose-invert max-w-none">
                    {!! $this->content !!}
                </div>
            </x-filament::section>
        </div>
        {{-- Sidebar de navigation --}}
        <div class="w-full md:w-64 flex-shrink-0">
            <x-filament::section>
                <nav class="flex flex-col space-y-1">
                    @php
                        $renderNav = function ($pages, $level = 0) use (&$renderNav) {
                            echo '<ul class="' . ($level > 0 ? 'pl-4 mt-1 space-y-1 border-l-2 border-gray-200 dark:border-gray-700' : 'space-y-1') . '">';
                            foreach ($pages as $page) {
                                $isActive = $this->currentPageId == $page->id;
                                $btnClass = $isActive 
                                    ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400'
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5';
                                
                                echo '<li>';
                                echo '<button wire:click="setPage(' . $page->id . ')" type="button" 
                                        class="text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors w-full ' . $btnClass . '">';
                                echo e($page->title);
                                echo '</button>';

                                if ($page->children->count() > 0) {
                                    $renderNav($page->children, $level + 1);
                                }
                                echo '</li>';
                            }
                            echo '</ul>';
                        };
                        
                        $renderNav($this->pages);
                    @endphp
                </nav>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
