<?php

namespace App\Console\Commands;

use App\Services\Seo\SitemapGenerator;
use Illuminate\Console\Command;

/**
 * Writes public/robots.txt from SitemapGenerator.
 *
 * There is also a `robots.txt` route, but web servers commonly short-circuit
 * that filename before the request reaches PHP (Laravel Herd returns 404 for
 * it locally). A real file is served correctly by every server, so the file is
 * the primary and the route is the fallback.
 */
class GenerateRobotsTxt extends Command
{
    protected $signature = 'seo:robots';

    protected $description = 'Write public/robots.txt from the current SEO configuration';

    public function handle(SitemapGenerator $sitemap): int
    {
        $path = public_path('robots.txt');
        $contents = $sitemap->robotsTxt();

        if (file_put_contents($path, $contents) === false) {
            $this->error("Could not write {$path}");

            return self::FAILURE;
        }

        $this->info("Wrote {$path}");
        $this->line($contents);

        return self::SUCCESS;
    }
}
