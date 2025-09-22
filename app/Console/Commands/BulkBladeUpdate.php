<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BulkBladeUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulk:blade-update {--path= : Path to directory or file} {--type=all : Type of update (layout, reset, sort, action, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk update Blade template files with common modifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path') ?: resource_path('views');
        $type = $this->option('type');

        $files = $this->getBladeFiles($path);

        if (empty($files)) {
            $this->error('No Blade files found in the specified path.');
            return;
        }

        $this->info("Found " . count($files) . " Blade files to process.");

        $updated = 0;

        foreach ($files as $file) {
            try {
                $content = File::get($file);
                $original = $content;

                switch ($type) {
                    case 'layout':
                        $content = $this->updateLayouts($content);
                        break;
                    case 'reset':
                        $content = $this->fixResetButtons($content);
                        break;
                    case 'sort':
                        $content = $this->addSortableHeaders($content);
                        break;
                    case 'action':
                        $content = $this->fixFormActions($content);
                        break;
                    case 'all':
                        $content = $this->updateLayouts($content);
                        $content = $this->fixResetButtons($content);
                        $content = $this->addSortableHeaders($content);
                        $content = $this->fixFormActions($content);
                        break;
                }

                if ($content !== $original) {
                    File::put($file, $content);
                    $this->line("Updated: " . str_replace(base_path() . '/', '', $file));
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$file}: " . $e->getMessage());
            }
        }

        $this->info("Processed " . count($files) . " files, updated {$updated} files.");
    }

    /**
     * Get all Blade files in the specified path.
     */
    private function getBladeFiles($path)
    {
        if (File::isFile($path) && str_ends_with($path, '.blade.php')) {
            return [$path];
        }

        if (File::isDirectory($path)) {
            return File::allFiles($path, true);
        }

        return [];
    }

    /**
     * Update layout components.
     */
    private function updateLayouts($content)
    {
        return str_replace('<x-template-layout>', '<x-app-layout>', $content);
    }

    /**
     * Fix reset buttons.
     */
    private function fixResetButtons($content)
    {
        // Replace type="reset" with type="button" and add onclick
        $content = preg_replace(
            '/<button type="reset"([^>]*)>(\s*<span[^>]*>Reset<\/span>\s*)<\/button>/',
            '<button type="button"$1 onclick="window.location.href=\'{{ url()->current() }}\'">$2</button>',
            $content
        );

        // Also handle if it's submit with Reset text
        $content = preg_replace(
            '/<button type="submit"([^>]*)>(\s*<span[^>]*>Reset<\/span>\s*)<\/button>/',
            '<button type="button"$1 onclick="window.location.href=\'{{ url()->current() }}\'">$2</button>',
            $content
        );

        return $content;
    }

    /**
     * Add sortable headers.
     */
    private function addSortableHeaders($content)
    {
        // Simple replacement for common headers
        $replacements = [
            '<th>ID</th>' => '<th><a href="{{ sortUrl(\'id\', \'user.getData\') }}">ID</a></th>',
            '<th>Username</th>' => '<th><a href="{{ sortUrl(\'username\', \'user.getData\') }}">Username</a></th>',
            '<th>Email</th>' => '<th><a href="{{ sortUrl(\'email\', \'user.getData\') }}">Email</a></th>',
            '<th>Role</th>' => '<th><a href="{{ sortUrl(\'role\', \'user.getData\') }}">Role</a></th>',
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Fix form actions.
     */
    private function fixFormActions($content)
    {
        // Change action to current URL
        $content = preg_replace(
            '/action="{{ route\(\'[^\'"]+\') }}"/',
            'action=""',
            $content
        );

        return $content;
    }
}
