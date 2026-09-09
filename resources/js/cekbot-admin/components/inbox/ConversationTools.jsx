import { useState } from 'react';
import { UserPlus, StickyNote } from 'lucide-react';
import { Modal, Button, Textarea } from '@/cekbot-admin/components/Ui';
import { cn } from '@/cekbot-admin/lib/utils';
import { leadColor } from '@/cekbot-admin/lib/leadColors';

export default function ConversationTools({ conversation, staff, availableLabels, notes, onAssign, onLabels, onAddNote }) {
  const [notesOpen, setNotesOpen] = useState(false);
  const [noteText, setNoteText] = useState('');
  const [saving, setSaving] = useState(false);
  const labels = conversation.labels || [];

  function toggleLabel(key) {
    const next = labels.includes(key) ? labels.filter((l) => l !== key) : [...labels, key];
    onLabels(next);
  }

  function submitNote() {
    const body = noteText.trim();
    if (!body) return;
    setSaving(true);
    onAddNote(body, () => { setNoteText(''); setSaving(false); });
  }

  return (
    <div className="flex flex-wrap items-center gap-2 border-b border-white/8 bg-white/[0.02] px-4 py-2">
      <div className="flex items-center gap-1.5">
        <UserPlus className="h-3.5 w-3.5 text-white/40" strokeWidth={2.2} />
        <select
          value={conversation.assigned_to || ''}
          onChange={(e) => onAssign(e.target.value || null)}
          className="cursor-pointer rounded-lg border-0 bg-white/8 px-2 py-1 text-[12px] text-white/80 ring-1 ring-inset ring-white/10 focus:outline-none focus:ring-2 focus:ring-emerald-500/60"
        >
          <option value="">Belum ditugaskan</option>
          {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
      </div>

      <div className="flex flex-wrap items-center gap-1">
        {availableLabels.map((l) => {
          const on = labels.includes(l.key);
          const c = leadColor(l.color);
          return (
            <button
              key={l.key}
              type="button"
              onClick={() => toggleLabel(l.key)}
              className={cn(
                'rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset transition-colors',
                on ? cn(c.chip, c.ring) : 'text-white/40 ring-white/10 hover:bg-white/5'
              )}
            >
              {l.name}
            </button>
          );
        })}
      </div>

      <button
        type="button"
        onClick={() => setNotesOpen(true)}
        className="ml-auto flex items-center gap-1.5 rounded-lg bg-white/8 px-2.5 py-1.5 text-[12px] font-semibold text-white/70 hover:bg-white/12"
      >
        <StickyNote className="h-3.5 w-3.5" strokeWidth={2.2} /> Nota{notes.length ? ` (${notes.length})` : ''}
      </button>

      <Modal open={notesOpen} onClose={() => setNotesOpen(false)} size="md" title="Nota dalaman" hint="Nota ini untuk pasukan sahaja — pelanggan tidak nampak.">
        <div className="space-y-3">
          <div className="flex gap-2">
            <Textarea rows={2} value={noteText} onChange={(e) => setNoteText(e.target.value)} placeholder="Tulis nota…" className="flex-1" />
            <Button variant="primary" loading={saving} onClick={submitNote}>Tambah</Button>
          </div>
          <div className="max-h-64 space-y-2 overflow-y-auto">
            {notes.length === 0 ? (
              <p className="py-6 text-center text-[12.5px] text-white/40">Belum ada nota.</p>
            ) : notes.map((n) => (
              <div key={n.id} className="rounded-xl bg-white/5 p-3">
                <p className="whitespace-pre-wrap text-[13px] text-white/85">{n.body}</p>
                <p className="mt-1 text-[11px] text-white/35">{n.author ?? 'Staf'} · {n.created_ago}</p>
              </div>
            ))}
          </div>
        </div>
      </Modal>
    </div>
  );
}
