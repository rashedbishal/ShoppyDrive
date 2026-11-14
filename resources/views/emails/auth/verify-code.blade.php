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
<title>Your verification code</title>
{{-- Admin › Settings › Brand Colour (App\Support\BrandColor); the default is the original red. --}}
@php($brandPalette = \App\Support\BrandColor::palette())
<style>
  body { margin: 0; padding: 0; background: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
  .wrapper { width: 100%; background: #f4f5f7; padding: 40px 16px; }
  .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
  .header { background: #1a1a2e; padding: 24px 40px; }
  .header-inner { display: flex; align-items: center; gap: 12px; text-decoration: none; }
  .header-logo-img { height: 40px; width: auto; object-fit: contain; display: block; background: #ffffff; border-radius: 8px; padding: 6px 10px; }
  .header-site-name { color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
  .body { padding: 40px 40px 32px; }
  .body-content { color: #374151; font-size: 15px; line-height: 1.75; text-align: center; }
  .code { display: inline-block; margin: 24px 0; padding: 16px 28px; border-radius: 12px; background: {{ $brandPalette['brand'] === \App\Support\BrandColor::DEFAULT ? '#fef2f2' : $brandPalette['light'] }}; color: {{ $brandPalette['brand'] }}; font-size: 36px; font-weight: 700; letter-spacing: 8px; }
  .divider { border: none; border-top: 1px solid #f3f4f6; margin: 0; }
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
    </div>

    <!-- Body -->
    <div class="body">
      <div class="body-content">
        <p>Use this code to verify your email address and activate your {{ $siteName }} account:</p>
        <div class="code">{{ $code }}</div>
        <p>This code expires in 15 minutes. If you didn't request this, you can safely ignore this email.</p>
      </div>
    </div>

    <hr class="divider" />

    <!-- Footer -->
    <div class="footer">
      <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
    </div>
  </div>
</div>
</body>
</html>
