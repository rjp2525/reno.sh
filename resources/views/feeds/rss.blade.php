<?=
/* Echo tag so `<?xml ... ?>` isn't parsed as a PHP short tag. */
'<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <atom:link href="{{ url($meta['link']) }}" rel="self" type="application/rss+xml" />
        <title>{!! \Spatie\Feed\Helpers\Cdata::out($meta['title']) !!}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{!! \Spatie\Feed\Helpers\Cdata::out($meta['description']) !!}</description>
        <language>{{ $meta['language'] }}</language>
        <lastBuildDate>{{ $meta['updated'] }}</lastBuildDate>
        <generator>{{ config('app.url') }}</generator>
@foreach($items as $item)
        <item>
            <title>{!! \Spatie\Feed\Helpers\Cdata::out($item->title) !!}</title>
            <link>{{ url($item->link) }}</link>
            <guid isPermaLink="true">{{ url($item->link) }}</guid>
            <description>{!! \Spatie\Feed\Helpers\Cdata::out($item->summary) !!}</description>
@isset($item->content)
            <content:encoded>{!! \Spatie\Feed\Helpers\Cdata::out($item->content) !!}</content:encoded>
@endisset
            <dc:creator>{!! \Spatie\Feed\Helpers\Cdata::out($item->authorName) !!}</dc:creator>
            <pubDate>{{ $item->timestamp() }}</pubDate>
@foreach($item->category as $category)
            <category>{!! \Spatie\Feed\Helpers\Cdata::out($category) !!}</category>
@endforeach
        </item>
@endforeach
    </channel>
</rss>
