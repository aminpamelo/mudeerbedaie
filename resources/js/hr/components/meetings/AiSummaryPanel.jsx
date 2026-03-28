import { useState } from 'react';
import { Brain, CheckCircle2, XCircle, Sparkles } from 'lucide-react';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';

export default function AiSummaryPanel({ summary, onApproveTasks, approving }) {
    const [selectedTasks, setSelectedTasks] = useState([]);

    if (!summary) return null;

    const summaryText = summary.summary || summary.content || '';
    const keyPoints = summary.key_points || [];
    const suggestedTasks = summary.suggested_tasks || [];

    function toggleTask(index) {
        setSelectedTasks((prev) =>
            prev.includes(index) ? prev.filter((i) => i !== index) : [...prev, index]
        );
    }

    function handleApprove() {
        const tasks = selectedTasks.map((i) => suggestedTasks[i]);
        onApproveTasks?.({ tasks });
    }

    return (
        <div className="space-y-4 rounded-lg border border-zinc-200 p-4">
            <div className="flex items-center gap-2">
                <Sparkles className="h-5 w-5 text-amber-500" />
                <h3 className="text-sm font-semibold text-zinc-900">AI Summary</h3>
            </div>

            {summaryText && (
                <div>
                    <p className="text-xs font-medium uppercase text-zinc-500">Summary</p>
                    <p className="mt-1 text-sm text-zinc-600">{summaryText}</p>
                </div>
            )}

            {keyPoints.length > 0 && (
                <div>
                    <p className="text-xs font-medium uppercase text-zinc-500">Key Points</p>
                    <ul className="mt-1 space-y-1">
                        {keyPoints.map((point, i) => (
                            <li key={i} className="flex items-start gap-2 text-sm text-zinc-600">
                                <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-zinc-400" />
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {suggestedTasks.length > 0 && (
                <div>
                    <p className="text-xs font-medium uppercase text-zinc-500">Suggested Tasks</p>
                    <div className="mt-2 space-y-2">
                        {suggestedTasks.map((task, i) => (
                            <label
                                key={i}
                                className="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-100 px-3 py-2 hover:bg-zinc-50"
                            >
                                <input
                                    type="checkbox"
                                    checked={selectedTasks.includes(i)}
                                    onChange={() => toggleTask(i)}
                                    className="mt-0.5 h-4 w-4 rounded border-zinc-300"
                                />
                                <div>
                                    <p className="text-sm font-medium text-zinc-900">{task.title}</p>
                                    {task.description && (
                                        <p className="text-xs text-zinc-500">{task.description}</p>
                                    )}
                                    {task.assignee && (
                                        <span className="text-xs text-zinc-400">Assign to: {task.assignee}</span>
                                    )}
                                </div>
                            </label>
                        ))}
                    </div>
                    <div className="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            onClick={handleApprove}
                            disabled={selectedTasks.length === 0 || approving}
                        >
                            <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                            {approving ? 'Approving...' : `Approve ${selectedTasks.length} task${selectedTasks.length !== 1 ? 's' : ''}`}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setSelectedTasks([])}
                        >
                            Clear Selection
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
