import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { GripVertical, Inbox, Plus, Trash2, Upload } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';
import { Button } from '@/livehost/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/livehost/components/ui/dropdown-menu';
import { FIELD_TYPES, findFieldType } from './fieldTypes';

/**
 * Middle panel — renders the fields of the selected page as non-interactive
 * preview cards, with drag-reorder, select, delete, and an "add field" picker.
 *
 * Props:
 *  - page:             { id, title, fields }
 *  - selectedFieldId:  string | null
 *  - onSelectField(id)
 *  - onReorderFields(fieldIds)
 *  - onAddField(type)
 *  - onDeleteField(id)
 *  - applicantCounts:  Record<fieldId, number> (optional)
 */
export default function FieldCanvas({
  page,
  selectedFieldId,
  onSelectField,
  onReorderFields,
  onAddField,
  onDeleteField,
  applicantCounts = {},
}) {
  const fields = page?.fields ?? [];

  const handleDragEnd = (result) => {
    if (!result.destination) {
      return;
    }
    if (result.destination.index === result.source.index) {
      return;
    }
    const next = Array.from(fields);
    const [moved] = next.splice(result.source.index, 1);
    next.splice(result.destination.index, 0, moved);
    onReorderFields?.(next.map((f) => f.id));
  };

  const groupedTypes = FIELD_TYPES.reduce((acc, t) => {
    (acc[t.group] = acc[t.group] || []).push(t);
    return acc;
  }, {});
  const groupOrder = ['Text', 'Choice', 'Other', 'Display'];

  return (
    <div className="flex h-full flex-col">
      <div className="mb-4 flex items-center justify-between px-1">
        <div>
          <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
            Page
          </div>
          <div className="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
            {page?.title || 'Untitled page'}
          </div>
        </div>
        <span className="inline-flex items-center gap-1 rounded-full border border-[#F0F0F0] bg-[#FAFAFA] px-2 py-0.5 font-mono text-[10.5px] tabular-nums text-[#737373]">
          {fields.length} field{fields.length === 1 ? '' : 's'}
        </span>
      </div>

      <div className="flex-1">
        {fields.length === 0 ? (
          <EmptyState />
        ) : (
          <DragDropContext onDragEnd={handleDragEnd}>
            <Droppable droppableId={`field-canvas-${page?.id ?? 'none'}`}>
              {(provided) => (
                <div
                  ref={provided.innerRef}
                  {...provided.droppableProps}
                  className="flex flex-col gap-2"
                >
                  {fields.map((field, index) => {
                    const count = applicantCounts[field.id] ?? 0;
                    const isUsed = count > 0;
                    return (
                      <Draggable draggableId={field.id} index={index} key={field.id}>
                        {(dragProvided, snapshot) => (
                          <FieldCard
                            field={field}
                            isSelected={field.id === selectedFieldId}
                            isDragging={snapshot.isDragging}
                            dragHandleProps={dragProvided.dragHandleProps}
                            draggableProps={dragProvided.draggableProps}
                            innerRef={dragProvided.innerRef}
                            onSelect={() => onSelectField?.(field.id)}
                            onDelete={() => onDeleteField?.(field.id)}
                            isUsed={isUsed}
                            usageCount={count}
                          />
                        )}
                      </Draggable>
                    );
                  })}
                  {provided.placeholder}
                </div>
              )}
            </Droppable>
          </DragDropContext>
        )}
      </div>

      <div className="mt-4">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-9 w-full gap-1.5 rounded-lg border-dashed border-[#D4D4D4] bg-transparent text-[13px] text-[#525252] hover:border-[#0A0A0A] hover:bg-[#FAFAFA] hover:text-[#0A0A0A]"
            >
              <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
              Add field
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="center" className="w-64">
            {groupOrder.map((group, gi) => (
              <div key={group}>
                {gi > 0 && <DropdownMenuSeparator />}
                <DropdownMenuLabel className="text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#A3A3A3]">
                  {group}
                </DropdownMenuLabel>
                {(groupedTypes[group] || []).map((t) => {
                  const Icon = t.icon;
                  return (
                    <DropdownMenuItem
                      key={t.type}
                      onSelect={() => onAddField?.(t.type)}
                      className="gap-2 text-[12.5px]"
                    >
                      <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                      {t.label}
                    </DropdownMenuItem>
                  );
                })}
              </div>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center rounded-[14px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-14 text-center">
      <div className="mb-3 grid h-10 w-10 place-items-center rounded-full bg-white text-[#737373] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <Inbox className="h-4 w-4" strokeWidth={2} />
      </div>
      <h3 className="text-[13.5px] font-semibold text-[#0A0A0A]">No fields yet</h3>
      <p className="mt-1 max-w-xs text-[12px] text-[#737373]">
        Add one below to start collecting information on this page.
      </p>
    </div>
  );
}

function FieldCard({
  field,
  isSelected,
  isDragging,
  dragHandleProps,
  draggableProps,
  innerRef,
  onSelect,
  onDelete,
  isUsed,
  usageCount,
}) {
  const typeDef = findFieldType(field.type);

  return (
    <div
      ref={innerRef}
      {...draggableProps}
      onClick={onSelect}
      className={cn(
        'group relative cursor-pointer rounded-[12px] border bg-white p-4 transition-all',
        isSelected
          ? 'border-[#10B981] ring-2 ring-[#10B981]/20'
          : 'border-[#EAEAEA] hover:border-[#D4D4D4]',
        isDragging && 'shadow-[0_8px_24px_rgba(0,0,0,0.08)]'
      )}
    >
      <div className="flex items-start gap-2">
        <button
          type="button"
          {...dragHandleProps}
          onClick={(e) => e.stopPropagation()}
          className="mt-0.5 flex h-5 w-5 shrink-0 cursor-grab items-center justify-center text-[#D4D4D4] opacity-0 transition-opacity hover:text-[#737373] group-hover:opacity-100 active:cursor-grabbing"
          aria-label="Drag field"
        >
          <GripVertical className="h-3.5 w-3.5" strokeWidth={2} />
        </button>

        <div className="min-w-0 flex-1">
          <FieldPreview field={field} />

          <div className="mt-2 flex items-center gap-1.5">
            <span className="inline-flex items-center gap-1 rounded-full bg-[#F5F5F5] px-1.5 py-0.5 font-mono text-[10px] text-[#525252]">
              {typeDef?.label ?? field.type}
            </span>
            {(field.required ?? false) && !['heading', 'paragraph'].includes(field.type) && (
              <span className="inline-flex items-center gap-1 rounded-full border border-[#FECACA] bg-[#FEF2F2] px-1.5 py-0.5 text-[10px] font-medium text-[#B91C1C]">
                Required
              </span>
            )}
            {field.role && (
              <span className="inline-flex items-center gap-1 rounded-full border border-[#E0E7FF] bg-[#EEF2FF] px-1.5 py-0.5 text-[10px] font-medium text-[#4338CA]">
                {field.role}
              </span>
            )}
            <span className="ml-1 font-mono text-[10px] text-[#A3A3A3]">{field.id}</span>
          </div>
        </div>

        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            if (isUsed) {
              return;
            }
            onDelete?.();
          }}
          disabled={isUsed}
          title={
            isUsed
              ? `Used by ${usageCount} applicant${usageCount === 1 ? '' : 's'}`
              : 'Delete field'
          }
          className={cn(
            'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md transition-all',
            'opacity-0 group-hover:opacity-100',
            isUsed
              ? 'cursor-not-allowed text-[#D4D4D4]'
              : 'text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]'
          )}
        >
          <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
        </button>
      </div>
    </div>
  );
}

function FieldPreview({ field }) {
  const label = (
    <label className="block text-[12.5px] font-medium text-[#0A0A0A]">
      {field.label || <span className="italic text-[#A3A3A3]">Untitled field</span>}
      {field.required && <span className="ml-0.5 text-[#F43F5E]">*</span>}
    </label>
  );

  const helpText = field.help_text ? (
    <p className="mt-1 text-[11.5px] text-[#737373]">{field.help_text}</p>
  ) : null;

  const inputBase =
    'mt-1.5 block w-full rounded-md border border-[#EAEAEA] bg-[#FAFAFA] px-3 py-2 text-[13px] text-[#525252] placeholder:text-[#A3A3A3]';

  switch (field.type) {
    case 'text':
    case 'email':
    case 'phone':
    case 'number':
    case 'url':
      return (
        <div>
          {label}
          <input
            type="text"
            disabled
            placeholder={field.placeholder || ''}
            className={inputBase}
          />
          {helpText}
        </div>
      );

    case 'textarea':
      return (
        <div>
          {label}
          <textarea
            disabled
            rows={field.rows || 3}
            placeholder={field.placeholder || ''}
            className={cn(inputBase, 'resize-none')}
          />
          {helpText}
        </div>
      );

    case 'date':
    case 'datetime':
      return (
        <div>
          {label}
          <input
            type="text"
            disabled
            placeholder={field.type === 'date' ? 'YYYY-MM-DD' : 'YYYY-MM-DD HH:MM'}
            className={inputBase}
          />
          {helpText}
        </div>
      );

    case 'select':
      return (
        <div>
          {label}
          <select disabled className={inputBase}>
            {(field.options ?? []).map((o, i) => (
              <option key={`${o.value}-${i}`} value={o.value}>
                {o.label}
              </option>
            ))}
            {(field.options ?? []).length === 0 && <option>—</option>}
          </select>
          {helpText}
        </div>
      );

    case 'radio':
      return (
        <div>
          {label}
          <div className="mt-1.5 flex flex-col gap-1.5">
            {(field.options ?? []).map((o, i) => (
              <label
                key={`${o.value}-${i}`}
                className="inline-flex items-center gap-2 text-[12.5px] text-[#525252]"
              >
                <input type="radio" disabled className="h-3.5 w-3.5 accent-[#10B981]" />
                {o.label}
              </label>
            ))}
            {(field.options ?? []).length === 0 && (
              <span className="text-[12px] italic text-[#A3A3A3]">No options yet</span>
            )}
          </div>
          {helpText}
        </div>
      );

    case 'checkbox_group':
      return (
        <div>
          {label}
          <div className="mt-1.5 flex flex-col gap-1.5">
            {(field.options ?? []).map((o, i) => (
              <label
                key={`${o.value}-${i}`}
                className="inline-flex items-center gap-2 text-[12.5px] text-[#525252]"
              >
                <input
                  type="checkbox"
                  disabled
                  className="h-3.5 w-3.5 rounded accent-[#10B981]"
                />
                {o.label}
              </label>
            ))}
            {(field.options ?? []).length === 0 && (
              <span className="text-[12px] italic text-[#A3A3A3]">No options yet</span>
            )}
          </div>
          {helpText}
        </div>
      );

    case 'file':
      return (
        <div>
          {label}
          <div className="mt-1.5 flex items-center gap-2 rounded-md border border-dashed border-[#D4D4D4] bg-[#FAFAFA] px-3 py-3 text-[12.5px] text-[#737373]">
            <Upload className="h-3.5 w-3.5" strokeWidth={2} />
            Upload file
            {field.accept && field.accept.length > 0 && (
              <span className="ml-auto font-mono text-[10.5px] text-[#A3A3A3]">
                {field.accept.join(', ')}
              </span>
            )}
          </div>
          {helpText}
        </div>
      );

    case 'heading':
      return (
        <h3 className="text-[18px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
          {field.text || <span className="italic text-[#A3A3A3]">Section heading</span>}
        </h3>
      );

    case 'paragraph':
      return (
        <p className="text-[13px] leading-relaxed text-[#525252]">
          {field.text || <span className="italic text-[#A3A3A3]">Explanatory text.</span>}
        </p>
      );

    default:
      return (
        <div>
          {label}
          <div className={inputBase}>Unknown field type: {field.type}</div>
        </div>
      );
  }
}
