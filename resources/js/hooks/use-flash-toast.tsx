import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { useUiStore } from '@/stores/uiStore';
import type { FlashToast } from '@/types/ui';

/** Splits on the first "N product(s)" substring so it can be rendered as a separate clickable element. */
function splitAroundProductCount(
    message: string,
): [string, string, string] | null {
    const match = message.match(/\d+ product\(s\)/);

    if (!match || match.index === undefined) {
        return null;
    }

    const before = message.slice(0, match.index);
    const after = message.slice(match.index + match[0].length);

    return [before, match[0], after];
}

function ToastMessage({ data }: { data: FlashToast }): React.JSX.Element {
    const showBlockedDeleteProducts = useUiStore(
        (state) => state.showBlockedDeleteProducts,
    );
    const split =
        data.products && data.products.length > 0
            ? splitAroundProductCount(data.message)
            : null;

    if (!split) {
        return <>{data.message}</>;
    }

    const [before, countText, after] = split;

    return (
        <span>
            {before}
            <button
                type="button"
                onClick={() => showBlockedDeleteProducts(data.products!)}
                className="font-semibold underline underline-offset-2"
            >
                {countText}
            </button>
            {after}
        </span>
    );
}

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](<ToastMessage data={data} />);
        });
    }, []);
}
