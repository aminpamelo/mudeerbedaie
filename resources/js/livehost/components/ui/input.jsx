import * as React from "react"

import { cn } from "@/livehost/lib/utils"

function Input({
  className,
  type,
  ...props
}) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
        // Soft, on-brand emerald focus. The shadcn `ring`/`border-ring` tokens
        // aren't mapped into Tailwind v4's @theme, so focus collapsed to a harsh
        // near-black 1px border with no ring; the `emerald` theme color resolves
        // correctly and matches the rest of the Live Host UI.
        "focus-visible:border-emerald focus-visible:ring-[3px] focus-visible:ring-emerald/20",
        "aria-invalid:border-rose aria-invalid:ring-rose/20",
        className
      )}
      {...props} />
  );
}

export { Input }
