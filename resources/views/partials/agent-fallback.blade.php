{{--
    DISC-63: production has no SSR, so without JavaScript the page is only
    the boot loader. AI agents and simple crawlers that don't run JavaScript
    read this instead: one <h1> naming the page, the footer's own links
    (About, Contact, policies), every active category and sub-category
    (DISC-95: the header menu only puts those links in the page once it is
    opened) and the saved contact details as tel:/mailto: links. Browsers
    with JavaScript never render <noscript>, so visitors see
    no change. Saved settings only: the footer's display placeholders are
    never shown here.
--}}
@php
    $fallbackSettings = \App\Support\LayoutCache::settings();
    $fallbackEmail = is_string($fallbackSettings['contact_email'] ?? null) ? trim($fallbackSettings['contact_email']) : '';
    $fallbackPhone = is_string($fallbackSettings['contact_phone'] ?? null) ? trim($fallbackSettings['contact_phone']) : '';
    $fallbackLinks = [
        'Home' => '/',
        'All products' => '/products',
        'About' => '/about',
        'Contact' => '/contact',
        'Blog' => '/blog',
        'Privacy Policy' => '/privacy-policy',
        'Terms of Service' => '/terms',
        'Return Policy' => '/return-policy',
        'Refund Policy' => '/refund-policy',
        'Shipping Policy' => '/shipping-policy',
        'FAQ' => '/faq',
    ];
    $fallbackCategories = \App\Support\LayoutCache::navCategories();
@endphp
<noscript>
    <header>
        <h1>{{ $title }}</h1>
        <p>{{ $description }}</p>
    </header>
    <nav aria-label="Site">
        <ul>
            @foreach($fallbackLinks as $label => $path)
                <li><a href="{{ url($path) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
    @if($fallbackCategories !== [])
        <nav aria-label="Categories">
            <ul>
                @foreach($fallbackCategories as $fallbackCategory)
                    <li>
                        <a href="{{ route('category.show', $fallbackCategory['slug']) }}">{{ $fallbackCategory['name'] }}</a>
                        @if(! empty($fallbackCategory['sub_categories']))
                            <ul>
                                @foreach($fallbackCategory['sub_categories'] as $fallbackSubCategory)
                                    <li><a href="{{ route('category.show', $fallbackSubCategory['slug']) }}">{{ $fallbackSubCategory['name'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
    @if($fallbackEmail !== '' || $fallbackPhone !== '')
        <address>
            @if($fallbackPhone !== '')
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $fallbackPhone) }}">{{ $fallbackPhone }}</a>
            @endif
            @if($fallbackEmail !== '')
                <a href="mailto:{{ $fallbackEmail }}">{{ $fallbackEmail }}</a>
            @endif
        </address>
    @endif
</noscript>
