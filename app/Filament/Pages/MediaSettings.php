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
                            ->helperText('Dossier où se trouvent les fichiers sources (fichiers originaux à encoder).')
                            ->required(),

                        TextInput::make('ffmpeg_path')
                            ->label('Chemin FFMpeg')
                            ->placeholder('/usr/bin/ffmpeg')
                            ->helperText('Laissez vide si ffmpeg est accessible via le PATH système.'),

                        TextInput::make('ffprobe_path')
                            ->label('Chemin FFProbe')
                            ->placeholder('/usr/bin/ffprobe')
                            ->helperText('Laissez vide si ffprobe est accessible via le PATH système.'),

                        TextInput::make('audiowaveform_path')
                            ->label('Chemin Audiowaveform')
                            ->placeholder('/usr/bin/audiowaveform')
                            ->helperText('Laissez vide si audiowaveform est accessible via le PATH système.'),

                        TextInput::make('diffusion_disk')
                            ->label('Disque de diffusion')
                            ->default('diffusion_medias')
                            ->required()
                            ->helperText('Disque de stockage où seront écrits les fichiers de diffusion (HLS, waveform).'),
                    ])->columns(2),

                Section::make('Encodage Vidéo')
                    ->schema([
                        Select::make('video_codec')
                            ->label('Codec Vidéo')
                            ->options(config('mms.encoding.video.codec.options'))
                            ->default(config('mms.encoding.video.codec.default'))
                            ->helperText('Codec utilisé pour compresser la vidéo. H.264 (AVC) est le standard le plus compatible (recommandé). H.265 (HEVC) offre une meilleure compression mais est moins compatible. VP9 offre une bonne qualité à bas débit.')
                            ->required(),
                        Select::make('video_preset')
                            ->label('Preset FFMpeg')
                            ->options(config('mms.encoding.video.preset.options'))
                            ->default(config('mms.encoding.video.preset.default'))
                            ->helperText('Vitesse de compression. Plus c\'est rapide (Ultra rapide), plus le fichier est gros pour une même qualité. Plus c\'est lent (Très lent), plus le fichier est petit mais le traitement dure plus longtemps. Valeur recommandée : Très rapide pour un bon équilibre.')
                            ->required(),
                        TextInput::make('video_crf')
                            ->label('Qualité (CRF)')
                            ->numeric()
                            ->minValue(config('mms.encoding.video.crf.min'))
                            ->maxValue(config('mms.encoding.video.crf.max'))
                            ->default(config('mms.encoding.video.crf.default'))
                            ->helperText('Qualité visible de la vidéo. 18 = qualité quasi parfaite (fichier volumineux), 23 = qualité très bonne (équilibre recommandé), 28 = qualité réduite (fichier plus petit). Ne pas descendre sous 18 : la différence n\'est plus visible mais le fichier est bien plus gros.')
                            ->required(),
                        Select::make('video_audio_bitrate')
                            ->label('Bitrate Audio (Vidéo)')
                            ->options(config('mms.encoding.video.audio_bitrate.options'))
                            ->default(config('mms.encoding.video.audio_bitrate.default'))
                            ->helperText('Débit binaire de la piste audio dans la vidéo. 128k = qualité FM (recommandé), 192k = haute qualité, 256k = qualité CD. Plus le débit est élevé, plus le fichier est gros et meilleure est la qualité audio.')
                            ->required(),
                        Select::make('video_hls_time')
                            ->label('Segment HLS (s)')
                            ->options(config('mms.encoding.video.hls_time.options'))
                            ->default(config('mms.encoding.video.hls_time.default'))
                            ->helperText('Durée de chaque segment de streaming (en secondes). Segments courts = démarrage plus rapide sur connexions lentes mais plus de fichiers. Segments longs = moins de fichiers, téléchargement plus efficace. 4s est un bon compromis.')
                            ->required(),
                    ])->columns(2),

                Section::make('Encodage Audio')
                    ->schema([
                        Select::make('audio_codec')
                            ->label('Codec Audio')
                            ->options(config('mms.encoding.audio.codec.options'))
                            ->default(config('mms.encoding.audio.codec.default'))
                            ->helperText('Format audio pour le streaming. AAC est le plus compatible (recommandé). Opus offre une meilleure qualité au même débit. MP3 est le format le plus universellement reconnu.')
                            ->required(),
                        Select::make('audio_bitrate')
                            ->label('Bitrate Audio')
                            ->options(config('mms.encoding.audio.bitrate.options'))
                            ->default(config('mms.encoding.audio.bitrate.default'))
                            ->helperText('Débit binaire audio : plus le chiffre est élevé, meilleure est la qualité mais plus le fichier est gros. 64k = acceptable (voix), 128k = bonne qualité (recommandé), 192k+ = haute qualité (musique).')
                            ->required(),
                        Select::make('audio_channels')
                            ->label('Canaux')
                            ->options(config('mms.encoding.audio.channels.options'))
                            ->default(config('mms.encoding.audio.channels.default'))
                            ->helperText('Mono = un seul canal (recommandé pour la parole). Stéréo = deux canaux (recommandé pour la musique). Le Mono produit un fichier deux fois plus petit.')
                            ->required(),
                        Select::make('audio_hls_time')
                            ->label('Segment HLS (s)')
                            ->options(config('mms.encoding.audio.hls_time.options'))
                            ->default(config('mms.encoding.audio.hls_time.default'))
                            ->helperText('Durée de chaque segment audio (en secondes). 10s est la valeur recommandée pour un bon équilibre entre fluidité et efficacité.')
                            ->required(),
                    ])->columns(2),

                Section::make('Waveform')
                    ->schema([
                        Select::make('waveform_pixels_per_second')
                            ->label('Pixels par seconde')
                            ->options(config('mms.encoding.waveform.pixels_per_second.options'))
                            ->default(config('mms.encoding.waveform.pixels_per_second.default'))
                            ->helperText('Résolution de la forme d\'onde : plus il y a de pixels par seconde, plus le rendu visuel est précis et détaillé, mais le fichier JSON est plus volumineux. 20 pps est excellent, 10 pps suffit.')
                            ->required(),
                        Select::make('waveform_bits')
                            ->label('Bits par échantillon')
                            ->options(config('mms.encoding.waveform.bits.options'))
                            ->default(config('mms.encoding.waveform.bits.default'))
                            ->helperText('Précision des données de la forme d\'onde. 8 bits = plus petit, suffisant pour un affichage standard. 16 bits = plus précis, 2 fois plus gros.')
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
