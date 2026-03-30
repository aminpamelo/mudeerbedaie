import { Check } from 'lucide-react';
import { cn } from '../lib/utils';
import { Avatar, AvatarFallback } from './ui/avatar';

const STAGES = ['idea', 'shooting', 'editing', 'posting', 'posted'];

const STAGE_LABELS = {
    idea: 'Idea',
    shooting: 'Shooting',
    editing: 'Editing',
    posting: 'Posting',
    posted: 'Posted',
};

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

export default function StageTimeline({ stages = [], currentStage = 'idea' }) {
    const currentIndex = STAGES.indexOf(currentStage);

    function getStageStatus(stage) {
        const idx = STAGES.indexOf(stage);
        if (idx < currentIndex) return 'completed';
        if (idx === currentIndex) return 'current';
        return 'pending';
    }

    function getStageData(stageName) {
        return stages.find((s) => s.stage === stageName || s.name === stageName) || null;
    }

    return (
        <div className="w-full">
            {/* Horizontal layout for md+ */}
            <div className="hidden md:flex items-start justify-between">
                {STAGES.map((stage, idx) => {
                    const status = getStageStatus(stage);
                    const stageData = getStageData(stage);
                    const isLast = idx === STAGES.length - 1;

                    return (
                        <div key={stage} className="flex items-start flex-1">
                            <div className="flex flex-col items-center">
                                {/* Circle */}
                                <div
                                    className={cn(
                                        'flex items-center justify-center rounded-full border-2 transition-all',
                                        status === 'completed' &&
                                            'h-8 w-8 border-emerald-500 bg-emerald-500 text-white',
                                        status === 'current' &&
                                            'h-10 w-10 border-indigo-500 bg-indigo-500 text-white',
                                        status === 'pending' &&
                                            'h-8 w-8 border-slate-300 bg-white text-slate-400'
                                    )}
                                >
                                    {status === 'completed' && (
                                        <Check className="h-4 w-4" />
                                    )}
                                    {status === 'current' && (
                                        <div className="h-3 w-3 rounded-full bg-white" />
                                    )}
                                    {status === 'pending' && (
                                        <div className="h-2 w-2 rounded-full bg-slate-300" />
                                    )}
                                </div>

                                {/* Label */}
                                <span
                                    className={cn(
                                        'mt-2 text-xs font-semibold',
                                        status === 'completed' && 'text-emerald-600',
                                        status === 'current' && 'text-indigo-600',
                                        status === 'pending' && 'text-slate-400'
                                    )}
                                >
                                    {STAGE_LABELS[stage]}
                                </span>

                                {/* Status text */}
                                {stageData?.status && (
                                    <span className="mt-0.5 text-[10px] text-slate-500">
                                        {stageData.status}
                                    </span>
                                )}

                                {/* Assignee avatars */}
                                {stageData?.assignees?.length > 0 && (status === 'current' || status === 'completed') && (
                                    <div className="mt-2 flex -space-x-1">
                                        {stageData.assignees.slice(0, 3).map((assignee, aIdx) => (
                                            <Avatar
                                                key={assignee.id || aIdx}
                                                className="h-6 w-6 border-2 border-white"
                                            >
                                                <AvatarFallback className="text-[10px]">
                                                    {getInitials(assignee.name || assignee.full_name)}
                                                </AvatarFallback>
                                            </Avatar>
                                        ))}
                                        {stageData.assignees.length > 3 && (
                                            <span className="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-slate-100 text-[10px] font-medium text-slate-600">
                                                +{stageData.assignees.length - 3}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Connector line */}
                            {!isLast && (
                                <div
                                    className={cn(
                                        'mt-4 h-0.5 flex-1 mx-2',
                                        idx < currentIndex
                                            ? 'bg-emerald-500'
                                            : 'bg-slate-200'
                                    )}
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Vertical layout for mobile */}
            <div className="flex flex-col gap-0 md:hidden">
                {STAGES.map((stage, idx) => {
                    const status = getStageStatus(stage);
                    const stageData = getStageData(stage);
                    const isLast = idx === STAGES.length - 1;

                    return (
                        <div key={stage} className="flex items-start">
                            <div className="flex flex-col items-center">
                                {/* Circle */}
                                <div
                                    className={cn(
                                        'flex items-center justify-center rounded-full border-2 transition-all',
                                        status === 'completed' &&
                                            'h-8 w-8 border-emerald-500 bg-emerald-500 text-white',
                                        status === 'current' &&
                                            'h-10 w-10 border-indigo-500 bg-indigo-500 text-white',
                                        status === 'pending' &&
                                            'h-8 w-8 border-slate-300 bg-white text-slate-400'
                                    )}
                                >
                                    {status === 'completed' && (
                                        <Check className="h-4 w-4" />
                                    )}
                                    {status === 'current' && (
                                        <div className="h-3 w-3 rounded-full bg-white" />
                                    )}
                                    {status === 'pending' && (
                                        <div className="h-2 w-2 rounded-full bg-slate-300" />
                                    )}
                                </div>
                                {/* Vertical connector */}
                                {!isLast && (
                                    <div
                                        className={cn(
                                            'w-0.5 h-8',
                                            idx < currentIndex
                                                ? 'bg-emerald-500'
                                                : 'bg-slate-200'
                                        )}
                                    />
                                )}
                            </div>

                            <div className="ml-3 pb-6">
                                <span
                                    className={cn(
                                        'text-sm font-semibold',
                                        status === 'completed' && 'text-emerald-600',
                                        status === 'current' && 'text-indigo-600',
                                        status === 'pending' && 'text-slate-400'
                                    )}
                                >
                                    {STAGE_LABELS[stage]}
                                </span>
                                {stageData?.status && (
                                    <p className="text-xs text-slate-500">
                                        {stageData.status}
                                    </p>
                                )}
                                {stageData?.assignees?.length > 0 && (status === 'current' || status === 'completed') && (
                                    <div className="mt-1 flex -space-x-1">
                                        {stageData.assignees.slice(0, 3).map((assignee, aIdx) => (
                                            <Avatar
                                                key={assignee.id || aIdx}
                                                className="h-6 w-6 border-2 border-white"
                                            >
                                                <AvatarFallback className="text-[10px]">
                                                    {getInitials(assignee.name || assignee.full_name)}
                                                </AvatarFallback>
                                            </Avatar>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
