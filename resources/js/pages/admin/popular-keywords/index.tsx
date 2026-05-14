import { Head, router, useForm, usePage } from '@inertiajs/react';
import { GripVertical, Plus, Save, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Keyword = { id: number; keyword: string; sort_order: number; is_active: boolean };
type Props = { keywords: Keyword[] };

function KeywordRow({ keyword, onDelete }: { keyword: Keyword; onDelete: (id: number) => void }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing } = useForm({
        keyword: keyword.keyword,
        sort_order: keyword.sort_order,
        is_active: keyword.is_active,
    });

    const save = () => {
        put(route('admin.popular-keywords.update', keyword.id), {
            onSuccess: () => setEditing(false),
        });
    };

    const inputClass = 'h-8 rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <div className="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50/60">
            <GripVertical className="size-4 shrink-0 text-slate-300" />

            {editing ? (
                <>
                    <input
                        autoFocus
                        className={`${inputClass} flex-1`}
                        value={data.keyword}
                        onChange={(e) => setData('keyword', e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && save()}
                    />
                    <input
                        type="number"
                        min={0}
                        className={`${inputClass} w-16`}
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', Number(e.target.value))}
                        title="Sort order"
                    />
                    <label className="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer shrink-0">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
                        Active
                    </label>
                    <Button size="sm" disabled={processing} onClick={save} className="bg-[#c8102e] text-white hover:bg-[#a50d25] shrink-0">
                        <Save className="size-3.5" />
                    </Button>
                    <button type="button" onClick={() => setEditing(false)} className="text-xs text-slate-400 hover:text-slate-600 shrink-0">Cancel</button>
                </>
            ) : (
                <>
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="flex-1 text-left"
                    >
                        <span className={`text-sm font-medium ${data.is_active ? 'text-slate-800' : 'text-slate-400 line-through'}`}>
                            {keyword.keyword}
                        </span>
                    </button>
                    <span className="text-[11px] text-slate-400 shrink-0">#{keyword.sort_order}</span>
                    {!keyword.is_active && (
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-400 shrink-0">hidden</span>
                    )}
                    <button
                        type="button"
                        onClick={() => onDelete(keyword.id)}
                        className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-colors"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </>
            )}
        </div>
    );
}

export default function PopularKeywordsIndex({ keywords }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const { data, setData, post, processing, reset } = useForm({ keyword: '' });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.popular-keywords.store'), {
            onSuccess: () => reset(),
        });
    };

    const handleDelete = (id: number) => {
        if (!confirm('Delete this keyword?')) return;
        router.delete(route('admin.popular-keywords.destroy', id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Popular Search Keywords — Admin" />
            <div className="max-w-xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold text-slate-900">Popular Search Keywords</h1>
                            <Search className="size-5 text-slate-400" />
                        </div>
                        <p className="text-sm text-slate-500">
                            These keywords appear in the search bar when customers click on it.
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                {/* Add keyword */}
                <Card className="shadow-sm p-4">
                    <form onSubmit={handleAdd} className="flex gap-2">
                        <input
                            className="h-9 flex-1 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                            value={data.keyword}
                            onChange={(e) => setData('keyword', e.target.value)}
                            placeholder="New keyword…"
                        />
                        <Button type="submit" disabled={processing || !data.keyword.trim()} className="bg-[#c8102e] text-white hover:bg-[#a50d25] shrink-0">
                            <Plus className="size-4" />
                            Add
                        </Button>
                    </form>
                </Card>

                {/* Keywords list */}
                <Card className="overflow-hidden p-0 shadow-sm">
                    {keywords.length === 0 ? (
                        <div className="py-10 text-center text-sm text-slate-400">
                            No keywords yet. Add one above.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-50">
                            {keywords.map((kw) => (
                                <KeywordRow key={kw.id} keyword={kw} onDelete={handleDelete} />
                            ))}
                        </div>
                    )}
                </Card>

                <p className="text-xs text-slate-400">
                    Click any keyword to edit it inline. Sort order controls the display sequence (lower = first).
                    Uncheck "Active" to hide a keyword without deleting it.
                </p>
            </div>
        </>
    );
}
