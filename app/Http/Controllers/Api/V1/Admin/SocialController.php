<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SocialPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SocialAccountResource;
use App\Http\Resources\SocialPostResource;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Social\SocialComposer;
use App\Services\Social\SocialScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SocialController extends Controller
{
    public function accounts(): AnonymousResourceCollection
    {
        return SocialAccountResource::collection(SocialAccount::orderBy('provider')->orderBy('name')->get());
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return SocialPostResource::collection($this->query($request)->paginate($this->perPage($request)));
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $tz = config('app.timezone');

        $days = $this->query($request)->get()
            ->groupBy(fn (SocialPost $p) => ($p->scheduled_at ?? $p->published_at ?? $p->created_at)->timezone($tz)->toDateString())
            ->map(fn ($posts, $date) => ['date' => $date, 'posts' => SocialPostResource::collection($posts->values())])
            ->sortKeys()
            ->values();

        return response()->json(['data' => $days]);
    }

    public function store(Request $request, SocialComposer $composer): SocialPostResource
    {
        $post = $composer->create($this->validated($request), $request->user()->id);

        return new SocialPostResource($post->load('targets.account'));
    }

    public function show(SocialPost $post): SocialPostResource
    {
        return new SocialPostResource($post->load('targets.account', 'source'));
    }

    public function update(Request $request, SocialPost $post, SocialComposer $composer): SocialPostResource
    {
        return new SocialPostResource($composer->update($post, $this->validated($request, partial: true))->load('targets.account'));
    }

    public function destroy(SocialPost $post): Response
    {
        abort_if($post->status === SocialPostStatus::PUBLISHING, 409, 'This post is publishing right now.');
        $post->delete();

        return response()->noContent();
    }

    public function publish(SocialPost $post, SocialScheduler $scheduler): JsonResponse
    {
        abort_if($post->status === SocialPostStatus::PUBLISHED, 409, 'Already published.');

        $retry = in_array($post->status, [SocialPostStatus::FAILED, SocialPostStatus::PARTIALLY_PUBLISHED], true);
        $scheduler->dispatch($post, onlyFailed: $retry);

        return (new SocialPostResource($post->fresh('targets.account')))->response()->setStatusCode(202);
    }

    private function query(Request $request)
    {
        return SocialPost::with('targets.account', 'source')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $from) => $q->where(fn ($w) => $w
                ->where('scheduled_at', '>=', Carbon::parse($from)->startOfDay())
                ->orWhere(fn ($x) => $x->whereNull('scheduled_at')->where('created_at', '>=', Carbon::parse($from)->startOfDay()))))
            ->when($request->query('to'), fn ($q, $to) => $q->where(fn ($w) => $w
                ->where('scheduled_at', '<=', Carbon::parse($to)->endOfDay())
                ->orWhere(fn ($x) => $x->whereNull('scheduled_at')->where('created_at', '<=', Carbon::parse($to)->endOfDay()))))
            ->orderByRaw('coalesce(scheduled_at, created_at) desc');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'body' => [$req, 'nullable', 'string', 'max:63206'],
            'account_ids' => [$partial ? 'sometimes' : 'required', 'array'],
            'account_ids.*' => ['string', Rule::exists('social_accounts', 'id')],
            'overrides' => ['sometimes', 'nullable', 'array'],
            'overrides.*' => ['nullable', 'string', 'max:63206'],
            'media' => ['sometimes', 'nullable', 'array', 'max:10'],
            'media.*' => ['string', 'max:255'],
            'link_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'publish_now' => ['sometimes', 'boolean'],
            'draft' => ['sometimes', 'boolean'],
        ]);
    }
}
