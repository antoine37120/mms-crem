<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class Documentation extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-book-open';
    protected static string|null|\UnitEnum $navigationGroup = 'Aide';
    protected static ?string $title = 'Documentation Utilisateur';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.documentation';

    public $currentSection = '01-introduction';
    public $sections = [];

    // Persist state in URL
    protected $queryString = [
        'currentSection' => ['except' => '01-introduction', 'as' => 'section'],
    ];

    public function mount()
    {
        $this->loadSections();

        // Ensure currentSection is valid
        if (!array_key_exists($this->currentSection, $this->sections)) {
            $this->currentSection = array_key_first($this->sections) ?? '01-introduction';
        }
    }

    public function loadSections()
    {
        $path = resource_path('docs');

        if (!File::exists($path)) {
            return;
        }

        $files = File::files($path);
        foreach ($files as $file) {
            $filename = $file->getFilenameWithoutExtension();
            // Format title: "01-introduction" -> "Introduction"
            $titleParts = explode('-', $filename, 2);
            $title = isset($titleParts[1]) ? Str::title(str_replace('-', ' ', $titleParts[1])) : Str::title($filename);

            $this->sections[$filename] = $title;
        }

        ksort($this->sections);
    }

    public function getContentProperty()
    {
        // Security check: ensure the section is valid
        if (!array_key_exists($this->currentSection, $this->sections)) {
            return "Section invalide.";
        }

        $path = resource_path("docs/{$this->currentSection}.md");
        if (!File::exists($path)) {
            return "Fichier non trouvé.";
        }

        $content = File::get($path);

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
                    // Fallback if route not found
                    return "$label (Route introuvable)";
                }
            },
            $content
        );

        return Str::markdown($content);
    }

    public function setSection($section)
    {
        if (array_key_exists($section, $this->sections)) {
            $this->currentSection = $section;
        }
    }
}
