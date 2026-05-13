<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class MediaSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $title = 'MMS Settings';

    protected string $view = 'filament.pages.media-settings';

    public static function canAccess(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = $this->getSettings();
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
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

                        TextInput::make('audiowaveform_path')
                            ->label('Chemin Audiowaveform')
                            ->placeholder('/usr/bin/audiowaveform')
                            ->helperText('Laissez vide pour utiliser le PATH système.'),

                        TextInput::make('diffusion_disk')
                            ->label('Disque de diffusion')
                            ->default('diffusion_medias')
                            ->required()
                            ->helperText('Nom du disque dans config/filesystems.php pour stocker les fichiers générés.'),
                    ])->columns(2),

                Section::make('Encodage Vidéo')
                    ->schema([
                        Select::make('video_codec')
                            ->label('Codec Vidéo')
                            ->options(config('mms.encoding.video.codec.options'))
                            ->default(config('mms.encoding.video.codec.default'))
                            ->required(),
                        Select::make('video_preset')
                            ->label('Preset FFMpeg')
                            ->options(config('mms.encoding.video.preset.options'))
                            ->default(config('mms.encoding.video.preset.default'))
                            ->required(),
                        TextInput::make('video_crf')
                            ->label('Qualité (CRF)')
                            ->numeric()
                            ->minValue(config('mms.encoding.video.crf.min'))
                            ->maxValue(config('mms.encoding.video.crf.max'))
                            ->default(config('mms.encoding.video.crf.default'))
                            ->helperText('Plus la valeur est basse, meilleure est la qualité vidéo (0-51).')
                            ->required(),
                        Select::make('video_audio_bitrate')
                            ->label('Bitrate Audio (Vidéo)')
                            ->options(config('mms.encoding.video.audio_bitrate.options'))
                            ->default(config('mms.encoding.video.audio_bitrate.default'))
                            ->required(),
                        Select::make('video_hls_time')
                            ->label('Segment HLS (s)')
                            ->options(config('mms.encoding.video.hls_time.options'))
                            ->default(config('mms.encoding.video.hls_time.default'))
                            ->required(),
                    ])->columns(2),

                Section::make('Encodage Audio')
                    ->schema([
                        Select::make('audio_codec')
                            ->label('Codec Audio')
                            ->options(config('mms.encoding.audio.codec.options'))
                            ->default(config('mms.encoding.audio.codec.default'))
                            ->required(),
                        Select::make('audio_bitrate')
                            ->label('Bitrate Audio')
                            ->options(config('mms.encoding.audio.bitrate.options'))
                            ->default(config('mms.encoding.audio.bitrate.default'))
                            ->required(),
                        Select::make('audio_channels')
                            ->label('Canaux')
                            ->options(config('mms.encoding.audio.channels.options'))
                            ->default(config('mms.encoding.audio.channels.default'))
                            ->required(),
                        Select::make('audio_hls_time')
                            ->label('Segment HLS (s)')
                            ->options(config('mms.encoding.audio.hls_time.options'))
                            ->default(config('mms.encoding.audio.hls_time.default'))
                            ->required(),
                    ])->columns(2),

                Section::make('Waveform')
                    ->schema([
                        Select::make('waveform_pixels_per_second')
                            ->label('Pixels par seconde')
                            ->options(config('mms.encoding.waveform.pixels_per_second.options'))
                            ->default(config('mms.encoding.waveform.pixels_per_second.default'))
                            ->required(),
                        Select::make('waveform_bits')
                            ->label('Bits par échantillon')
                            ->options(config('mms.encoding.waveform.bits.options'))
                            ->default(config('mms.encoding.waveform.bits.default'))
                            ->required(),
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
