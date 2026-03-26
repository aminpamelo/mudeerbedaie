import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-zinc-900 text-white',
                secondary: 'border-transparent bg-zinc-100 text-zinc-900',
                destructive: 'border-transparent bg-red-600 text-white',
                outline: 'border-zinc-300 text-zinc-700',
                success: 'border-transparent bg-emerald-100 text-emerald-800',
                warning: 'border-transparent bg-amber-100 text-amber-800',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

function Badge({ className, variant, ...props }) {
    return (
        <div className={cn(badgeVariants({ variant }), className)} {...props} />
    );
}

export { Badge, badgeVariants };
