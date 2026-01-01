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
        
        if (Storage::deleteDirectory('public/cockpit')) {
            $this->info("Dossier purgé avec succès.");
        } else {
            $this->error("Erreur lors de la purge.");
        }
    }
}