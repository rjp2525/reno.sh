<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Notifications\ContactFormNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Contact');
    }

    public function send(ContactRequest $request)
    {
        $validated = $request->validated();

        Notification::route('mail', config('mail.contact_to', 'hello@reno.sh'))
            ->notify(new ContactFormNotification(
                name: $validated['name'],
                email: $validated['email'],
                subject: $validated['subject'],
                contactMessage: $validated['message'],
            ));

        return back()->with('success', 'Thanks for reaching out! I\'ll get back to you soon.');
    }
}
