<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuthManagement\Entities\QrToken;

class PruneExpiredQrTokens extends Command
{
    protected $signature = 'vito:prune-qr-tokens';
    protected $description = 'Delete QR tokens that expired more than 30 days ago';

    public function handle(): int
    {
        $deleted = QrToken::where('expires_at', '<', now()->subDays(30))->delete();
        $this->info("Pruned {$deleted} expired QR tokens.");
        return Command::SUCCESS;
    }
}
