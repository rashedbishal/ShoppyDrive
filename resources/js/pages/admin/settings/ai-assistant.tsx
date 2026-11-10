import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

type Props = {
    maskedKey: string;
    fullKey?: string;
    enabled: boolean;
    hasKey: boolean;
    selectedModel: string;
    selectedModelLabel: string;
    selectedModelUnavailable: boolean;
    availableModels: Record<string, string>;
};

export default function AiAssistantSettings({
    maskedKey,
    fullKey,
    enabled,
    hasKey,
    selectedModel,
    selectedModelLabel,
    selectedModelUnavailable,
    availableModels,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;
    const canReveal = fullKey !== undefined;

    const [showKey, setShowKey] = useState(false);

    const { data, setData, post, processing } = useForm({
        api_key: '',
        enabled,
        model: selectedModel,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.ai-assistant.update'));
    };

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-[#c8102e] bg-slate-50 text-slate-700';

    return (
        <>
            <Head title="AI Assistant Settings — Admin" />
            <div className="space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    AI Assistant Settings
                </h1>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {selectedModelUnavailable && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <p className="font-medium">
                            The selected model isn't available right now
                        </p>
                        <p className="mt-1 text-xs text-amber-700">
                            Groq's account no longer reports access to "
                            {selectedModelLabel}" — the assistant will fail on
                            every message until
                            {canReveal
                                ? ' you switch to one of the models below and save.'
                                : ' someone with update access switches the model.'}
                        </p>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Groq API Key
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5 pt-3">
                                <p className="text-xs text-slate-500">
                                    The API key from console.groq.com. Shoppers
                                    never see this — it's only used by the
                                    server when talking to Groq on their behalf.
                                </p>

                                {canReveal && hasKey && (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            Current Key
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                readOnly
                                                className={inputClass}
                                                value={
                                                    showKey && fullKey
                                                        ? fullKey
                                                        : maskedKey
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setShowKey((v) => !v)
                                                }
                                                className="shrink-0"
                                            >
                                                {showKey ? 'Hide' : 'Show'}
                                            </Button>
                                        </div>
                                        <p className="text-xs text-slate-400">
                                            Only the last 6 characters are shown
                                            for security.
                                        </p>
                                    </div>
                                )}

                                {canReveal ? (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            {hasKey
                                                ? 'Replace Key'
                                                : 'Paste Your API Key'}
                                        </label>
                                        <input
                                            className={inputClass}
                                            placeholder={
                                                hasKey
                                                    ? 'Leave blank to keep the current key'
                                                    : 'Paste the key from console.groq.com'
                                            }
                                            value={data.api_key}
                                            onChange={(e) =>
                                                setData(
                                                    'api_key',
                                                    e.target.value,
                                                )
                                            }
                                            autoComplete="off"
                                        />
                                    </div>
                                ) : (
                                    <p className="text-xs text-slate-400">
                                        You have view-only access. Ask the owner
                                        for update access to change this.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Assistant
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                {canReveal && (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            Model
                                        </label>
                                        <Select
                                            value={data.model}
                                            onValueChange={(value) =>
                                                setData('model', value)
                                            }
                                            options={Object.entries(
                                                availableModels,
                                            ).map(([value, label]) => ({
                                                value,
                                                label,
                                            }))}
                                        />
                                        <p className="text-xs text-slate-400">
                                            Only models that support tool
                                            calling (needed for product search
                                            and order lookup) are offered here.
                                        </p>
                                    </div>
                                )}

                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-slate-700">
                                            Show the assistant on the storefront
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            Adds a third button to the WhatsApp
                                            / Messenger widget. Turning this off
                                            hides it immediately without losing
                                            the key.
                                        </p>
                                    </div>
                                    {canReveal && (
                                        <Switch
                                            checked={data.enabled}
                                            onCheckedChange={(checked) =>
                                                setData('enabled', checked)
                                            }
                                        />
                                    )}
                                </div>

                                {canReveal && (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                    >
                                        Save
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}
