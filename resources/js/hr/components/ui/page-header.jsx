import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { cn } from '../../lib/utils';

/**
 * Standard page header — title + description + optional eyebrow + action buttons + breadcrumb.
 * Use on list / detail / form / settings pages.
 *
 * <PageHeader
 *   title="Employees"
 *   description="Manage your workforce"
 *   breadcrumb={[{ label: 'HR', to: '/' }, { label: 'Employees' }]}
 *   actions={<>
 *     <Button variant="outline">Export</Button>
 *     <Button>New Employee</Button>
 *   </>}
 * />
 */
export function PageHeader({ eyebrow, title, description, breadcrumb, actions, className }) {
    return (
        <div className={cn('mb-6 space-y-3', className)}>
            {breadcrumb && breadcrumb.length > 0 && (
                <nav aria-label="Breadcrumb" className="flex items-center gap-1 text-xs text-slate-500">
                    {breadcrumb.map((crumb, i) => (
                        <span key={i} className="flex items-center gap-1">
                            {i > 0 && <ChevronRight className="h-3 w-3 text-slate-400" />}
                            {crumb.to ? (
                                <Link to={crumb.to} className="rounded hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span className="font-medium text-slate-700">{crumb.label}</span>
                            )}
                        </span>
                    ))}
                </nav>
            )}

            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    {eyebrow && (
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{eyebrow}</p>
                    )}
                    <h1 className={cn('font-bold tracking-tight text-slate-900', eyebrow ? 'mt-1.5 text-2xl sm:text-3xl' : 'text-2xl sm:text-3xl')}>
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1.5 text-sm text-slate-500">{description}</p>
                    )}
                </div>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}
