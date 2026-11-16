<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\OAuth\OAuthException;
use App\Services\Social\OAuth\OAuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GET /admin/social/connect/{provider}   → network consent screen
 * GET /admin/social/callback/{provider}  → upsert accounts, back to the admin list
 */
class OAuthController extends Controller
{
    public function __construct(private readonly OAuthFlow $flow) {}

    public function redirect(Request $request, SocialProvider $provider): RedirectResponse
    {
        abort_unless($provider->usesOAuth(), 404);

        try {
            return redirect()->away($this->flow->authorizationUrl($provider, $request->session()));
        } catch (OAuthException $e) {
            return $this->back('error', $e->getMessage());
        }
    }

    public function callback(Request $request, SocialProvider $provider): RedirectResponse
    {
        abort_unless($provider->usesOAuth(), 404);

        try {
            $accounts = $this->flow->handleCallback($provider, $request);
        } catch (OAuthException $e) {
            Log::warning('Social connect failed', ['provider' => $provider->value, 'error' => $e->getMessage()]);

            return $this->back('error', $e->getMessage());
        }

        if ($accounts === []) {
            return $this->back('error', match ($provider) {
                SocialProvider::FACEBOOK => 'No Facebook Pages were shared. Reconnect and select at least one Page.',
                SocialProvider::INSTAGRAM => 'No Instagram professional accounts found. Link an Instagram Business/Creator account to a Facebook Page, then reconnect and select that Page.',
                default => "{$provider->getLabel()} returned no accounts.",
            });
        }

        $names = collect($accounts)->map(fn (SocialAccount $a) => $a->name)->implode(', ');

        return $this->back('success', sprintf('Connected %d %s account%s: %s', count($accounts), $provider->getLabel(), count($accounts) === 1 ? '' : 's', $names));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect(url('/admin/social-accounts'))->with($type, $message);
    }
}
