@php
    $logoPath = \App\Models\Setting::where('key', 'logo')->value('value');
    $logoUrl  = $logoPath ? config('app.url') . $logoPath : null;
    $siteName = \App\Models\Setting::where('key', 'site_name')->value('value') ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<title>{{ $emailSubject }}</title>
<style>
  body { margin: 0; padding: 0; background: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
  .wrapper { width: 100%; background: #f4f5f7; padding: 40px 16px; }
  .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
  .header { background: #1a1a2e; padding: 20px 40px; }
  .header-inner { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
  .header-logo-img { height: 40px; width: auto; object-fit: contain; display: block; background: #ffffff; border-radius: 8px; padding: 6px 10px; }
  .header-site-name { color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
  .header-badge { display: block; margin-top: 10px; }
  .badge { display: inline-block; background: rgba(255,255,255,0.12); color: #fff; font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; border-radius: 100px; padding: 4px 12px; }
  .product-image { width: 100%; max-height: 360px; object-fit: cover; display: block; }
  .body { padding: 36px 40px 32px; }
  .product-name { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 8px; line-height: 1.3; }
  .product-brand { font-size: 12px; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: #9ca3af; margin: 0 0 16px; }
  .product-description { font-size: 15px; color: #4b5563; line-height: 1.75; margin: 0 0 24px; }
  .price-row { display: flex; align-items: baseline; gap: 10px; margin-bottom: 28px; }
  .price { font-size: 26px; font-weight: 800; color: #c8102e; }
  .compare-price { font-size: 16px; color: #9ca3af; text-decoration: line-through; }
  .discount-badge { font-size: 12px; font-weight: 700; background: #fef3c7; color: #92400e; border-radius: 6px; padding: 3px 8px; }
  .cta-btn { display: inline-block; background: #1a1a2e; color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 36px; border-radius: 8px; letter-spacing: 0.06em; text-transform: uppercase; }
  .divider { border: none; border-top: 1px solid #f3f4f6; margin: 32px 0 0; }
  .footer { padding: 24px 40px; background: #fafafa; text-align: center; }
  .footer p { margin: 0 0 6px; font-size: 12px; color: #9ca3af; }
  .footer a { color: #9ca3af; text-decoration: underline; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <!-- Header -->
    <div class="header">
      <a href="{{ config('app.url') }}" class="header-inner" style="display:inline-flex;align-items:center;gap:12px;text-decoration:none;">
        @if($logoUrl)
          <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="header-logo-img" />
        @endif
        @unless($logoUrl)
        <span class="header-site-name">{{ $siteName }}</span>
        @endunless
      </a>
      <div class="header-badge">
        <span class="badge">Featured Product</span>
      </div>
    </div>

    <!-- Product Image -->
    @if($imageUrl)
    <a href="{{ $productUrl }}"><img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="product-image" /></a>
    @endif

    <!-- Body -->
    <div class="body">
      @if($product->brand)
      <p class="product-brand">{{ $product->brand->name }}</p>
      @endif

      <h1 class="product-name">{{ $product->name }}</h1>

      @if($product->short_description)
      <p class="product-description">{{ $product->short_description }}</p>
      @endif

      <div class="price-row">
        <span class="price">৳{{ number_format($product->price, 2) }}</span>
        @if($product->compare_price && $product->compare_price > $product->price)
          <span class="compare-price">৳{{ number_format($product->compare_price, 2) }}</span>
          <span class="discount-badge">
            {{ round((($product->compare_price - $product->price) / $product->compare_price) * 100) }}% OFF
          </span>
        @endif
      </div>

      <a href="{{ $productUrl }}" class="cta-btn">
        View Product
      </a>
    </div>

    <div class="divider"></div>

    <!-- Footer -->
    <div class="footer">
      <p>You are receiving this email because you subscribed to {{ $siteName }} newsletters.</p>
      <p>
        &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        &nbsp;&middot;&nbsp;
        <a href="{{ config('app.url') }}">Visit Store</a>
        &nbsp;&middot;&nbsp;
        <a href="{{ route('newsletter.manage') }}">Manage subscription / Unsubscribe</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
