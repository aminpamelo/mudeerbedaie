import { useCallback } from 'react';
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { AlertTriangle, GripVertical, Plus, Settings2, Trash2 } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import { findFieldType, ROLE_COMPAT } from './fieldTypes';

const CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];
const TEXTUAL_TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'url', 'date', 'datetime'];
const DATA_TYPES = [
  'text',
  'textarea',
  'email',
  'phone',
  'number',
  'url',
  'select',
  'radio',
  'checkbox_group',
  'file',
  'date',
  'datetime',
];

function slugify(value) {
  return (value || '')
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40);
}

/**
 * Right rail — edits properties of the selected field.
 *
 * Props:
 *  - field:           field object | null
 *  - onChange(field): callback with the updated field
 *  - canChangeType:   boolean (defaults true)
 *  - applicantCount:  number (optional) — shown as warning pill
 */
export default function FieldSettings({
  field,
  onChange,
  canChangeType = true,
  applicantCount = 0,
}) {
  const typeDef = field ? findFieldType(field.type) : null;

  const patch = useCallback(
    (partial) => {
      if (!field) {
        return;
      }
      onChange?.({ ...field, ...partial });
    },
    [field, onChange]
  );

  if (!field) {
    return (
      <div className="flex h-full flex-col items-center justify-center rounded-[14px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] p-8 text-center">
        <div className="mb-3 grid h-9 w-9 place-items-center rounded-full bg-white text-[#737373] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <Settings2 className="h-4 w-4" strokeWidth={2} />
        </div>
        <p className="text-[12.5px] font-medium text-[#525252]">Select a field to edit</p>
        <p className="mt-1 max-w-[220px] text-[11.5px] text-[#737373]">
          Click a field on the canvas to see its settings here.
        </p>
      </div>
    );
  }

  const isDisplay = field.type === 'heading' || field.type === 'paragraph';
  const isChoice = CHOICE_TYPES.includes(field.type);
  const roleOptions = Object.entries(ROLE_COMPAT)
    .filter(([, compat]) => compat.includes(field.type))
    .map(([role]) => role);

  return (
    <div className="flex h-full flex-col">
      <div className="mb-4 flex items-center justify-between gap-2 px-1">
        <div className="min-w-0">
          <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
            Field settings
          </div>
          <div className="mt-0.5 flex items-center gap-1.5">
            <span className="truncate text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
              {typeDef?.label ?? field.type}
            </span>
            <span className="inline-flex items-center rounded-full bg-[#F5F5F5] px-1.5 py-0.5 font-mono text-[10px] text-[#525252]">
              {field.type}
            </span>
          </div>
        </div>
      </div>

      {applicantCount > 0 && (
        <div className="mb-3 flex items-start gap-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] p-2.5">
          <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#B45309]" strokeWidth={2} />
          <div className="text-[11.5px] leading-relaxed text-[#92400E]">
            Used by {applicantCount} applicant{applicantCount === 1 ? '' : 's'}. Changing the type
            or deleting this field may break historical data.
          </div>
        </div>
      )}

      <div className="flex-1 space-y-4">
        {!isDisplay && (
          <SettingField label="Label" hint="Shown above the input on the public form">
            <Input
              value={field.label ?? ''}
              onChange={(e) => patch({ label: e.target.value })}
              placeholder="Full name"
            />
          </SettingField>
        )}

        {!isDisplay && !isChoice && TEXTUAL_TYPES.includes(field.type) && (
          <SettingField label="Placeholder" hint="Optional">
            <Input
              value={field.placeholder ?? ''}
              onChange={(e) => patch({ placeholder: e.target.value })}
              placeholder="e.g. Ahmad Rahman"
            />
          </SettingField>
        )}

        {!isDisplay && (
          <SettingField label="Help text" hint="Optional">
            <Input
              value={field.help_text ?? ''}
              onChange={(e) => patch({ help_text: e.target.value })}
              placeholder="Appears as hint under the field"
            />
          </SettingField>
        )}

        {!isDisplay && (
          <div className="flex items-center justify-between rounded-lg border border-[#EAEAEA] bg-white px-3 py-2.5">
            <div>
              <Label className="text-[12.5px] font-medium text-[#0A0A0A]">Required</Label>
              <p className="mt-0.5 text-[11px] text-[#737373]">
                Applicants must fill this field before submit.
              </p>
            </div>
            <ToggleSwitch
              checked={!!field.required}
              onChange={(v) => patch({ required: v })}
            />
          </div>
        )}

        {!isDisplay && DATA_TYPES.includes(field.type) && (
          <SettingField
            label="Role"
            hint="Tags a semantic meaning used by hire flow + dedupe"
          >
            <select
              value={field.role ?? ''}
              onChange={(e) => {
                const value = e.target.value;
                if (value === '') {
                  const { role, ...rest } = field;
                  void role;
                  onChange?.(rest);
                } else {
                  patch({ role: value });
                }
              }}
              className="h-9 w-full rounded-md border border-[#EAEAEA] bg-white px-3 text-[13px] text-[#0A0A0A] shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
              <option value="">None</option>
              {roleOptions.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
          </SettingField>
        )}

        {isChoice && (
          <OptionsEditor
            options={field.options ?? []}
            onChange={(options) => patch({ options })}
          />
        )}

        {field.type === 'file' && (
          <>
            <SettingField
              label="Accepted file types"
              hint="Comma-separated extensions (e.g. pdf, doc, docx)"
            >
              <Input
                value={(field.accept ?? []).join(', ')}
                onChange={(e) => {
                  const parts = e.target.value
                    .split(',')
                    .map((p) => p.trim().toLowerCase().replace(/^\./, ''))
                    .filter(Boolean);
                  patch({ accept: parts });
                }}
                placeholder="pdf, doc, docx"
              />
            </SettingField>
            <SettingField label="Max size (KB)" hint="Applicant upload size limit">
              <Input
                type="number"
                min={1}
                value={field.max_size_kb ?? ''}
                onChange={(e) => {
                  const v = e.target.value;
                  patch({ max_size_kb: v === '' ? null : Number(v) });
                }}
                placeholder="5120"
                className="tabular-nums"
              />
            </SettingField>
          </>
        )}

        {field.type === 'textarea' && (
          <SettingField label="Rows" hint="Height of the textarea (2–12)">
            <Input
              type="number"
              min={2}
              max={12}
              value={field.rows ?? 4}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (Number.isFinite(v)) {
                  patch({ rows: Math.max(2, Math.min(12, v)) });
                }
              }}
              className="tabular-nums"
            />
          </SettingField>
        )}

        {field.type === 'heading' && (
          <SettingField label="Heading text">
            <Input
              value={field.text ?? ''}
              onChange={(e) => patch({ text: e.target.value })}
              placeholder="Section heading"
            />
          </SettingField>
        )}

        {field.type === 'paragraph' && (
          <SettingField label="Paragraph text">
            <textarea
              value={field.text ?? ''}
              onChange={(e) => patch({ text: e.target.value })}
              rows={4}
              placeholder="Explanatory text shown on the form."
              className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2.5 text-[13px] leading-relaxed text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </SettingField>
        )}

        {!canChangeType && (
          <div className="rounded-lg border border-[#F0F0F0] bg-[#FAFAFA] p-2.5 text-[11.5px] text-[#737373]">
            Type is locked because applicants have already submitted data for this field.
          </div>
        )}

        <div className="mt-4 rounded-lg border border-[#F0F0F0] bg-[#FAFAFA] px-3 py-2">
          <div className="text-[10px] font-semibold uppercase tracking-[0.08em] text-[#A3A3A3]">
            Field ID
          </div>
          <div className="mt-0.5 font-mono text-[11px] text-[#525252]">{field.id}</div>
        </div>
      </div>
    </div>
  );
}

