<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExtractCNRSMHCommand extends Command
{
    // php artisan extract:cnrsmh C:\laragon\www\mms-crem\public\all_items_path.txt
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'extract:cnrsmh
                           {source? : Chemin vers le fichier source}
                           {--output-csv= : Chemin pour le fichier CSV des lignes trouvées}
                           {--output-rejected= : Chemin pour le fichier des lignes rejetées}';

    /**
     * The console command description.
     */
    protected $description = 'Extrait les lignes contenant le pattern CNRSMH_I_YYYY_XXX et les sépare en deux fichiers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Récupération des arguments et options
        $sourceFile = $this->argument('source') ?: $this->ask('Chemin vers le fichier source');
        $outputCsv = $this->option('output-csv') ?: storage_path('app/cnrsmh_matches2.csv');
        $outputRejected = $this->option('output-rejected') ?: storage_path('app/cnrsmh_rejected.txt');

        // Vérification que le fichier source existe
        if (! File::exists($sourceFile)) {
            $this->error("Le fichier source '{$sourceFile}' n'existe pas.");

            return Command::FAILURE;
        }

        $this->info("Traitement du fichier : {$sourceFile}");
        $this->info("Fichier CSV de sortie : {$outputCsv}");
        $this->info("Fichier des rejets : {$outputRejected}");

        // Pattern pour CNRSMH_I_ suivi d'une année (4 chiffres) et de 3 chiffres
        // $pattern = '/CNRSMH_I_\d{4}_\d{3}/';
        $pattern = '/CNRSMH_E_\d{4}_\d{3}_\d{3}_/';

        try {
            // Lecture du fichier source
            $lines = File::lines($sourceFile);

            $matchedLines = [];
            $rejectedLines = [];

            // En-têtes pour le CSV
            $matchedLines[] = ['ligne_complete', 'pattern_trouve'];

            $totalLines = 0;
            $matchedCount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                } // Ignorer les lignes vides

                $totalLines++;

                if (preg_match($pattern, $line, $matches)) {
                    $matchedLines[] = [$line, $matches[0]];
                    $matchedCount++;
                } else {
                    $rejectedLines[] = $line;
                }
            }

            // Écriture du fichier CSV pour les lignes trouvées
            $this->writeCSV($outputCsv, $matchedLines);

            // Écriture du fichier pour les lignes rejetées
            $this->writeRejectedFile($outputRejected, $rejectedLines);

            // Statistiques
            $rejectedCount = count($rejectedLines);

            $this->info('=== Résultats ===');
            $this->info("Total de lignes traitées : {$totalLines}");
            $this->info("Lignes avec pattern trouvé : {$matchedCount}");
            $this->info("Lignes rejetées : {$rejectedCount}");
            $this->info("Fichier CSV créé : {$outputCsv}");
            $this->info("Fichier des rejets créé : {$outputRejected}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Erreur lors du traitement : '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Écrire le fichier CSV
     */
    private function writeCSV(string $filePath, array $data): void
    {
        // Créer le répertoire si nécessaire
        $directory = dirname($filePath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file = fopen($filePath, 'w');

        // Ajouter BOM UTF-8 pour Excel
        fwrite($file, "\xEF\xBB\xBF");

        foreach ($data as $row) {
            fputcsv($file, $row, ';', '"');
        }

        fclose($file);
    }

    /**
     * Écrire le fichier des lignes rejetées
     */
    private function writeRejectedFile(string $filePath, array $lines): void
    {
        // Créer le répertoire si nécessaire
        $directory = dirname($filePath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, implode("\n", $lines));
    }
}
