<div class="space-y-2">
    @php
        $renderPages = function ($pages, $level = 0) use (&$renderPages) {
            echo '<ul class="' . ($level > 0 ? 'pl-4 mt-1 space-y-1 border-l-2 border-gray-200 dark:border-gray-700' : 'space-y-1') . '">';
            foreach ($pages as $page) {
                $isActive = request()->route('record') == $page->id;
                $lineClass = $level === 0 ? 'font-medium' : 'text-sm';
                $textClass = $isActive 
                    ? 'text-primary-600 underline' 
                    : ($level === 0 ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400');
                
                echo '<li>';
                echo '<a href="' . \App\Filament\Resources\DocumentationPageResource::getUrl('view', ['record' => $page]) . '" 
                         class="block py-1 ' . $lineClass . ' ' . $textClass . ' hover:text-primary-600 hover:underline">';
                echo e($page->title);
                echo '</a>';
                
                if ($page->children->count() > 0) {
                    $renderPages($page->children()->orderBy('order')->get(), $level + 1);
                }
                echo '</li>';
            }
            echo '</ul>';
        };

        $rootPages = \App\Models\DocumentationPage::whereNull('parent_id')->orderBy('order')->get();
        $renderPages($rootPages);
    @endphp
</div>
