import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-slate-800 text-white',
                secondary: 'border-transparent bg-slate-100 text-slate-800',
                destructive: 'border-transparent bg-rose-600 text-white',
                outline: 'border-slate-300 text-slate-600',
                success: 'border-transparent bg-teal-100 text-teal-800',
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
