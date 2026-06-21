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
        "h-9 w-full min-w-0 rounded-md border border-[#EAEAEA] bg-transparent px-3 py-1 text-base transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
        // Match the rest of the Live Host UI's field focus: keep the gray #EAEAEA
        // border in place and add a soft 2px emerald (#10B981) ring — rather than a
        // solid emerald border + 3px ring + shadow, which visibly clashed beside the
        // hand-rolled border-[#EAEAEA] selects/textareas used across the app.
        "focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20",
        "aria-invalid:border-rose aria-invalid:ring-rose/20",
        className
      )}
      {...props} />
  );
}

export { Input }
