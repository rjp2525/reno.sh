<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Prints\PrintCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PrintCheckout $checkout): Response
    {
        $secret = config('prints.stripe.webhook_secret');
        abort_if(blank($secret), 404);

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            abort(400, 'Invalid signature');
        }

        $object = $event->data->object;

        if ($object instanceof Session) {
            match ($event->type) {
                'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $checkout->markPaid($object),
                'checkout.session.expired', 'checkout.session.async_payment_failed' => $checkout->markExpired($object),
                default => null,
            };
        }

        return response('ok');
    }
}
