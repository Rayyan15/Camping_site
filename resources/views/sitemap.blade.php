<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ $homeUrl }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
@foreach($unitTypes as $unitType)
    <url>
        <loc>{{ route('tenda.show', $unitType->slug) }}</loc>
        <lastmod>{{ $unitType->updated_at?->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
