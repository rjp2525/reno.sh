<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrintOrderResource;
use App\Models\PrintOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PrintOrderResource::collection(PrintOrder::with('items.photo', 'items.product')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request)));
    }

    public function show(PrintOrder $order): PrintOrderResource
    {
        return new PrintOrderResource($order->load('items.photo', 'items.product'));
    }
}
