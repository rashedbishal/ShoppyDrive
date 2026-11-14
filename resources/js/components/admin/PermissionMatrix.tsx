type SectionDefinition = {
    key: string;
    label: string;
    abilities: string[];
};

type SectionGroup = {
    group: string;
    sections: SectionDefinition[];
};

type Props = {
    catalog: SectionGroup[];
    value: Record<string, string[]>;
    onChange: (value: Record<string, string[]>) => void;
};

const ABILITY_LABELS: Record<string, string> = {
    create: 'Create',
    update: 'Update',
    delete: 'Delete',
};

const checkboxClass =
    'size-4 rounded border-slate-300 accent-brand disabled:cursor-not-allowed disabled:opacity-40';

/**
 * The permission matrix on Admin > Admins > create/edit. Checking a
 * section's "Visible" box grants `view` (and shows the sidebar item);
 * create/update/delete only appear where that section actually has a
 * route for them (see App\Support\AdminSections) and stay disabled until
 * the section is visible.
 */
export function PermissionMatrix({ catalog, value, onChange }: Props) {
    const isVisible = (key: string) => (value[key] ?? []).includes('view');
    const hasAbility = (key: string, ability: string) =>
        (value[key] ?? []).includes(ability);

    const toggleVisible = (key: string, checked: boolean) => {
        const next = { ...value };

        if (checked) {
            next[key] = ['view'];
        } else {
            delete next[key];
        }

        onChange(next);
    };

    const toggleAbility = (key: string, ability: string, checked: boolean) => {
        const current = value[key] ?? ['view'];
        const abilities = checked
            ? [...current, ability]
            : current.filter((a) => a !== ability);

        onChange({ ...value, [key]: Array.from(new Set(abilities)) });
    };

    return (
        <div className="space-y-5">
            {catalog.map((group) => (
                <div key={group.group}>
                    <p className="mb-2 text-xs font-semibold tracking-widest text-slate-600 uppercase">
                        {group.group}
                    </p>
                    <div className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                        {group.sections.map((section) => {
                            const visible = isVisible(section.key);

                            return (
                                <div
                                    key={section.key}
                                    className="flex flex-wrap items-center gap-x-5 gap-y-2 px-3 py-2.5"
                                >
                                    <label className="flex w-40 shrink-0 cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                        <input
                                            type="checkbox"
                                            className={checkboxClass}
                                            checked={visible}
                                            onChange={(e) =>
                                                toggleVisible(
                                                    section.key,
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        {section.label}
                                    </label>

                                    <div className="flex flex-wrap items-center gap-4">
                                        {section.abilities.map((ability) => (
                                            <label
                                                key={ability}
                                                className={`flex cursor-pointer items-center gap-1.5 text-xs ${
                                                    visible
                                                        ? 'text-slate-600'
                                                        : 'text-slate-300'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className={checkboxClass}
                                                    disabled={!visible}
                                                    checked={hasAbility(
                                                        section.key,
                                                        ability,
                                                    )}
                                                    onChange={(e) =>
                                                        toggleAbility(
                                                            section.key,
                                                            ability,
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                {ABILITY_LABELS[ability] ??
                                                    ability}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
