import { Head, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { FileText, Plus, RotateCcw, Trash2, Search } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import {
  Card, Badge, Button, Field, Input, Textarea, Select,
  FileUpload, EmptyState, Pagination, Modal,
} from '@/mindpal-admin/components/Ui';
import { cn, formatBytes, formatNumber } from '@/mindpal-admin/lib/utils';

const STATUS_BADGE = {
  processing: { color: 'yellow', label: 'Processing' },
  ready: { color: 'green', label: 'Ready' },
  failed: { color: 'red', label: 'Failed' },
};

function StatsBar({ stats }) {
  return (
    <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
      {[
        { label: 'Total', value: stats?.total, tone: 'text-white' },
        { label: 'Ready', value: stats?.ready, tone: 'text-emerald-400' },
        { label: 'Processing', value: stats?.processing, tone: 'text-amber-400' },
        { label: 'Failed', value: stats?.failed, tone: 'text-rose-400' },
      ].map((s) => (
        <Card key={s.label} className="flex items-center gap-3 px-4 py-3">
          <span className={cn('text-[20px] font-extrabold tabular-nums', s.tone)}>{formatNumber(s.value)}</span>
          <span className="text-[12.5px] text-white/50">{s.label}</span>
        </Card>
      ))}
    </div>
  );
}

function DocumentRow({ doc, onDelete, onReprocess }) {
  const badge = STATUS_BADGE[doc.status] || STATUS_BADGE.processing;

  return (
    <Card className="p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <FileText className="h-4 w-4 shrink-0 text-violet-400" strokeWidth={2} />
            <span className="truncate text-[14px] font-semibold text-white">{doc.title}</span>
            <Badge color={badge.color}>{badge.label}</Badge>
          </div>
          {doc.description && (
            <p className="mt-1 text-[12.5px] text-white/50 line-clamp-2">{doc.description}</p>
          )}
          <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-white/40">
            <span>{doc.file_name}</span>
            {doc.file_size && <span>{formatBytes(doc.file_size)}</span>}
            {doc.total_pages != null && <span>{doc.total_pages} pages</span>}
            {doc.total_chunks != null && <span>{doc.total_chunks} chunks</span>}
            {doc.uploader && <span>by {doc.uploader}</span>}
            <span>{doc.created_at}</span>
          </div>
          {doc.status === 'failed' && doc.error_message && (
            <p className="mt-2 rounded-lg bg-rose-500/10 px-3 py-2 text-[12px] text-rose-400">{doc.error_message}</p>
          )}
        </div>
        <div className="flex items-center gap-1.5">
          {doc.status === 'failed' && (
            <Button variant="ghost" size="sm" onClick={() => onReprocess(doc.id)} title="Reprocess">
              <RotateCcw className="h-3.5 w-3.5 text-amber-400" />
            </Button>
          )}
          <Button variant="ghost" size="sm" onClick={() => onDelete(doc.id)} title="Delete">
            <Trash2 className="h-3.5 w-3.5 text-rose-400" />
          </Button>
        </div>
      </div>
    </Card>
  );
}

function UploadModal({ open, onClose }) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState(null);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const reset = () => {
    setTitle('');
    setDescription('');
    setFile(null);
    setErrors({});
    setSubmitting(false);
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const submit = (e) => {
    e.preventDefault();
    setSubmitting(true);

    const formData = new FormData();
    formData.append('title', title);
    if (description) {
      formData.append('description', description);
    }
    if (file) {
      formData.append('file', file);
    }

    router.post('/admin/mindpal/documents', formData, {
      forceFormData: true,
      onSuccess: () => {
        handleClose();
      },
      onError: (errs) => {
        setErrors(errs);
        setSubmitting(false);
      },
      onFinish: () => {
        setSubmitting(false);
      },
    });
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Upload Document"
      hint="Upload a PDF to add to the knowledge base"
      footer={
        <>
          <Button variant="secondary" onClick={handleClose}>Cancel</Button>
          <Button variant="primary" onClick={submit} disabled={submitting}>
            {submitting ? 'Uploading...' : 'Upload & Process'}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-4">
        <Field label="Title" error={errors.title}>
          <Input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Marketing Textbook Chapter 1"
          />
        </Field>

        <Field label="Description" hint="Optional" error={errors.description}>
          <Textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Brief description of this document..."
            rows={3}
          />
        </Field>

        <Field label="PDF File" error={errors.file}>
          <FileUpload
            accept=".pdf"
            fileName={file?.name}
            onChange={(f) => setFile(f)}
            error={errors.file}
          />
        </Field>
      </form>
    </Modal>
  );
}

export default function Documents({ documents, filters, stats }) {
  const [uploadOpen, setUploadOpen] = useState(false);
  const [search, setSearch] = useState(filters?.search || '');
  const [status, setStatus] = useState(filters?.status || '');
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  const applyFilters = useCallback((newSearch, newStatus) => {
    router.get('/admin/mindpal/documents', {
      search: newSearch || undefined,
      status: newStatus || undefined,
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  }, []);

  const handleSearch = (e) => {
    const v = e.target.value;
    setSearch(v);
    // Debounce via setTimeout
    clearTimeout(window._mindpalSearchTimeout);
    window._mindpalSearchTimeout = setTimeout(() => applyFilters(v, status), 300);
  };

  const handleStatusChange = (e) => {
    const v = e.target.value;
    setStatus(v);
    applyFilters(search, v);
  };

  const handleDelete = (id) => {
    if (deleteConfirm === id) {
      router.delete(`/admin/mindpal/documents/${id}`, { preserveScroll: true });
      setDeleteConfirm(null);
    } else {
      setDeleteConfirm(id);
      setTimeout(() => setDeleteConfirm(null), 3000);
    }
  };

  const handleReprocess = (id) => {
    router.post(`/admin/mindpal/documents/${id}/reprocess`, {}, { preserveScroll: true });
  };

  const items = documents?.data || [];

  return (
    <MindpalLayout
      title="Documents"
      subtitle="Upload and manage PDF knowledge base documents"
      actions={
        <Button variant="primary" onClick={() => setUploadOpen(true)}>
          <Plus className="h-4 w-4" />
          Upload PDF
        </Button>
      }
    >
      <Head title="Documents" />

      <StatsBar stats={stats} />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
          <Input
            value={search}
            onChange={handleSearch}
            placeholder="Search documents..."
            className="pl-10"
          />
        </div>
        <Select value={status} onChange={handleStatusChange} className="w-auto min-w-[140px]">
          <option value="">All Status</option>
          <option value="processing">Processing</option>
          <option value="ready">Ready</option>
          <option value="failed">Failed</option>
        </Select>
      </div>

      {items.length > 0 ? (
        <div className="space-y-3">
          {items.map((doc) => (
            <DocumentRow
              key={doc.id}
              doc={doc}
              onDelete={handleDelete}
              onReprocess={handleReprocess}
            />
          ))}
        </div>
      ) : (
        <EmptyState
          icon={FileText}
          title="No documents found"
          hint={filters?.search || filters?.status
            ? 'Try adjusting your filters'
            : 'Upload your first PDF to get started'}
          action={!filters?.search && !filters?.status && (
            <Button variant="primary" onClick={() => setUploadOpen(true)}>
              <Plus className="h-4 w-4" />
              Upload PDF
            </Button>
          )}
        />
      )}

      <Pagination
        links={documents?.links}
        meta={documents?.meta || { from: documents?.from, to: documents?.to, total: documents?.total }}
      />

      <UploadModal open={uploadOpen} onClose={() => setUploadOpen(false)} />
    </MindpalLayout>
  );
}
