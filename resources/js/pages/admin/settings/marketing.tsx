import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

type MarketingSettings = {
    custom_header_code: string;
    custom_footer_code: string;
    google_analytics_id: string;
    facebook_pixel_id: string;
    facebook_conversions_api_test_event_code: string;
    facebook_conversions_api_enabled: boolean;
    facebook_conversions_api_token_masked: string;
    has_facebook_conversions_api_token: boolean;
};

type Props = {
    settings: MarketingSettings;
    canEditCustomCode: boolean;
};

export default function MarketingSettingsEdit({
    settings,
    canEditCustomCode,
}: Props) {
    const customCodeForm = useForm({
        _method: 'PUT',
        custom_header_code: settings.custom_header_code,
        custom_footer_code: settings.custom_footer_code,
    });

    const handleCustomCodeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        customCodeForm.post(
            route('admin.settings.marketing.custom-code.update'),
        );
    };

    const trackingForm = useForm({
        _method: 'PUT',
        google_analytics_id: settings.google_analytics_id,
        facebook_pixel_id: settings.facebook_pixel_id,
        facebook_conversions_api_token: '',
        facebook_conversions_api_test_event_code:
            settings.facebook_conversions_api_test_event_code,
        facebook_conversions_api_enabled:
            settings.facebook_conversions_api_enabled,
    });

    const handleTrackingSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        trackingForm.post(
            route('admin.settings.marketing.facebook-conversions-api.update'),
        );
    };

    return (
        <>
            <Head title="Marketing & Tracking — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Marketing &amp; Tracking
                    </h1>
                    <p className="text-sm text-slate-600">
                        Custom tracking code and Facebook attribution. Nothing
                        here loads on admin pages — only the storefront.
                    </p>
                </div>

                {/* Its own <form> — never nested inside the one below. */}
                <form onSubmit={handleCustomCodeSubmit} className="space-y-3">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Custom Code
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <SettingsField
                                label="Custom Header Code"
                                hint="Paste any script here — a Google Tag Manager container snippet, a tracking pixel, anything with <script> tags. Rendered exactly as pasted, just before </head>, on every storefront page."
                            >
                                <Textarea
                                    maxLength={20000}
                                    showCount
                                    textareaClassName={`${settingsInputClass} font-mono text-xs`}
                                    rows={8}
                                    disabled={!canEditCustomCode}
                                    error={
                                        customCodeForm.errors.custom_header_code
                                    }
                                    value={
                                        customCodeForm.data.custom_header_code
                                    }
                                    onChange={(e) =>
                                        customCodeForm.setData(
                                            'custom_header_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="<!-- e.g. Google Tag Manager -->&#10;<script>...</script>"
                                />
                            </SettingsField>
                            <SettingsField
                                label="Custom Footer Code"
                                hint="Rendered just before </body>. GTM's own noscript fallback is written for right after <body> opens, but it still works correctly here too."
                            >
                                <Textarea
                                    maxLength={20000}
                                    showCount
                                    textareaClassName={`${settingsInputClass} font-mono text-xs`}
                                    rows={6}
                                    disabled={!canEditCustomCode}
                                    error={
                                        customCodeForm.errors.custom_footer_code
                                    }
                                    value={
                                        customCodeForm.data.custom_footer_code
                                    }
                                    onChange={(e) =>
                                        customCodeForm.setData(
                                            'custom_footer_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="<!-- e.g. a noscript fallback -->&#10;<noscript>...</noscript>"
                                />
                            </SettingsField>

                            {!canEditCustomCode && (
                                <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    <Lock className="mt-0.5 size-4 shrink-0" />
                                    Only the main (mother) admin can change
                                    custom header/footer code. You can view it
                                    here.
                                </div>
                            )}

                            {canEditCustomCode && (
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={customCodeForm.processing}
                                >
                                    Save Custom Code
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </form>

                {/* Its own <form> too — three independent forms on this
                    page, never nested inside each other. */}
                <form onSubmit={handleTrackingSubmit} className="space-y-3">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Facebook Conversions API
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label="Google Analytics ID"
                                    hint="e.g. G-XXXXXXXX or UA-XXXXXXXX"
                                >
                                    <input
                                        maxLength={30}
                                        className={settingsInputClass}
                                        value={
                                            trackingForm.data
                                                .google_analytics_id
                                        }
                                        onChange={(e) =>
                                            trackingForm.setData(
                                                'google_analytics_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="G-XXXXXXXXXX"
                                    />
                                    <CharCount
                                        value={
                                            trackingForm.data
                                                .google_analytics_id
                                        }
                                        max={30}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField
                                    label="Facebook Pixel ID"
                                    hint="Numbers only, from Events Manager"
                                >
                                    <input
                                        maxLength={32}
                                        className={settingsInputClass}
                                        value={
                                            trackingForm.data.facebook_pixel_id
                                        }
                                        onChange={(e) =>
                                            trackingForm.setData(
                                                'facebook_pixel_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="123456789012345"
                                    />
                                    <CharCount
                                        value={
                                            trackingForm.data.facebook_pixel_id
                                        }
                                        max={32}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                            </div>
                            <SettingsField
                                label="Conversions API Access Token"
                                hint={
                                    settings.has_facebook_conversions_api_token
                                        ? `Currently set, ending in ${settings.facebook_conversions_api_token_masked}. Leave blank to keep it unchanged.`
                                        : 'Generate this in Events Manager → Settings → Conversions API.'
                                }
                            >
                                <input
                                    maxLength={255}
                                    type="password"
                                    autoComplete="off"
                                    className={settingsInputClass}
                                    value={
                                        trackingForm.data
                                            .facebook_conversions_api_token
                                    }
                                    onChange={(e) =>
                                        trackingForm.setData(
                                            'facebook_conversions_api_token',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.has_facebook_conversions_api_token
                                            ? '•••••••••••••••••••••'
                                            : 'Paste the access token'
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Test Event Code"
                                hint="Optional — shows events in Events Manager's Test Events tool instead of counting toward live ad data. Clear it once you've verified the integration works."
                            >
                                <input
                                    maxLength={20}
                                    className={settingsInputClass}
                                    value={
                                        trackingForm.data
                                            .facebook_conversions_api_test_event_code
                                    }
                                    onChange={(e) =>
                                        trackingForm.setData(
                                            'facebook_conversions_api_test_event_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="TEST12345"
                                />
                                <CharCount
                                    value={
                                        trackingForm.data
                                            .facebook_conversions_api_test_event_code
                                    }
                                    max={20}
                                    className="mt-1 block text-right"
                                />
                            </SettingsField>
                            <SettingsField
                                label="Send Purchase Events"
                                hint="When on, a Purchase event is sent to Facebook for every order placed (customer checkouts and admin-created orders alike), using the Pixel ID and token above."
                            >
                                <Switch
                                    checked={
                                        trackingForm.data
                                            .facebook_conversions_api_enabled
                                    }
                                    onCheckedChange={(checked) =>
                                        trackingForm.setData(
                                            'facebook_conversions_api_enabled',
                                            checked,
                                        )
                                    }
                                />
                            </SettingsField>

                            <Button
                                type="submit"
                                size="sm"
                                disabled={trackingForm.processing}
                            >
                                Save Facebook Conversions API Settings
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
