<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Str;

class Documentation extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-book-open';

    protected static string|null|\UnitEnum $navigationGroup = 'Aide';

    protected static ?string $title = 'Guide Utilisateur';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.documentation';

    public $currentPageId;

    // Persist state in URL
    protected $queryString = [
        'currentPageId' => ['as' => 'p'],
    ];

    public function mount()
    {
        if (! $this->currentPageId) {
            $firstPage = \App\Models\DocumentationPage::orderBy('order')->first();
            $this->currentPageId = $firstPage?->id;
        }
    }

    public function getPagesProperty()
    {
        return \App\Models\DocumentationPage::whereNull('parent_id')
            ->orderBy('order')
            ->with(['children' => fn ($q) => $q->orderBy('order')])
            ->get();
    }

    public function getCurrentPageProperty()
    {
        return \App\Models\DocumentationPage::find($this->currentPageId);
    }

    public function getContentProperty()
    {
        $page = $this->currentPage;

        if (! $page) {
            return 'Sélectionnez une page pour commencer.';
        }

        $content = $page->content;

        // Process dynamic routes: [Label](route:route.name)
        $content = preg_replace_callback(
            '/\[([^\]]+)\]\(route:([a-zA-Z0-9\._-]+)\)/',
            function ($matches) {
                $label = $matches[1];
                $routeName = $matches[2];
                try {
                    $url = route($routeName);

                    return "<a href=\"{$url}\" wire:navigate class=\"text-primary-600 hover:text-primary-500 underline\">{$label}</a>";
                } catch (\Exception $e) {
                    return "$label (Route introuvable)";
                }
            },
            $content
        );

        return Str::markdown($content ?? '');
    }

    public function setPage($id)
    {
        $this->currentPageId = $id;
    }
}
