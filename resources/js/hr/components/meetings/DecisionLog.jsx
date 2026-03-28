import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Check, X } from 'lucide-react';
import { addDecision, updateDecision, deleteDecision } from '../../lib/api';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';

export default function DecisionLog({ meetingId, decisions, onUpdate }) {
    const queryClient = useQueryClient();
    const [adding, setAdding] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({ title: '', description: '', decided_by: '' });

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', meetingId] });
        onUpdate?.();
    }

    const addMut = useMutation({
        mutationFn: (data) => addDecision(meetingId, data),
        onSuccess: () => {
            invalidate();
            setAdding(false);
            setForm({ title: '', description: '', decided_by: '' });
        },
    });

    const updateMut = useMutation({
        mutationFn: ({ decId, data }) => updateDecision(meetingId, decId, data),
        onSuccess: () => {
            invalidate();
            setEditingId(null);
        },
    });

    const deleteMut = useMutation({
        mutationFn: (decId) => deleteDecision(meetingId, decId),
        onSuccess: invalidate,
    });

    function startEdit(dec) {
        setEditingId(dec.id);
        setForm({
            title: dec.title || '',
            description: dec.description || '',
            decided_by: dec.decided_by || '',
        });
    }

    function formatDate(d) {
        if (!d) return '';
        return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    Decisions
                    <Badge variant="secondary">{decisions.length}</Badge>
                </CardTitle>
                <Button variant="outline" size="sm" onClick={() => { setAdding(true); setForm({ title: '', description: '', decided_by: '' }); }}>
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add Decision
                </Button>
            </CardHeader>
            <CardContent>
                {decisions.length === 0 && !adding ? (
                    <p className="text-sm text-zinc-500">No decisions recorded yet.</p>
                ) : (
                    <div className="space-y-3">
                        {decisions.map((dec) => (
                            <div key={dec.id} className="rounded-lg border border-zinc-200 p-3">
                                {editingId === dec.id ? (
                                    <div className="space-y-2">
                                        <Input
                                            value={form.title}
                                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                                            placeholder="Decision title"
                                        />
                                        <Textarea
                                            value={form.description}
                                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                            placeholder="Details"
                                            rows={2}
                                        />
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() => updateMut.mutate({ decId: dec.id, data: form })}
                                                disabled={updateMut.isPending || !form.title.trim()}
                                            >
                                                <Check className="mr-1 h-3.5 w-3.5" />
                                                Save
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => setEditingId(null)}>
                                                <X className="mr-1 h-3.5 w-3.5" />
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-zinc-900">{dec.title}</p>
                                            {dec.description && (
                                                <p className="mt-0.5 text-sm text-zinc-500">{dec.description}</p>
                                            )}
                                            <div className="mt-1 flex items-center gap-2 text-xs text-zinc-400">
                                                {dec.decided_by_employee?.full_name && (
                                                    <span>Decided by {dec.decided_by_employee.full_name}</span>
                                                )}
                                                {dec.created_at && <span>{formatDate(dec.created_at)}</span>}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => startEdit(dec)}>
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => deleteMut.mutate(dec.id)}>
                                                <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {adding && (
                    <div className="mt-4 space-y-2 rounded-lg border border-zinc-200 p-3">
                        <Input
                            value={form.title}
                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                            placeholder="Decision title"
                            autoFocus
                        />
                        <Textarea
                            value={form.description}
                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                            placeholder="Details"
                            rows={2}
                        />
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() => addMut.mutate(form)}
                                disabled={addMut.isPending || !form.title.trim()}
                            >
                                <Check className="mr-1 h-3.5 w-3.5" />
                                Add
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setAdding(false)}>
                                <X className="mr-1 h-3.5 w-3.5" />
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
