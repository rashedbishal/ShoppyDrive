import * as DialogPrimitive from "@radix-ui/react-dialog"
import { XIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Dialog({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Root>) {
  return <DialogPrimitive.Root data-slot="dialog" {...props} />
}

function DialogTrigger({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Trigger>) {
  return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />
}

function DialogPortal({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Portal>) {
  return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />
}

function DialogClose({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Close>) {
  return <DialogPrimitive.Close data-slot="dialog-close" {...props} />
}

type DialogAppearance = "default" | "premium"

function DialogOverlay({
  className,
  appearance = "default",
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Overlay> & {
  appearance?: DialogAppearance
}) {
  return (
    <DialogPrimitive.Overlay
      data-slot="dialog-overlay"
      className={cn(
        appearance === "premium"
          ? "dialog-premium-overlay fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-[3px]"
          : "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80",
        className
      )}
      {...props}
    />
  )
}

// Lets a floating-anchored control rendered deep inside dialog content (e.g.
// `Select`) portal its popup to a sibling of that content instead of a
// descendant of it. `DialogPrimitive.Content` is both the transform origin
// (so it's the containing block for anything `position: absolute`/`fixed`
// underneath it, no matter how deep) and, when a caller like `RouteModal`
// makes its content scrollable, the overflow-clipping box — so a popup
// nested inside that scrollable region gets clipped against the dialog's
// own scroll, not just the viewport. Portaling to this sibling keeps the
// popup in the same stacking context (so it still renders above the
// overlay) without being inside anything that scrolls or clips.
const DialogPortalTargetContext = React.createContext<React.RefObject<HTMLDivElement | null> | null>(null)

function useDialogPortalTarget(): React.RefObject<HTMLDivElement | null> | null {
  return React.useContext(DialogPortalTargetContext)
}

/**
 * `appearance="premium"` is the compact admin look (tighter padding, soft
 * shadow, spring/blur enter + cascading sections, see `.dialog-premium` in
 * app.css). It's purely visual — behavior is identical to the default.
 */
function DialogContent({
  className,
  children,
  appearance = "default",
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
  appearance?: DialogAppearance
}) {
  const portalTargetRef = React.useRef<HTMLDivElement>(null)
  const isPremium = appearance === "premium"

  return (
    <DialogPortal data-slot="dialog-portal">
      <DialogOverlay appearance={appearance} />
      <DialogPrimitive.Content
        data-slot="dialog-content"
        className={cn(
          isPremium
            ? "dialog-premium fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-3.5 rounded-2xl bg-white p-5 text-slate-900 shadow-[0_24px_64px_-16px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5 sm:max-w-lg"
            : "bg-background backdrop-blur-[var(--ui-blur)] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg",
          className
        )}
        {...props}
      >
        <DialogPortalTargetContext.Provider value={portalTargetRef}>
          {children}
        </DialogPortalTargetContext.Provider>
        {/*
         * `absolute inset-0` (rather than `display: contents`) guarantees this never
         * contributes to `DialogContent`'s own `grid` sizing, even transiently — a
         * `display: contents` wrapper hoists its children directly into the grid, so
         * whatever gets portaled into it (e.g. an open `Select` list) briefly counted as
         * an extra grid item and nudged the dialog's height. `pointer-events-none` keeps
         * it from intercepting clicks over the rest of the dialog; portaled popups
         * re-enable their own pointer events. The inner child keeps it non-empty:
         * Headless UI's `Portal` deletes whatever DOM node it portals into once that node
         * has zero children left (cleanup logic meant for the singleton root it creates
         * for itself) — without this, the very first popup to close here would delete
         * this div out from under its own ref, so every popup after the first would
         * portal into an already-detached, invisible node.
         */}
        <div ref={portalTargetRef} className="pointer-events-none absolute inset-0">
          <span className="hidden" aria-hidden="true" />
        </div>
        <DialogPrimitive.Close
          className={
            isPremium
              // `z-10` plus an always-on (not hover-only) solid background:
              // on a caller whose own scrollable content fills the same
              // top-right corner (e.g. RouteModal, create-order-modal),
              // the native scrollbar track would otherwise render visibly
              // behind/around this button. It still paints on top either
              // way (later in DOM order, so already clickable), but a
              // transparent resting state let the scrollbar show through
              // it, which looked like the two were overlapping.
              ? "absolute top-3.5 right-3.5 z-10 flex size-8 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-900/5 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
              : "ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
          }
        >
          <XIcon />
          <span className="sr-only">Close</span>
        </DialogPrimitive.Close>
      </DialogPrimitive.Content>
    </DialogPortal>
  )
}

function DialogHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-header"
      className={cn("flex flex-col gap-2 text-center sm:text-left", className)}
      {...props}
    />
  )
}

function DialogFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-footer"
      className={cn(
        "flex flex-col-reverse gap-2 sm:flex-row sm:justify-end",
        className
      )}
      {...props}
    />
  )
}

function DialogTitle({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
  return (
    <DialogPrimitive.Title
      data-slot="dialog-title"
      className={cn("text-lg leading-none font-semibold", className)}
      {...props}
    />
  )
}

function DialogDescription({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
  return (
    <DialogPrimitive.Description
      data-slot="dialog-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

export {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  DialogTrigger,
  useDialogPortalTarget,
}
