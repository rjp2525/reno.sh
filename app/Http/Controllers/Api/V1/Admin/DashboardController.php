<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PhotoProcessingStatus;
use App\Enums\PostStatus;
use App\Enums\PrintOrderStatus;
use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrintOrderResource;
use App\Http\Resources\SocialPostResource;
use App\Models\Photo;
use App\Models\Post;
use App\Models\PrintOrder;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $upcoming = SocialPost::with('targets.account')
            ->where('status', SocialPostStatus::SCHEDULED)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $paid = PrintOrder::whereNotIn('status', [PrintOrderStatus::PENDING_PAYMENT, PrintOrderStatus::CANCELLED]);

        return response()->json(['data' => [
            'posts' => [
                'published' => Post::published()->count(),
                'drafts' => Post::where('status', PostStatus::DRAFT)->count(),
                'scheduled' => Post::where('status', PostStatus::SCHEDULED)->count(),
            ],
            'photos' => [
                'total' => Photo::count(),
                'processing' => Photo::whereIn('processing_status', [PhotoProcessingStatus::PENDING, PhotoProcessingStatus::PROCESSING])->count(),
                'failed' => Photo::where('processing_status', PhotoProcessingStatus::FAILED)->count(),
            ],
            'social' => [
                'scheduled' => SocialPost::where('status', SocialPostStatus::SCHEDULED)->count(),
                'failed_last_7d' => SocialPostTarget::where('status', SocialTargetStatus::FAILED)->where('updated_at', '>=', now()->subDays(7))->count(),
                'next' => $upcoming->first() ? new SocialPostResource($upcoming->first()) : null,
            ],
            'orders' => [
                'open' => PrintOrder::open()->count(),
                'revenue_30d' => (int) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('total'),
                'currency' => config('prints.currency'),
            ],
            'recent_orders' => PrintOrderResource::collection(PrintOrder::with('items.photo', 'items.product')->whereNotNull('paid_at')->latest('paid_at')->limit(5)->get()),
            'upcoming_social' => SocialPostResource::collection($upcoming),
        ]]);
    }
}
