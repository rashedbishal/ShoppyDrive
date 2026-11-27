import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { Textarea } from '@/components/ui/textarea';

/**
 * DISC-87: a page's search-engine title and description, as saved through
 * admin.settings.update. Both are optional; the hints say what the page
 * uses when they're left blank. Limits match SettingController's rules.
 */
export function SeoSettingsCard({
    metaTitle,
    metaDescription,
    onMetaTitleChange,
    onMetaDescriptionChange,
    titleFallback,
    descriptionFallback,
    errors = {},
}: {
    metaTitle: string;
    metaDescription: string;
    onMetaTitleChange: (value: string) => void;
    onMetaDescriptionChange: (value: string) => void;
    titleFallback: string;
    descriptionFallback: string;
    errors?: { title?: string; description?: string };
}) {
    return (
        <Card className="gap-3 py-4 shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-3">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Search engine (SEO)
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 pt-0">
                <p className="text-xs text-slate-600">
                    What Google and link previews show for this page. Both
                    fields are optional.
                </p>
                <SettingsField
                    label="SEO Meta Title"
                    hint={`Aim for 50–60 characters; the site name is added after it. Leave blank to use “${titleFallback}”.`}
                >
                    <input
                        maxLength={70}
                        className={settingsInputClass}
                        value={metaTitle}
                        placeholder={titleFallback}
                        onChange={(e) => onMetaTitleChange(e.target.value)}
                    />
                    <CharCount
                        value={metaTitle}
                        max={70}
                        className="mt-0.5 block text-right text-[11px] leading-none"
                    />
                    {errors.title && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.title}
                        </p>
                    )}
                </SettingsField>
                <SettingsField
                    label="SEO Meta Description"
                    hint={`Aim for 120–160 characters. Leave blank to use ${descriptionFallback}.`}
                >
                    <Textarea
                        maxLength={160}
                        showCount
                        textareaClassName={settingsInputClass}
                        rows={2}
                        value={metaDescription}
                        onChange={(e) =>
                            onMetaDescriptionChange(e.target.value)
                        }
                    />
                    {errors.description && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.description}
                        </p>
                    )}
                </SettingsField>
            </CardContent>
        </Card>
    );
}
