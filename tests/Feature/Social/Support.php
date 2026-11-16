<?php

/*
 * Shared helpers for the social tests. Not a test file itself; each test
 * file require_once's it (Pest only autoloads *Test.php).
 */

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublisher;
use App\Services\Social\SocialPublishException;
use Illuminate\Support\Facades\Storage;

function socialTarget(SocialProvider $provider, array $post = [], array $account = []): SocialPostTarget
{
    $acc = SocialAccount::factory()->provider($provider)->create($account);
    $p = SocialPost::factory()->create($post);

    return SocialPostTarget::factory()->for($p, 'post')->for($acc, 'account')->create()->load(['post', 'account']);
}

/** Put a tiny fake image on the (faked) public disk and return its media entry. */
function socialImage(string $path = 'social/photo.jpg', string $contents = 'JPEG-BYTES'): array
{
    Storage::disk('public')->put($path, $contents);

    return ['path' => $path, 'mime' => 'image/jpeg'];
}

/** An unsigned JWT with the given exp, enough for expiry checks. */
function fakeJwt(int $exp): string
{
    $b64 = fn (array $v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');

    return $b64(['alg' => 'none']).'.'.$b64(['exp' => $exp, 'sub' => 'did:plc:test']).'.sig';
}

function expectPublishError(Closure $fn): SocialPublishException
{
    try {
        $fn();
    } catch (SocialPublishException $e) {
        return $e;
    }

    throw new RuntimeException('Expected a SocialPublishException');
}

/** Scriptable publisher for job tests. */
class FakeSocialPublisher implements SocialPublisher
{
    public int $publishes = 0;

    public int $refreshes = 0;

    public function __construct(
        public ?Throwable $throw = null,
        public bool $refreshResult = true,
    ) {}

    public function publish(SocialPostTarget $target): PublishResult
    {
        $this->publishes++;

        if ($this->throw) {
            throw $this->throw;
        }

        return new PublishResult('remote-'.$target->id, 'https://example.test/p/'.$target->id);
    }

    public function refresh(SocialAccount $account): bool
    {
        $this->refreshes++;

        return $this->refreshResult;
    }
}
