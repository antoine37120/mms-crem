<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class MediaSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $title = 'MMS Settings';

    protected static string $view = 'filament.pages.media-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = $this->getSettings();
        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuration MMS')
                    ->description('Chemins et paramètres pour le traitement des médias.')
                    ->schema([
                        TextInput::make('scan_path')
                            ->label('Dossier de Scan')
                            ->helperText('Chemin absolu ou relatif vers le dossier à scanner.')
                            ->required(),

                        TextInput::make('ffmpeg_path')
                            ->label('Chemin FFMpeg')
                            ->placeholder('/usr/bin/ffmpeg')
                            ->helperText('Laissez vide pour utiliser le PATH système.'),

                        TextInput::make('ffprobe_path')
                            ->label('Chemin FFProbe')
                            ->placeholder('/usr/bin/ffprobe')
                            ->helperText('Laissez vide pour utiliser le PATH système.'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $this->saveSettings($data);

        Notification::make()
            ->success()
            ->title('Paramètres sauvegardés')
            ->send();
    }

    protected function getSettingsPath(): string
    {
        return 'mms_settings.json';
    }

    protected function getSettings(): array
    {
        if (Storage::disk('local')->exists($this->getSettingsPath())) {
            return json_decode(Storage::disk('local')->get($this->getSettingsPath()), true) ?? [];
        }

        return [];
    }

    protected function saveSettings(array $settings): void
    {
        Storage::disk('local')->put($this->getSettingsPath(), json_encode($settings, JSON_PRETTY_PRINT));
    }
}
