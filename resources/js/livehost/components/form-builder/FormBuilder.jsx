import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';
import FieldCanvas from './FieldCanvas';
import FieldSettings from './FieldSettings';
import PageList from './PageList';
import { findFieldType } from './fieldTypes';
import { validateSchema } from './validateSchema';

const randomSuffix = () => Math.random().toString(36).slice(2, 8);

/**
 * Root form builder component. Composes PageList + FieldCanvas + FieldSettings
 * and owns the selection state. Schema state itself lives on the caller, which
 * receives updates via `onChange`.
 *
 * Props:
 *  - schema:           current schema object
 *  - onChange(schema): callback with the updated schema
 *  - applicantCounts:  Record<fieldId, number> (optional)
 */
export default function FormBuilder({ schema, onChange, applicantCounts = {} }) {
  const pages = schema?.pages ?? [];
  const [selectedPageId, setSelectedPageId] = useState(pages[0]?.id ?? null);
  const [selectedFieldId, setSelectedFieldId] = useState(null);
  const [errorsExpanded, setErrorsExpanded] = useState(false);

  // If the selected page disappears (e.g. deleted externally), fall back to the first page.
  useEffect(() => {
    if (!pages.find((p) => p.id === selectedPageId)) {
      setSelectedPageId(pages[0]?.id ?? null);
      setSelectedFieldId(null);
    }
  }, [pages, selectedPageId]);

  const selectedPage = useMemo(
    () => pages.find((p) => p.id === selectedPageId) ?? null,
    [pages, selectedPageId]
  );

  const selectedField = useMemo(() => {
    if (!selectedPage || !selectedFieldId) {
      return null;
    }
    return selectedPage.fields?.find((f) => f.id === selectedFieldId) ?? null;
  }, [selectedPage, selectedFieldId]);

  const validation = useMemo(() => validateSchema(schema ?? {}), [schema]);

  // ─── Page handlers ──────────────────────────────────────────────────────

  const addPage = () => {
    const newPage = {
      id: `p_${randomSuffix()}`,
      title: 'New page',
      fields: [],
    };
    onChange?.({ ...schema, pages: [...pages, newPage] });
    setSelectedPageId(newPage.id);
    setSelectedFieldId(null);
  };

  const deletePage = (id) => {
    if (pages.length <= 1) {
      return;
    }
    const nextPages = pages.filter((p) => p.id !== id);
    onChange?.({ ...schema, pages: nextPages });
    if (selectedPageId === id) {
      setSelectedPageId(nextPages[0]?.id ?? null);
      setSelectedFieldId(null);
    }
  };

  const renamePage = (id, title) => {
    onChange?.({
      ...schema,
      pages: pages.map((p) => (p.id === id ? { ...p, title } : p)),
    });
  };

  const reorderPages = (ids) => {
    const ordered = ids.map((id) => pages.find((p) => p.id === id)).filter(Boolean);
    onChange?.({ ...schema, pages: ordered });
  };

  // ─── Field handlers ─────────────────────────────────────────────────────

  const addField = (type) => {
    const typeDef = findFieldType(type);
    if (!typeDef || !selectedPage) {
      return;
    }
    const newField = typeDef.defaultSettings();
    onChange?.({
      ...schema,
      pages: pages.map((p) =>
        p.id === selectedPage.id ? { ...p, fields: [...(p.fields ?? []), newField] } : p
      ),
    });
    setSelectedFieldId(newField.id);
  };

  const updateField = (updated) => {
    onChange?.({
      ...schema,
      pages: pages.map((p) => ({
        ...p,
        fields: (p.fields ?? []).map((f) => (f.id === updated.id ? updated : f)),
      })),
    });
  };

  const deleteField = (id) => {
    onChange?.({
      ...schema,
      pages: pages.map((p) => ({
        ...p,
        fields: (p.fields ?? []).filter((f) => f.id !== id),
      })),
    });
    if (selectedFieldId === id) {
      setSelectedFieldId(null);
    }
  };

  const reorderFieldsInPage = (pageId, fieldIds) => {
    onChange?.({
      ...schema,
      pages: pages.map((p) => {
        if (p.id !== pageId) {
          return p;
        }
        const lookup = new Map((p.fields ?? []).map((f) => [f.id, f]));
        return {
          ...p,
          fields: fieldIds.map((fid) => lookup.get(fid)).filter(Boolean),
        };
      }),
    });
  };

  // ─── Render ─────────────────────────────────────────────────────────────

  return (
    <div className="flex flex-col">
      {validation.errors.length > 0 && (
        <div className="mb-4 rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] p-3">
          <button
            type="button"
            onClick={() => setErrorsExpanded((v) => !v)}
            className="flex w-full items-center gap-2 text-left"
          >
            <AlertCircle className="h-4 w-4 shrink-0 text-[#B91C1C]" strokeWidth={2} />
            <span className="flex-1 text-[12.5px] font-medium text-[#991B1B]">
              {validation.errors.length} {validation.errors.length === 1 ? 'issue' : 'issues'}{' '}
              in this form
            </span>
            {errorsExpanded ? (
              <ChevronUp className="h-3.5 w-3.5 text-[#991B1B]" strokeWidth={2} />
            ) : (
              <ChevronDown className="h-3.5 w-3.5 text-[#991B1B]" strokeWidth={2} />
            )}
          </button>
          {errorsExpanded && (
            <ul className="mt-2 space-y-1 pl-6 text-[11.5px] text-[#991B1B]">
              {validation.errors.map((err, i) => (
                <li key={i} className="list-disc">
                  {err}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      <div
        className={cn(
          'grid gap-4',
          'grid-cols-1',
          'lg:grid-cols-[260px_minmax(0,1fr)_320px]'
        )}
      >
        <aside className="rounded-[14px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <PageList
            pages={pages}
            selectedPageId={selectedPageId}
            onSelect={(id) => {
              setSelectedPageId(id);
              setSelectedFieldId(null);
            }}
            onReorder={reorderPages}
            onAdd={addPage}
            onRename={renamePage}
            onDelete={deletePage}
          />
        </aside>

        <main className="rounded-[14px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {selectedPage ? (
            <FieldCanvas
              page={selectedPage}
              selectedFieldId={selectedFieldId}
              onSelectField={setSelectedFieldId}
              onReorderFields={(ids) => reorderFieldsInPage(selectedPage.id, ids)}
              onAddField={addField}
              onDeleteField={deleteField}
              applicantCounts={applicantCounts}
            />
          ) : (
            <div className="grid place-items-center py-16 text-[13px] text-[#737373]">
              No page selected.
            </div>
          )}
        </main>

        <aside className="rounded-[14px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <FieldSettings
            field={selectedField}
            onChange={updateField}
            canChangeType={true}
            applicantCount={selectedField ? applicantCounts[selectedField.id] ?? 0 : 0}
          />
        </aside>
      </div>
    </div>
  );
}
