<?php

namespace lionelhenne\LaravelCockpitCms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
        
        $directory = 'public/cockpit';

        // On supprime tout le dossier
        Storage::deleteDirectory($directory);
        
        // On le recrée immédiatement à vide pour garder le lien symbolique "vivant"
        if (Storage::makeDirectory($directory)) {
            $this->info("Dossier vidé avec succès.");
        } else {
            $this->error("Erreur lors du nettoyage.");
        }
    }
}