<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class VitoSetup extends Command
{
    protected $signature   = 'vito:setup';
    protected $description = 'Run all post-deploy setup steps (OAuth clients, storage link, queue table)';

    public function handle(): int
    {
        $this->info('Running Vito post-deploy setup...');

        // 1. Ensure the jobs table exists (needed when QUEUE_CONNECTION=database)
        if (!$this->tableExists('jobs')) {
            $this->warn('jobs table missing — running queue:table migration.');
            Artisan::call('queue:table');
            Artisan::call('migrate', ['--force' => true]);
        }

        // 2. OAuth RSA keys
        if (!file_exists(storage_path('oauth-private.key'))) {
            $this->info('Generating Passport RSA keys...');
            Artisan::call('passport:keys', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            $this->info('Passport RSA keys already exist — skipping key generation.');
        }

        // 3. OAuth clients (personal access + password grant)
        $clientCount = DB::table('oauth_clients')->count();
        if ($clientCount < 2) {
            $this->info('Creating OAuth clients via passport:install...');
            Artisan::call('passport:install', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            $this->info('OAuth clients already exist — skipping passport:install.');
        }

        // 4. Storage symlink
        if (!file_exists(public_path('storage'))) {
            $this->info('Creating storage symlink...');
            Artisan::call('storage:link');
            $this->line(Artisan::output());
        } else {
            $this->info('Storage symlink already exists — skipping.');
        }

        $this->info('Vito setup complete.');
        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::table($table)->limit(1)->get();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
