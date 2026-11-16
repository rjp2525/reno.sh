<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use RuntimeException;

/** A connect attempt failed in a way the user should see (denied, bad state, API error). */
class OAuthException extends RuntimeException {}
