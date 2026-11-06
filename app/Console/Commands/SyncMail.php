<?php

namespace App\Console\Commands;

use App\Models\GmailAccount;
use App\Services\Gmail\GoogleOAuth;
use App\Services\Gmail\MailSync;
use Illuminate\Console\Command;

class SyncMail extends Command
{
    protected $signature = 'jobhunt:sync-mail {--account= : Only sync this Gmail address}';

    protected $description = 'Fetch job-related Gmail from the last 30 days (read-only)';

    public function handle(GoogleOAuth $oauth, MailSync $sync): int
    {
        if (! $oauth->configured()) {
            $this->error('Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env first. See docs/GMAIL_SETUP.md.');

            return self::FAILURE;
        }

        $only = null;

        if ($this->option('account')) {
            $only = GmailAccount::where('email', strtolower($this->option('account')))->first();

            if (! $only) {
                $this->error('No connected Gmail account with that address.');

                return self::FAILURE;
            }
        }

        $results = $sync->run($only);

        if ($results === null) {
            $this->warn('Another sync is already running.');

            return self::SUCCESS;
        }

        if ($results === []) {
            $this->info('No Gmail accounts to sync. Connect one on the Settings page.');

            return self::SUCCESS;
        }

        $this->table(
            ['Account', 'Found', 'New', 'Linked', 'Skipped', 'Error'],
            array_map(fn (array $result) => [
                $result['account'], $result['listed'], $result['stored'], $result['linked'], $result['skipped'], $result['error'] ?? '',
            ], $results),
        );

        return collect($results)->every(fn (array $result) => $result['error'] !== null) ? self::FAILURE : self::SUCCESS;
    }
}