function SettingField({ label, hint, children }) {
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between">
        <Label className="text-[12.5px] font-medium text-[#0A0A0A]">{label}</Label>
        {hint && <span className="text-[10.5px] text-[#A3A3A3]">{hint}</span>}
      </div>
      {children}
    </div>
  );
}

function ToggleSwitch({ checked, onChange }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange?.(!checked)}
      className={cn(
        'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border transition-colors',
        checked ? 'border-[#059669] bg-[#10B981]' : 'border-[#D4D4D4] bg-[#E5E5E5]'
      )}
    >
      <span
        className={cn(
          'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform',
          checked ? 'translate-x-[18px]' : 'translate-x-0.5'
        )}
      />
    </button>
  );
}

function OptionsEditor({ options, onChange }) {
  const handleDragEnd = (result) => {
    if (!result.destination) {
      return;
    }
    if (result.destination.index === result.source.index) {
      return;
    }
    const next = Array.from(options);
    const [moved] = next.splice(result.source.index, 1);
    next.splice(result.destination.index, 0, moved);
    onChange?.(next);
  };

  const updateOption = (index, patch) => {
    const next = options.map((o, i) => (i === index ? { ...o, ...patch } : o));
    onChange?.(next);
  };

  const removeOption = (index) => {
    const next = options.filter((_, i) => i !== index);
    onChange?.(next);
  };

  const addOption = () => {
    const n = options.length + 1;
    onChange?.([...options, { label: `Option ${n}`, value: `opt_${n}` }]);
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <Label className="text-[12.5px] font-medium text-[#0A0A0A]">Options</Label>
        <span className="text-[10.5px] text-[#A3A3A3]">{options.length} option{options.length === 1 ? '' : 's'}</span>
      </div>

      <DragDropContext onDragEnd={handleDragEnd}>
        <Droppable droppableId="option-list">
          {(provided) => (
            <div
              ref={provided.innerRef}
              {...provided.droppableProps}
              className="flex flex-col gap-1.5"
            >
              {options.map((opt, index) => (
                <Draggable
                  draggableId={`opt-${index}-${opt.value || 'new'}`}
                  index={index}
                  key={`opt-${index}`}
                >
                  {(dragProvided, snapshot) => (
                    <div
                      ref={dragProvided.innerRef}
                      {...dragProvided.draggableProps}
                      className={cn(
                        'group flex items-center gap-1.5 rounded-md border border-[#EAEAEA] bg-white p-1.5',
                        snapshot.isDragging && 'shadow-[0_4px_12px_rgba(0,0,0,0.08)]'
                      )}
                    >
                      <span
                        {...dragProvided.dragHandleProps}
                        className="flex h-6 w-4 shrink-0 cursor-grab items-center justify-center text-[#D4D4D4] hover:text-[#737373]"
                      >
                        <GripVertical className="h-3 w-3" strokeWidth={2} />
                      </span>
                      <input
                        value={opt.label ?? ''}
                        onChange={(e) => {
                          const nextLabel = e.target.value;
                          const prevAutoValue = slugify(opt.label ?? '');
                          // Auto-generate value if user hasn't customised it yet
                          if (!opt.value || opt.value === prevAutoValue) {
                            updateOption(index, {
                              label: nextLabel,
                              value: slugify(nextLabel) || opt.value || `opt_${index + 1}`,
                            });
                          } else {
                            updateOption(index, { label: nextLabel });
                          }
                        }}
                        placeholder="Label"
                        className="h-7 min-w-0 flex-1 rounded border-0 bg-transparent px-1 text-[12.5px] text-[#0A0A0A] outline-none placeholder:text-[#A3A3A3] focus:bg-[#FAFAFA]"
                      />
                      <span className="text-[10px] text-[#D4D4D4]">·</span>
                      <input
                        value={opt.value ?? ''}
                        onChange={(e) => updateOption(index, { value: e.target.value })}
                        placeholder="value"
                        className="h-7 w-24 min-w-0 rounded border-0 bg-transparent px-1 font-mono text-[11px] text-[#525252] outline-none placeholder:text-[#A3A3A3] focus:bg-[#FAFAFA]"
                      />
                      <button
                        type="button"
                        onClick={() => removeOption(index)}
                        className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-[#A3A3A3] opacity-0 hover:bg-[#FFF1F2] hover:text-[#F43F5E] group-hover:opacity-100"
                        aria-label="Remove option"
                      >
                        <Trash2 className="h-3 w-3" strokeWidth={2} />
                      </button>
                    </div>
                  )}
                </Draggable>
              ))}
              {provided.placeholder}
            </div>
          )}
        </Droppable>
      </DragDropContext>

      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={addOption}
        className="h-7 w-full justify-start gap-1.5 rounded-md border-dashed border-[#D4D4D4] text-[12px] text-[#525252] hover:border-[#0A0A0A] hover:bg-[#FAFAFA] hover:text-[#0A0A0A]"
      >
        <Plus className="h-3 w-3" strokeWidth={2.25} />
        Add option
      </Button>
    </div>
  );
}
