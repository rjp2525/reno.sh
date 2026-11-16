<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Signed-in admins may preview drafts and unpublished content on the public site. */
    protected function canPreview(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** ?per_page=N for API listings, clamped to 1..100 (default 20). */
    protected function perPage(Request $request, int $default = 20): int
    {
        return max(1, min(100, (int) $request->query('per_page', (string) $default)));
    }
}
