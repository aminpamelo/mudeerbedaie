import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Loader2, ChevronDown, ChevronRight, Lightbulb, Camera, Film, Send } from 'lucide-react';
import { createContent } from '../lib/api';
import { toastSuccess, toastError } from '../lib/toast';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Textarea } from '../components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../components/ui/select';
import AssigneePicker from '../components/AssigneePicker';

const STAGES = [
    {
        key: 'idea',
        label: 'Idea Stage',
        icon: Lightbulb,
        borderColor: 'border-l-blue-500',
        iconColor: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        key: 'shooting',
        label: 'Shooting Stage',
        icon: Camera,
        borderColor: 'border-l-purple-500',
        iconColor: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
    {
        key: 'editing',
        label: 'Editing Stage',
        icon: Film,
        borderColor: 'border-l-amber-500',
        iconColor: 'text-amber-500',
        bgColor: 'bg-amber-50',
    },
    {
        key: 'posting',
        label: 'Posting Stage',
        icon: Send,
        borderColor: 'border-l-emerald-500',
        iconColor: 'text-emerald-500',
        bgColor: 'bg-emerald-50',
    },
];

export default function ContentCreate() {
    const navigate = useNavigate();
    const [expandedStages, setExpandedStages] = useState({
        idea: true,
        shooting: false,
        editing: false,
        posting: false,
    });
    const [errors, setErrors] = useState({});

    const [form, setForm] = useState({
        title: '',
        description: '',
        priority: 'medium',
        due_date: '',
        stages: [
            { stage: 'idea', due_date: '', assignees: [] },
            { stage: 'shooting', due_date: '', assignees: [] },
            { stage: 'editing', due_date: '', assignees: [] },
            { stage: 'posting', due_date: '', assignees: [] },
        ],
    });

    const mutation = useMutation({
        mutationFn: (data) => createContent(data),
        onSuccess: (response) => {
            toastSuccess('Content created');
            const contentId = response?.data?.id || response?.id;
            if (contentId) {
                navigate(`/contents/${contentId}`);
            } else {
                navigate('/contents');
            }
        },
        onError: (error) => {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {});
            }
            toastError(error, 'Failed to create content');
        },
    });

    function toggleStage(stageKey) {
        setExpandedStages((prev) => ({
            ...prev,
            [stageKey]: !prev[stageKey],
        }));
    }

    function updateField(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    }

    function updateStageDueDate(stageKey, value) {
        setForm((prev) => ({
            ...prev,
            stages: prev.stages.map((s) =>
                s.stage === stageKey ? { ...s, due_date: value } : s
            ),
        }));
    }

    function updateStageAssignees(stageKey, assignees) {
        setForm((prev) => ({
            ...prev,
            stages: prev.stages.map((s) =>
                s.stage === stageKey ? { ...s, assignees } : s
            ),
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        setErrors({});
        mutation.mutate(form);
    }

    function getFieldError(field) {
        const fieldErrors = errors[field];
        if (!fieldErrors) return null;
        return Array.isArray(fieldErrors) ? fieldErrors[0] : fieldErrors;
    }

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Link to="/contents">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">Create Content</h1>
                    <p className="text-sm text-zinc-500">
                        Plan new content and assign team members to each stage.
                    </p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Section 1: Basic Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Title */}
                        <div className="space-y-2">
                            <Label htmlFor="title">
                                Title <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="title"
                                value={form.title}
                                onChange={(e) => updateField('title', e.target.value)}
                                placeholder="Enter content title"
                            />
                            {getFieldError('title') && (
                                <p className="text-sm text-red-500">{getFieldError('title')}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={form.description}
                                onChange={(e) => updateField('description', e.target.value)}
                                placeholder="Describe the content idea..."
                                rows={3}
                            />
                            {getFieldError('description') && (
                                <p className="text-sm text-red-500">{getFieldError('description')}</p>
                            )}
                        </div>

                        {/* Priority & Due Date */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Priority</Label>
                                <Select
                                    value={form.priority}
                                    onValueChange={(value) => updateField('priority', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select priority" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                                {getFieldError('priority') && (
                                    <p className="text-sm text-red-500">{getFieldError('priority')}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="due_date">Due Date</Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={form.due_date}
                                    onChange={(e) => updateField('due_date', e.target.value)}
                                />
                                {getFieldError('due_date') && (
                                    <p className="text-sm text-red-500">{getFieldError('due_date')}</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Section 2: Stage Assignments */}
                <Card>
                    <CardHeader>
                        <CardTitle>Stage Assignments</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {STAGES.map((stage, index) => {
                            const Icon = stage.icon;
                            const isExpanded = expandedStages[stage.key];
                            const stageData = form.stages[index];
                            const assigneeCount = stageData.assignees.length;

                            return (
                                <div
                                    key={stage.key}
                                    className={`rounded-lg border border-zinc-200 border-l-4 ${stage.borderColor}`}
                                >
                                    {/* Stage header */}
                                    <button
                                        type="button"
                                        onClick={() => toggleStage(stage.key)}
                                        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-zinc-50"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${stage.bgColor}`}>
                                                <Icon className={`h-4 w-4 ${stage.iconColor}`} />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {stage.label}
                                                </p>
                                                {assigneeCount > 0 && (
                                                    <p className="text-xs text-zinc-500">
                                                        {assigneeCount} assignee{assigneeCount !== 1 ? 's' : ''}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        {isExpanded ? (
                                            <ChevronDown className="h-4 w-4 text-zinc-400" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4 text-zinc-400" />
                                        )}
                                    </button>

                                    {/* Stage content */}
                                    {isExpanded && (
                                        <div className="space-y-4 border-t border-zinc-100 px-4 py-4">
                                            <div className="space-y-2">
                                                <Label htmlFor={`stage-due-${stage.key}`}>
                                                    Stage Due Date
                                                </Label>
                                                <Input
                                                    id={`stage-due-${stage.key}`}
                                                    type="date"
                                                    value={stageData.due_date}
                                                    onChange={(e) =>
                                                        updateStageDueDate(stage.key, e.target.value)
                                                    }
                                                />
                                                {getFieldError(`stages.${index}.due_date`) && (
                                                    <p className="text-sm text-red-500">
                                                        {getFieldError(`stages.${index}.due_date`)}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Assignees</Label>
                                                <AssigneePicker
                                                    assignees={stageData.assignees}
                                                    onAssigneesChange={(assignees) =>
                                                        updateStageAssignees(stage.key, assignees)
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {/* Global error */}
                {errors.message && (
                    <p className="text-sm text-red-500">{errors.message}</p>
                )}

                {/* Submit */}
                <div className="flex justify-end gap-3">
                    <Link to="/contents">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                    <Button type="submit" disabled={mutation.isPending}>
                        {mutation.isPending && (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        Create Content
                    </Button>
                </div>
            </form>
        </div>
    );
}
