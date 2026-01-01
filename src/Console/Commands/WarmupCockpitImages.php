<?php

namespace lionelhenne\LaravelCockpitCms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use lionelhenne\LaravelCockpitCms\Facades\Cockpit;
use App\Http\Controllers\Traits\CockpitGQLQueries;

class WarmupCockpitImages extends Command
{
    use CockpitGQLQueries;
    protected $signature = 'cockpit:warmup {--queries= : Liste des requêtes GQL séparées par une virgule}';
    protected $description = 'Aspirer toutes les images de Cockpit vers le stockage local';

    public function handle()
    {
        $this->info("Démarrage du warmup des images...");

        // On récupère les requêtes depuis l'option ou une config
        $queryMethods = $this->option('queries') 
            ? explode(',', $this->option('queries')) 
            : ['getActiveSisters', 'getRetiredSisters', 'getBeyondSisters'];

        foreach ($queryMethods as $method) {
            $this->line("Exécution de la requête : {$method}");
            
            // Correction ici : on appelle la méthode sur $this
            if (!method_exists($this, $method)) {
                $this->error("La méthode {$method} n'existe pas dans le trait.");
                continue;
            }

            // On appelle la méthode du trait et on passe le résultat à Cockpit::execute
            $gql = $this->{$method}(); 
            $result = Cockpit::execute([$gql]); 
            
            $paths = $this->extractImagePaths($result);

            foreach ($paths as $path) {
                $url = route('cockpit.image', ['path' => $path]);
                $this->output->write("Vérification de {$path}... ");
                
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout(60)->get($url);

                if ($response->successful()) {
                    $this->info("OK");
                } else {
                    $this->error("ERREUR");
                }
            }
        }

        $this->info("Warmup terminé !");
    }

    protected function extractImagePaths($data): array
    {
        $paths = [];
        array_walk_recursive($data, function($value, $key) use (&$paths) {
            if ($key === 'path' && is_string($value) && (strpos($value, '.') !== false)) {
                $paths[] = $value;
            }
        });
        return array_unique($paths);
    }
}