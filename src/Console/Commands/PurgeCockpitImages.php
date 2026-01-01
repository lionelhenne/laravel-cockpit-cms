<?php

namespace lionelhenne\LaravelCockpitCms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File; // On utilise File ici

class PurgeCockpitImages extends Command
{
    protected $signature = 'cockpit:purge {--force : Passer la confirmation}';
    protected $description = 'Supprime toutes les images Cockpit stockées localement';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Es-tu sûr de vouloir supprimer TOUTES les images locales ?')) {
            return;
        }

        $this->info("Nettoyage du dossier cockpit...");
        
        // On définit le chemin absolu vers le dossier public dans le storage
        $path = storage_path('app/public/cockpit');

        if (!File::exists($path)) {
            $this->warn("Le dossier n'existe pas, rien à purger.");
            return;
        }

        // cleanDirectory vide tout mais garde le dossier racine 'cockpit'
        // C'est une méthode native de Laravel Filesystem
        if (File::cleanDirectory($path)) {
            $this->info("Dossier vidé avec succès.");
        } else {
            $this->error("Erreur lors du nettoyage physique du dossier.");
        }
    }
}