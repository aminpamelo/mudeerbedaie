import { useEffect, useRef, useState } from 'react';
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { GripVertical, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';
import { Button } from '@/livehost/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/livehost/components/ui/dropdown-menu';

/**
 * Left rail — list of pages with drag-reorder, add, rename, delete.
 *
 * Props:
 *  - pages:          Array<{ id, title, fields }>
 *  - selectedPageId: string
 *  - onSelect(id)
 *  - onReorder(pageIds)
 *  - onAdd()
 *  - onRename(id, newTitle)
 *  - onDelete(id)
 */
export default function PageList({
  pages = [],
  selectedPageId,
  onSelect,
  onReorder,
  onAdd,
  onRename,
  onDelete,
}) {
  const [renamingId, setRenamingId] = useState(null);
  const [renameDraft, setRenameDraft] = useState('');
  const inputRef = useRef(null);

  useEffect(() => {
    if (renamingId && inputRef.current) {
      inputRef.current.focus();
      inputRef.current.select();
    }
  }, [renamingId]);

  const beginRename = (page) => {
    setRenameDraft(page.title || '');
    setRenamingId(page.id);
  };

  const commitRename = () => {
    if (!renamingId) {
      return;
    }
    const title = (renameDraft || '').trim();
    if (title) {
      onRename?.(renamingId, title);
    }
    setRenamingId(null);
    setRenameDraft('');
  };

  const cancelRename = () => {
    setRenamingId(null);
    setRenameDraft('');
  };

  const handleDragEnd = (result) => {
    if (!result.destination) {
      return;
    }
    if (result.destination.index === result.source.index) {
      return;
    }
    const next = Array.from(pages);
    const [moved] = next.splice(result.source.index, 1);
    next.splice(result.destination.index, 0, moved);
    onReorder?.(next.map((p) => p.id));
  };

  return (
    <div className="flex w-full flex-col">
      <div className="mb-3 flex items-center justify-between px-1">
        <span className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Pages
        </span>
        <span className="font-mono text-[11px] text-[#A3A3A3]">{pages.length}</span>
      </div>

      <DragDropContext onDragEnd={handleDragEnd}>
        <Droppable droppableId="form-builder-pages">
          {(provided) => (
            <ol
              ref={provided.innerRef}
              {...provided.droppableProps}
              className="flex flex-col gap-1"
            >
              {pages.map((page, index) => {
                const isSelected = page.id === selectedPageId;
                const fieldCount = page.fields?.length ?? 0;
                const isRenaming = renamingId === page.id;

                return (
                  <Draggable draggableId={page.id} index={index} key={page.id}>
                    {(dragProvided, snapshot) => (
                      <li
                        ref={dragProvided.innerRef}
                        {...dragProvided.draggableProps}
                        className={cn(
                          'group relative flex items-center gap-2 rounded-lg border bg-white px-2 py-2 transition-all',
                          isSelected
                            ? 'border-[#E5E5E5] bg-[#FAFAFA] shadow-[0_1px_2px_rgba(0,0,0,0.04)]'
                            : 'border-transparent hover:border-[#EAEAEA] hover:bg-[#FAFAFA]',
                          snapshot.isDragging &&
                            'border-[#D4D4D4] shadow-[0_4px_12px_rgba(0,0,0,0.08)]'
                        )}
                      >
                        {isSelected && (
                          <span
                            aria-hidden="true"
                            className="absolute left-0 top-2 bottom-2 w-[2px] rounded-full bg-[#0A0A0A]"
                          />
                        )}

                        <button
                          type="button"
                          {...dragProvided.dragHandleProps}
                          className="flex h-6 w-5 shrink-0 items-center justify-center text-[#D4D4D4] opacity-0 transition-opacity hover:text-[#737373] group-hover:opacity-100"
                          aria-label="Drag page"
                        >
                          <GripVertical className="h-3.5 w-3.5" strokeWidth={2} />
                        </button>

                        <div
                          className="min-w-0 flex-1 cursor-pointer"
                          onClick={() => !isRenaming && onSelect?.(page.id)}
                          onDoubleClick={() => beginRename(page)}
                        >
                          {isRenaming ? (
                            <input
                              ref={inputRef}
                              value={renameDraft}
                              onChange={(e) => setRenameDraft(e.target.value)}
                              onBlur={commitRename}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  commitRename();
                                } else if (e.key === 'Escape') {
                                  cancelRename();
                                }
                              }}
                              className="w-full rounded-md border border-[#D4D4D4] bg-white px-1.5 py-0.5 text-[13px] font-medium text-[#0A0A0A] outline-none focus:ring-2 focus:ring-[#10B981]/20"
                            />
                          ) : (
                            <div className="min-w-0">
                              <div
                                className={cn(
                                  'truncate text-[13px] leading-tight',
                                  isSelected
                                    ? 'font-semibold text-[#0A0A0A]'
                                    : 'font-medium text-[#262626]'
                                )}
                              >
                                {page.title || 'Untitled page'}
                              </div>
                            </div>
                          )}
                        </div>

                        {!isRenaming && (
                          <span
                            className="inline-flex h-5 shrink-0 items-center rounded-full bg-[#F5F5F5] px-1.5 font-mono text-[10.5px] tabular-nums text-[#737373]"
                            title={`${fieldCount} field${fieldCount === 1 ? '' : 's'}`}
                          >
                            {fieldCount}
                          </span>
                        )}

                        {!isRenaming && (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <button
                                type="button"
                                className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[#A3A3A3] opacity-0 transition-all hover:bg-[#F0F0F0] hover:text-[#0A0A0A] group-hover:opacity-100 data-[state=open]:opacity-100"
                                aria-label="Page actions"
                                onClick={(e) => e.stopPropagation()}
                              >
                                <MoreHorizontal className="h-3.5 w-3.5" strokeWidth={2} />
                              </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-40">
                              <DropdownMenuItem
                                onSelect={() => beginRename(page)}
                                className="gap-2 text-[12.5px]"
                              >
                                <Pencil className="h-3.5 w-3.5" strokeWidth={2} />
                                Rename
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                onSelect={() => onDelete?.(page.id)}
                                disabled={pages.length <= 1}
                                variant="destructive"
                                className="gap-2 text-[12.5px]"
                              >
                                <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                                Delete
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                      </li>
                    )}
                  </Draggable>
                );
              })}
              {provided.placeholder}
            </ol>
          )}
        </Droppable>
      </DragDropContext>

      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={onAdd}
        className="mt-3 h-8 justify-start gap-1.5 rounded-lg border-dashed border-[#D4D4D4] bg-transparent text-[12.5px] text-[#525252] hover:border-[#0A0A0A] hover:bg-[#FAFAFA] hover:text-[#0A0A0A]"
      >
        <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
        Add page
      </Button>
    </div>
  );
}
