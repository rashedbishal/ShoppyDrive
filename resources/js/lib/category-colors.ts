/** All 148 CSS Level-4 named colors — valid anywhere a CSS <color> is accepted (incl. color-mix()). */
export const CSS_NAMED_COLORS = [
    'aliceblue',
    'antiquewhite',
    'aqua',
    'aquamarine',
    'azure',
    'beige',
    'bisque',
    'black',
    'blanchedalmond',
    'blue',
    'blueviolet',
    'brown',
    'burlywood',
    'cadetblue',
    'chartreuse',
    'chocolate',
    'coral',
    'cornflowerblue',
    'cornsilk',
    'crimson',
    'cyan',
    'darkblue',
    'darkcyan',
    'darkgoldenrod',
    'darkgray',
    'darkgreen',
    'darkgrey',
    'darkkhaki',
    'darkmagenta',
    'darkolivegreen',
    'darkorange',
    'darkorchid',
    'darkred',
    'darksalmon',
    'darkseagreen',
    'darkslateblue',
    'darkslategray',
    'darkslategrey',
    'darkturquoise',
    'darkviolet',
    'deeppink',
    'deepskyblue',
    'dimgray',
    'dimgrey',
    'dodgerblue',
    'firebrick',
    'floralwhite',
    'forestgreen',
    'fuchsia',
    'gainsboro',
    'ghostwhite',
    'gold',
    'goldenrod',
    'gray',
    'green',
    'greenyellow',
    'grey',
    'honeydew',
    'hotpink',
    'indianred',
    'indigo',
    'ivory',
    'khaki',
    'lavender',
    'lavenderblush',
    'lawngreen',
    'lemonchiffon',
    'lightblue',
    'lightcoral',
    'lightcyan',
    'lightgoldenrodyellow',
    'lightgray',
    'lightgreen',
    'lightgrey',
    'lightpink',
    'lightsalmon',
    'lightseagreen',
    'lightskyblue',
    'lightslategray',
    'lightslategrey',
    'lightsteelblue',
    'lightyellow',
    'lime',
    'limegreen',
    'linen',
    'magenta',
    'maroon',
    'mediumaquamarine',
    'mediumblue',
    'mediumorchid',
    'mediumpurple',
    'mediumseagreen',
    'mediumslateblue',
    'mediumspringgreen',
    'mediumturquoise',
    'mediumvioletred',
    'midnightblue',
    'mintcream',
    'mistyrose',
    'moccasin',
    'navajowhite',
    'navy',
    'oldlace',
    'olive',
    'olivedrab',
    'orange',
    'orangered',
    'orchid',
    'palegoldenrod',
    'palegreen',
    'paleturquoise',
    'palevioletred',
    'papayawhip',
    'peachpuff',
    'peru',
    'pink',
    'plum',
    'powderblue',
    'purple',
    'rebeccapurple',
    'red',
    'rosybrown',
    'royalblue',
    'saddlebrown',
    'salmon',
    'sandybrown',
    'seagreen',
    'seashell',
    'sienna',
    'silver',
    'skyblue',
    'slateblue',
    'slategray',
    'slategrey',
    'snow',
    'springgreen',
    'steelblue',
    'tan',
    'teal',
    'thistle',
    'tomato',
    'turquoise',
    'violet',
    'wheat',
    'white',
    'whitesmoke',
    'yellow',
    'yellowgreen',
] as const;

export type CssNamedColor = (typeof CSS_NAMED_COLORS)[number];

const NAMED_COLOR_SET = new Set<string>(CSS_NAMED_COLORS);

function titleCase(name: string): string {
    return name.charAt(0).toUpperCase() + name.slice(1);
}

export const CATEGORY_COLOR_OPTIONS = CSS_NAMED_COLORS.map((name) => ({
    value: name,
    label: titleCase(name),
}));

/** Used when a category has no color set, so categories still look varied by default. */
const FALLBACK_ROTATION: string[] = [
    '#0284c7', // sky
    '#e11d48', // rose
    '#059669', // emerald
    '#d97706', // amber
    '#7c3aed', // violet
    '#0891b2', // cyan
    '#c026d3', // fuchsia
    '#4f46e5', // indigo
];

export function isHexColor(value?: string | null): boolean {
    return !!value && /^#[0-9a-f]{6}$/i.test(value);
}

export function isNamedColor(value?: string | null): value is CssNamedColor {
    return !!value && NAMED_COLOR_SET.has(value.toLowerCase());
}

/** Resolves a category's stored `color` (a hex code, a CSS named color, or null) to a valid CSS <color> value. */
export function resolveCategoryColor(
    color: string | null | undefined,
    fallbackIndex: number,
): string {
    if (color) {
        if (isHexColor(color)) {
            return color;
        }

        if (isNamedColor(color)) {
            return color.toLowerCase();
        }
    }

    return FALLBACK_ROTATION[fallbackIndex % FALLBACK_ROTATION.length];
}
