<?php

namespace lionelhenne\LaravelCockpitCms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeCockpitImages extends Command
{
    protected $signature = 'cockpit:purge {--force : Passer la confirmation}';
    protected $description = 'Supprime toutes les images Cockpit stockées localement';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Supprimer TOUTES les images locales ?')) {
            return self::FAILURE;
        }

        $this->info("Nettoyage du dossier cockpit...");
        $path = storage_path('app/public/cockpit');

        if (!File::exists($path)) {
            $this->warn("Le dossier n'existe pas, rien à purger.");
            return self::SUCCESS;
        }

        if (File::cleanDirectory($path)) {
            $this->info("Dossier vidé avec succès.");
            return self::SUCCESS;
        }

        $this->error("Erreur lors du nettoyage.");
        return self::FAILURE;
    }
}