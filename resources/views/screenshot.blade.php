<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Screenshot :: {{ $screenshot->created_at->format('l, M j, Y \a\t g:iA T') }}</title>

    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('screenshot', compact('screenshot')) }}" />
    <meta property="og:title" content="Screenshot :: {{ $screenshot->created_at->format('l, M j, Y \a\t g:iA T') }}" />
    <meta property="og:image" content="{{ route('screenshot.opengraph', compact('screenshot')) }}" />

    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="{{ route('screenshot', compact('screenshot')) }}" />
    <meta property="twitter:title" content="Screenshot :: {{ $screenshot->created_at->format('l, M j, Y \a\t g:iA T') }}" />
    <meta property="twitter:image" content="{{ route('screenshot.opengraph', compact('screenshot')) }}" />

    @vite(['resources/css/screenshots.css'])
</head>
<body>
    <div class="flex h-full justify-center items-center">
        <div class="relative flex-1 w-full max-w-fit mx-0 md:mx-4">
            <div class="absolute -inset-4">
                <div
                class="w-full h-full mx-auto lg:mx-0 opacity-30 blur-lg bg-gradient-to-r from-blue-600 to-violet-600">
            </div>
            </div>
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-tr from-pink-300/70 to-blue-300/70 p-1 shadow-lg">
                <img class="max-w-full w-auto rounded-xl" src="{{ route('screenshot.raw', compact('screenshot')) }}" alt="" >
            </div>
        </div>
    </div>
</body>
</html>
