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
    protected $description = 'Aspirer les images depuis Cockpit vers le stockage local';

    protected array $processed = [];
    protected array $stats = ['success' => 0, 'fail' => 0, 'skip' => 0];

    public function handle()
    {
        $queries = $this->option('queries');

        if (!$queries) {
            $this->warn("Aucune requête spécifiée.");
            $this->line("Usage : <info>php artisan cockpit:warmup --queries=getHeroModel,getNewsModel</info>");
            return self::FAILURE;
        }

        $queryMethods = explode(',', $queries);
        $this->info("Démarrage du warmup...");

        foreach ($queryMethods as $method) {
            $method = trim($method);
            
            if (!method_exists($this, $method)) {
                $this->error("\nLa méthode {$method} n'existe pas dans le trait.");
                continue;
            }

            $this->line("\nRequête : <info>{$method}</info>");

            try {
                $gql = $this->{$method}();
                $result = Cockpit::execute([$gql]);
                $paths = $this->extractImagePaths($result);

                if (empty($paths)) {
                    $this->comment("  Aucune image valide trouvée.");
                    continue;
                }

                foreach ($paths as $path) {
                    $this->warmup($path);
                    usleep(200000); // Pause de 0.2s
                }
            } catch (\Exception $e) {
                $this->error("  Erreur : " . $e->getMessage());
            }
        }

        $this->line("");
        $this->info("Warmup terminé !");
        $this->line("• Succès : <fg=green>{$this->stats['success']}</>");
        $this->line("• Échecs : <fg=red>{$this->stats['fail']}</>");
        $this->line("• Doublons ignorés : {$this->stats['skip']}");
        
        return self::SUCCESS;
    }

    protected function warmup(string $path)
    {
        if (in_array($path, $this->processed)) {
            $this->stats['skip']++;
            return;
        }

        $url = route('cockpit.image', ['path' => $path]);
        $this->output->write("  Vérification {$path} ... ");

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(30)->get($url);
            if ($response->successful()) {
                $this->info("OK");
                $this->processed[] = $path;
                $this->stats['success']++;
            } else {
                $this->error("FAIL (" . $response->status() . ")");
                $this->stats['fail']++;
            }
        } catch (\Exception $e) {
            $this->error("ERREUR HTTP");
            $this->stats['fail']++;
        }
    }

    protected function extractImagePaths($data): array
    {
        $paths = [];
        $extensions = ['jpeg', 'jpg', 'gif', 'png', 'webp', 'avif'];
        $data = json_decode(json_encode($data), true);
        if (!is_array($data)) return [];

        array_walk_recursive($data, function ($value) use (&$paths, $extensions) {
            if (is_string($value) && str_contains($value, '.')) {
                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                if (in_array($ext, $extensions) && str_contains($value, '/')) {
                    $paths[] = ltrim($value, '/');
                }
            }
        });

        return array_unique($paths);
    }
}