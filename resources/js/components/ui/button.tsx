import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import { type ButtonHTMLAttributes, type ReactNode } from "react";
import { LoaderCircle } from "lucide-react";

import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";

const buttonVariants = cva(
    "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-60 [&_svg]:pointer-events-none [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                primary:
                    "bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover hover:shadow-md",
                default:
                    "bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover hover:shadow-md",
                destructive: "bg-destructive text-white shadow-sm hover:opacity-90",
                secondary:
                    "border-2 border-primary bg-transparent text-primary hover:bg-primary-light",
                ghost:
                    "text-text-secondary hover:bg-primary-light hover:text-primary",
                danger:
                    "bg-destructive text-white shadow-sm hover:opacity-90",
                outline:
                    "border border-border bg-surface text-text-primary hover:bg-surface-alt",
                link: "text-primary underline-offset-4 hover:underline",
            },
            size: {
                sm: "h-9 px-3 text-sm",
                md: "h-11 px-6",
                lg: "h-12 px-7 text-base",
                icon: "h-11 w-11 p-0",
            },
            fullWidth: {
                true: "w-full",
                false: "",
            },
        },
        defaultVariants: {
            variant: "primary",
            size: "md",
            fullWidth: false,
        },
    },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        leftIcon?: ReactNode;
        rightIcon?: ReactNode;
        loading?: boolean;
        loadingText?: string;
        fullWidth?: boolean;
    };

function Button({
    className,
    variant,
    size,
    fullWidth,
    asChild = false,
    leftIcon,
    rightIcon,
    loading = false,
    loadingText = "Loading...",
    disabled,
    type,
    children,
    ...props
}: ButtonProps) {
    const Comp = asChild ? Slot : "button";
    const isDisabled = disabled || loading;

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, fullWidth }), className)}
            disabled={isDisabled}
            type={type ?? "button"}
            aria-busy={loading || undefined}
            {...props}
        >
            {asChild ? children : loading ? (
                <>
                    <Spinner size="sm" className="size-4 shrink-0" />
                    <span>{loadingText}</span>
                </>
            ) : (
                <>
                    {leftIcon}
                    <span>{children}</span>
                    {rightIcon}
                </>
            )}
        </Comp>
    );
}

export { Button, buttonVariants };
