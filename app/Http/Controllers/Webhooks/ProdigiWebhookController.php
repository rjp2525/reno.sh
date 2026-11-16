<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PrintOrder;
use App\Services\Prints\ProdigiClient;
use App\Services\Prints\ProdigiSync;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Prodigi status callbacks (CloudEvents). They're unsigned, so we require the
 * shared token in the URL and then re-fetch the order from Prodigi rather than
 * trusting the posted body.
 */
class ProdigiWebhookController extends Controller
{
    public function __invoke(Request $request, ProdigiClient $prodigi, ProdigiSync $sync): Response
    {
        $token = (string) config('prints.prodigi.callback_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->query('token')), 404);

        $id = $request->input('data.order.id') ?? $request->input('order.id');
        $order = $id ? PrintOrder::where('prodigi_order_id', $id)->first() : null;

        if ($order) {
            $sync->apply($order, $prodigi->getOrder($id));
        }

        return response('ok');
    }
}
