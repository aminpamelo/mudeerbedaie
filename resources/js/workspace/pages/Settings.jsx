import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { Trophy, Tag } from 'lucide-react';

export default function Settings({ categories = [], badges = [] }) {
    return (
        <WorkspaceLayout title="Settings" subtitle="Workspace configuration">
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Tag className="h-5 w-5 text-slate-400" />
                        <h3 className="text-[15px] font-bold text-slate-900">Task Categories</h3>
                    </div>
                    {categories.length > 0 ? (
                        <div className="space-y-2">
                            {categories.map(c => (
                                <div
                                    key={c.id}
                                    className="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2"
                                >
                                    <div
                                        className="h-3 w-3 rounded-full"
                                        style={{ backgroundColor: c.color ?? '#6366f1' }}
                                    />
                                    <span className="text-[13px] font-medium text-slate-700">
                                        {c.name}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-[13px] text-slate-400">No categories configured.</p>
                    )}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Trophy className="h-5 w-5 text-amber-500" />
                        <h3 className="text-[15px] font-bold text-slate-900">Badges</h3>
                    </div>
                    <div className="space-y-2">
                        {badges.map(b => (
                            <div
                                key={b.id}
                                className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"
                            >
                                <div className="flex items-center gap-2">
                                    <div
                                        className="grid h-7 w-7 place-items-center rounded-lg text-[10px] font-bold text-white"
                                        style={{ backgroundColor: b.color }}
                                    >
                                        {b.points}
                                    </div>
                                    <div>
                                        <span className="text-[13px] font-medium text-slate-700">
                                            {b.name}
                                        </span>
                                        <p className="text-[11px] text-slate-400">
                                            {b.description}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </WorkspaceLayout>
    );
}
