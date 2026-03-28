import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Check, X } from 'lucide-react';
import { addAgendaItem, updateAgendaItem, deleteAgendaItem } from '../../lib/api';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';

export default function AgendaEditor({ meetingId, items, onUpdate }) {
    const queryClient = useQueryClient();
    const [adding, setAdding] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({ title: '', description: '' });

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', meetingId] });
        onUpdate?.();
    }

    const addMut = useMutation({
        mutationFn: (data) => addAgendaItem(meetingId, data),
        onSuccess: () => {
            invalidate();
            setAdding(false);
            setForm({ title: '', description: '' });
        },
    });

    const updateMut = useMutation({
        mutationFn: ({ itemId, data }) => updateAgendaItem(meetingId, itemId, data),
        onSuccess: () => {
            invalidate();
            setEditingId(null);
            setForm({ title: '', description: '' });
        },
    });

    const deleteMut = useMutation({
        mutationFn: (itemId) => deleteAgendaItem(meetingId, itemId),
        onSuccess: invalidate,
    });

    function startEdit(item) {
        setEditingId(item.id);
        setForm({ title: item.title, description: item.description || '' });
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    Agenda
                    <Badge variant="secondary">{items.length}</Badge>
                </CardTitle>
                <Button variant="outline" size="sm" onClick={() => { setAdding(true); setForm({ title: '', description: '' }); }}>
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add Item
                </Button>
            </CardHeader>
            <CardContent>
                {items.length === 0 && !adding ? (
                    <p className="text-sm text-zinc-500">No agenda items yet.</p>
                ) : (
                    <div className="space-y-3">
                        {items.map((item, index) => (
                            <div key={item.id} className="flex items-start gap-3">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600">
                                    {index + 1}
                                </span>
                                {editingId === item.id ? (
                                    <div className="flex-1 space-y-2">
                                        <Input
                                            value={form.title}
                                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                                            placeholder="Agenda item title"
                                        />
                                        <Textarea
                                            value={form.description}
                                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                            placeholder="Description (optional)"
                                            rows={2}
                                        />
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() => updateMut.mutate({ itemId: item.id, data: form })}
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
                                    <div className="flex flex-1 items-start justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-zinc-900">{item.title}</p>
                                            {item.description && (
                                                <p className="mt-0.5 text-sm text-zinc-500">{item.description}</p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => startEdit(item)}>
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => deleteMut.mutate(item.id)}>
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
                            placeholder="New agenda item title"
                            autoFocus
                        />
                        <Textarea
                            value={form.description}
                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                            placeholder="Description (optional)"
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
