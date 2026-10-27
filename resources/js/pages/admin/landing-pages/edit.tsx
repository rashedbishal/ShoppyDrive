import { Head, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { maxLength, pattern, required, validate } from '@/lib/validate';

type Product = { id: number; name: string; price: number };

type Sections = {
    hero: {
        enabled: boolean;
        headline: string;
        subheadline: string;
        cta_text: string;
        bg_color: string;
    };
    benefits: { enabled: boolean; title: string; items: string[] };
    description: {
        enabled: boolean;
        title: string;
        use_product_description: boolean;
        custom_content: string;
    };
    gallery: { enabled: boolean; title: string };
    testimonials: {
        enabled: boolean;
        title: string;
        items: { name: string; rating: number; text: string }[];
    };
    faq: {
        enabled: boolean;
        title: string;
        items: { question: string; answer: string }[];
    };
    footer: { enabled: boolean; text: string };
};

type Props = {
    page: {
        id: number;
        title: string;
        slug: string;
        product_id: number;
        is_active: boolean;
    };
    sections: Sections;
    products: Product[];
};

function SectionWrap({
    title,
    enabled,
    onToggle,
    children,
}: {
    title: string;
    enabled: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Card className="shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div className="flex items-center gap-3">
                    <input
                        type="checkbox"
                        checked={enabled}
                        onChange={onToggle}
                        className="rounded border-slate-300"
                    />
                    <span className="text-sm font-semibold text-slate-700">
                        {title}
                    </span>
                    {!enabled && (
                        <span className="text-[10px] tracking-wide text-slate-400 uppercase">
                            hidden on page
                        </span>
                    )}
                </div>
                {enabled && (
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="text-slate-400 hover:text-slate-600"
                    >
                        {open ? (
                            <ChevronUp className="size-4" />
                        ) : (
                            <ChevronDown className="size-4" />
                        )}
                    </button>
                )}
            </div>
            {enabled && open && (
                <CardContent className="space-y-3 pt-4">{children}</CardContent>
            )}
        </Card>
    );
}

export default function LandingPageEdit({
    page,
    sections: initialSections,
    products,
}: Props) {
    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';
    const textareaClass =
        'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e] resize-none';
    const labelClass = 'mb-1 block text-xs font-medium text-slate-600';

    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            title: page.title,
            slug: page.slug,
            product_id: String(page.product_id),
            is_active: page.is_active,
            sections: initialSections,
        });

    const sec = data.sections;
    const setSec = (patch: Partial<Sections>) =>
        setData('sections', { ...sec, ...patch });

    return (
        <>
            <Head title={`Edit — ${page.title}`} />
            <RouteModal
                title={page.title}
                returnTo={route('admin.landing-pages.index')}
                className="sm:max-w-3xl"
            >
                <a
                    href={`/lp/${page.slug}`}
                    target="_blank"
                    rel="noreferrer"
                    className="flex w-fit items-center gap-1 text-xs text-[#c8102e] hover:underline"
                >
                    <ExternalLink className="size-3.5" /> Preview
                </a>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        clearErrors();
                        const clientErrors = validate(data, {
                            title: [required(), maxLength(255)],
                            slug: [
                                required(),
                                maxLength(255),
                                pattern(
                                    /^[a-z0-9-]+$/,
                                    'Only lowercase letters, numbers, and hyphens are allowed.',
                                ),
                            ],
                            product_id: [required()],
                        });

                        if (Object.keys(clientErrors).length > 0) {
                            setError(clientErrors);

                            return;
                        }

                        put(route('admin.landing-pages.update', page.id));
                    }}
                    className="space-y-4"
                >
                    {/* Basic settings */}
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-3">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Settings
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 pt-4">
                            <div className="col-span-2">
                                <label className={labelClass}>Page Title</label>
                                <input
                                    className={inputClass}
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                />
                                {errors.title && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.title}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>URL Slug</label>
                                <div className="flex items-center overflow-hidden rounded-lg border border-slate-200 focus-within:ring-1 focus-within:ring-[#c8102e]">
                                    <span className="flex h-9 shrink-0 items-center border-r border-slate-200 bg-slate-50 px-2 text-xs text-slate-400">
                                        /sentinel/
                                    </span>
                                    <input
                                        className="h-9 flex-1 px-2 font-mono text-sm focus:outline-none"
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData(
                                                'slug',
                                                e.target.value
                                                    .toLowerCase()
                                                    .replace(
                                                        /[^a-z0-9\-]/g,
                                                        '',
                                                    ),
                                            )
                                        }
                                    />
                                </div>
                                {errors.slug && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.slug}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>Product</label>
                                <Select
                                    selectClassName={inputClass}
                                    value={data.product_id}
                                    onChange={(e) =>
                                        setData('product_id', e.target.value)
                                    }
                                >
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="col-span-2 flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active_edit"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="rounded border-slate-300"
                                />
                                <label
                                    htmlFor="is_active_edit"
                                    className="text-sm font-medium text-slate-700"
                                >
                                    Active (publicly accessible)
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Hero section */}
                    <SectionWrap
                        title="Hero Section"
                        enabled={sec.hero.enabled}
                        onToggle={() =>
                            setSec({
                                hero: {
                                    ...sec.hero,
                                    enabled: !sec.hero.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>
                                Background Color
                            </label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    value={sec.hero.bg_color}
                                    onChange={(e) =>
                                        setSec({
                                            hero: {
                                                ...sec.hero,
                                                bg_color: e.target.value,
                                            },
                                        })
                                    }
                                    className="h-9 w-12 cursor-pointer rounded-lg border border-slate-200 p-1"
                                />
                                <input
                                    className={inputClass}
                                    value={sec.hero.bg_color}
                                    onChange={(e) =>
                                        setSec({
                                            hero: {
                                                ...sec.hero,
                                                bg_color: e.target.value,
                                            },
                                        })
                                    }
                                    placeholder="#0f172a"
                                />
                            </div>
                        </div>
                        <div>
                            <label className={labelClass}>Headline</label>
                            <input
                                className={inputClass}
                                value={sec.hero.headline}
                                onChange={(e) =>
                                    setSec({
                                        hero: {
                                            ...sec.hero,
                                            headline: e.target.value,
                                        },
                                    })
                                }
                                placeholder="Get the best deal on this product!"
                            />
                        </div>
                        <div>
                            <label className={labelClass}>Sub-headline</label>
                            <input
                                className={inputClass}
                                value={sec.hero.subheadline}
                                onChange={(e) =>
                                    setSec({
                                        hero: {
                                            ...sec.hero,
                                            subheadline: e.target.value,
                                        },
                                    })
                                }
                                placeholder="Limited time offer. Order now!"
                            />
                        </div>
                        <div>
                            <label className={labelClass}>
                                CTA Button Text
                            </label>
                            <input
                                className={inputClass}
                                value={sec.hero.cta_text}
                                onChange={(e) =>
                                    setSec({
                                        hero: {
                                            ...sec.hero,
                                            cta_text: e.target.value,
                                        },
                                    })
                                }
                                placeholder="Order Now"
                            />
                        </div>
                    </SectionWrap>

                    {/* Benefits */}
                    <SectionWrap
                        title="Benefits / Features"
                        enabled={sec.benefits.enabled}
                        onToggle={() =>
                            setSec({
                                benefits: {
                                    ...sec.benefits,
                                    enabled: !sec.benefits.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>Section Title</label>
                            <input
                                className={inputClass}
                                value={sec.benefits.title}
                                onChange={(e) =>
                                    setSec({
                                        benefits: {
                                            ...sec.benefits,
                                            title: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                        <div>
                            <label className={labelClass}>Bullet Points</label>
                            <div className="space-y-2">
                                {sec.benefits.items.map((item, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            className={inputClass}
                                            value={item}
                                            onChange={(e) => {
                                                const items = [
                                                    ...sec.benefits.items,
                                                ];
                                                items[i] = e.target.value;
                                                setSec({
                                                    benefits: {
                                                        ...sec.benefits,
                                                        items,
                                                    },
                                                });
                                            }}
                                            placeholder={`Benefit ${i + 1}`}
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setSec({
                                                    benefits: {
                                                        ...sec.benefits,
                                                        items: sec.benefits.items.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    },
                                                })
                                            }
                                            className="shrink-0 text-slate-400 hover:text-red-500"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setSec({
                                            benefits: {
                                                ...sec.benefits,
                                                items: [
                                                    ...sec.benefits.items,
                                                    '',
                                                ],
                                            },
                                        })
                                    }
                                    className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
                                >
                                    <Plus className="size-3.5" /> Add bullet
                                </button>
                            </div>
                        </div>
                    </SectionWrap>

                    {/* Description */}
                    <SectionWrap
                        title="Product Description"
                        enabled={sec.description.enabled}
                        onToggle={() =>
                            setSec({
                                description: {
                                    ...sec.description,
                                    enabled: !sec.description.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>Section Title</label>
                            <textarea
                                rows={6}
                                className={textareaClass}
                                value={sec.description.title}
                                onChange={(e) =>
                                    setSec({
                                        description: {
                                            ...sec.description,
                                            title: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="use_prod_desc"
                                checked={
                                    sec.description.use_product_description
                                }
                                onChange={(e) =>
                                    setSec({
                                        description: {
                                            ...sec.description,
                                            use_product_description:
                                                e.target.checked,
                                        },
                                    })
                                }
                                className="rounded border-slate-300"
                            />
                            <label
                                htmlFor="use_prod_desc"
                                className="text-xs font-medium text-slate-600"
                            >
                                Use product's own description
                            </label>
                        </div>
                        {!sec.description.use_product_description && (
                            <div>
                                <label className={labelClass}>
                                    Custom Content (HTML allowed)
                                </label>
                                <textarea
                                    rows={6}
                                    className={textareaClass}
                                    value={sec.description.custom_content}
                                    onChange={(e) =>
                                        setSec({
                                            description: {
                                                ...sec.description,
                                                custom_content: e.target.value,
                                            },
                                        })
                                    }
                                />
                            </div>
                        )}
                    </SectionWrap>

                    {/* Gallery */}
                    <SectionWrap
                        title="Product Gallery"
                        enabled={sec.gallery.enabled}
                        onToggle={() =>
                            setSec({
                                gallery: {
                                    ...sec.gallery,
                                    enabled: !sec.gallery.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>Section Title</label>
                            <input
                                className={inputClass}
                                value={sec.gallery.title}
                                onChange={(e) =>
                                    setSec({
                                        gallery: {
                                            ...sec.gallery,
                                            title: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                        <p className="text-xs text-slate-400">
                            Images are pulled automatically from the selected
                            product's media.
                        </p>
                    </SectionWrap>

                    {/* Testimonials */}
                    <SectionWrap
                        title="Customer Testimonials"
                        enabled={sec.testimonials.enabled}
                        onToggle={() =>
                            setSec({
                                testimonials: {
                                    ...sec.testimonials,
                                    enabled: !sec.testimonials.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>Section Title</label>
                            <input
                                className={inputClass}
                                value={sec.testimonials.title}
                                onChange={(e) =>
                                    setSec({
                                        testimonials: {
                                            ...sec.testimonials,
                                            title: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                        {sec.testimonials.items.map((item, i) => (
                            <div
                                key={i}
                                className="space-y-2 rounded-lg border border-slate-100 p-3"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-600">
                                        Review #{i + 1}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSec({
                                                testimonials: {
                                                    ...sec.testimonials,
                                                    items: sec.testimonials.items.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                },
                                            })
                                        }
                                        className="text-slate-400 hover:text-red-500"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                                <input
                                    className={inputClass}
                                    value={item.name}
                                    onChange={(e) => {
                                        const items = [
                                            ...sec.testimonials.items,
                                        ];
                                        items[i] = {
                                            ...items[i],
                                            name: e.target.value,
                                        };
                                        setSec({
                                            testimonials: {
                                                ...sec.testimonials,
                                                items,
                                            },
                                        });
                                    }}
                                    placeholder="Customer name"
                                />
                                <Select
                                    selectClassName={inputClass}
                                    value={item.rating}
                                    onChange={(e) => {
                                        const items = [
                                            ...sec.testimonials.items,
                                        ];
                                        items[i] = {
                                            ...items[i],
                                            rating: Number(e.target.value),
                                        };
                                        setSec({
                                            testimonials: {
                                                ...sec.testimonials,
                                                items,
                                            },
                                        });
                                    }}
                                >
                                    {[5, 4, 3, 2, 1].map((r) => (
                                        <option key={r} value={r}>
                                            {r} Stars
                                        </option>
                                    ))}
                                </Select>
                                <textarea
                                    rows={2}
                                    className={textareaClass}
                                    value={item.text}
                                    onChange={(e) => {
                                        const items = [
                                            ...sec.testimonials.items,
                                        ];
                                        items[i] = {
                                            ...items[i],
                                            text: e.target.value,
                                        };
                                        setSec({
                                            testimonials: {
                                                ...sec.testimonials,
                                                items,
                                            },
                                        });
                                    }}
                                    placeholder="Review text…"
                                />
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() =>
                                setSec({
                                    testimonials: {
                                        ...sec.testimonials,
                                        items: [
                                            ...sec.testimonials.items,
                                            { name: '', rating: 5, text: '' },
                                        ],
                                    },
                                })
                            }
                            className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
                        >
                            <Plus className="size-3.5" /> Add review
                        </button>
                    </SectionWrap>

                    {/* FAQ */}
                    <SectionWrap
                        title="FAQ"
                        enabled={sec.faq.enabled}
                        onToggle={() =>
                            setSec({
                                faq: { ...sec.faq, enabled: !sec.faq.enabled },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>Section Title</label>
                            <input
                                className={inputClass}
                                value={sec.faq.title}
                                onChange={(e) =>
                                    setSec({
                                        faq: {
                                            ...sec.faq,
                                            title: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                        {sec.faq.items.map((item, i) => (
                            <div
                                key={i}
                                className="space-y-2 rounded-lg border border-slate-100 p-3"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-600">
                                        Q&A #{i + 1}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSec({
                                                faq: {
                                                    ...sec.faq,
                                                    items: sec.faq.items.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                },
                                            })
                                        }
                                        className="text-slate-400 hover:text-red-500"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                                <input
                                    className={inputClass}
                                    value={item.question}
                                    onChange={(e) => {
                                        const items = [...sec.faq.items];
                                        items[i] = {
                                            ...items[i],
                                            question: e.target.value,
                                        };
                                        setSec({ faq: { ...sec.faq, items } });
                                    }}
                                    placeholder="Question"
                                />
                                <textarea
                                    rows={2}
                                    className={textareaClass}
                                    value={item.answer}
                                    onChange={(e) => {
                                        const items = [...sec.faq.items];
                                        items[i] = {
                                            ...items[i],
                                            answer: e.target.value,
                                        };
                                        setSec({ faq: { ...sec.faq, items } });
                                    }}
                                    placeholder="Answer"
                                />
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() =>
                                setSec({
                                    faq: {
                                        ...sec.faq,
                                        items: [
                                            ...sec.faq.items,
                                            { question: '', answer: '' },
                                        ],
                                    },
                                })
                            }
                            className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
                        >
                            <Plus className="size-3.5" /> Add Q&A
                        </button>
                    </SectionWrap>

                    {/* Footer */}
                    <SectionWrap
                        title="Footer"
                        enabled={sec.footer.enabled}
                        onToggle={() =>
                            setSec({
                                footer: {
                                    ...sec.footer,
                                    enabled: !sec.footer.enabled,
                                },
                            })
                        }
                    >
                        <div>
                            <label className={labelClass}>
                                Footer Text (contact info, copyright, etc.)
                            </label>
                            <textarea
                                rows={3}
                                className={textareaClass}
                                value={sec.footer.text}
                                onChange={(e) =>
                                    setSec({
                                        footer: {
                                            ...sec.footer,
                                            text: e.target.value,
                                        },
                                    })
                                }
                                placeholder="© 2025 ShoppyDrive · support@shoppydrive.com"
                            />
                        </div>
                    </SectionWrap>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save All Changes'}
                    </Button>
                </form>
            </RouteModal>
        </>
    );
}
