<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\SocialManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RefreshSocialTokens extends Command
{
    protected $signature = 'social:refresh-tokens {--days=7 : Refresh tokens expiring within this many days}';

    protected $description = 'Refresh social account access tokens that are about to expire';

    public function handle(SocialManager $manager): int
    {
        $accounts = SocialAccount::query()
            ->where('is_active', true)
            ->where('needs_reauth', false)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays((int) $this->option('days')))
            ->get();

        foreach ($accounts as $account) {
            try {
                $ok = $manager->driver($account->provider)->refresh($account);
            } catch (Throwable $e) {
                // Transient (rate limit / outage): try again on the next run.
                Log::warning('Social token refresh failed', ['account' => $account->id, 'error' => $e->getMessage()]);
                $account->forceFill(['last_error' => Str::limit($e->getMessage(), 2000)])->save();
                $this->components->warn("{$account->displayName()}: {$e->getMessage()}");

                continue;
            }

            if ($ok) {
                $this->components->info("Refreshed {$account->displayName()}");

                continue;
            }

            $account->forceFill([
                'needs_reauth' => true,
                'last_error' => 'The access token could not be refreshed. Reconnect the account.',
            ])->save();

            $this->components->warn("{$account->displayName()} needs to be reconnected.");
        }

        if ($accounts->isEmpty()) {
            $this->components->info('No tokens need refreshing.');
        }

        return self::SUCCESS;
    }
}
