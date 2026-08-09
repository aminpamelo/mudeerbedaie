import { Head, router, useForm } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import toast from 'react-hot-toast';
import {
  Key, Plus, Search, Eye, EyeOff, Copy, Pencil, Trash2,
  ExternalLink, RefreshCw, Globe, X,
} from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, Badge, Button, Field, Input, Textarea, Select, EmptyState, Pagination, Modal } from '@/vault/components/Ui';
import { cn, getJson, generatePassword, copyToClipboard, hostnameFrom, timeAgo } from '@/vault/lib/utils';

/* ---------- Password generator panel ---------- */
function PasswordGenerator({ onInsert }) {
  const [length, setLength] = useState(16);
  const [uppercase, setUppercase] = useState(true);
  const [lowercase, setLowercase] = useState(true);
  const [numbers, setNumbers] = useState(true);
  const [symbols, setSymbols] = useState(true);
  const [preview, setPreview] = useState('');

  const generate = useCallback(() => {
    const pw = generatePassword({ length, uppercase, lowercase, numbers, symbols });
    setPreview(pw);
    return pw;
  }, [length, uppercase, lowercase, numbers, symbols]);

  return (
    <div className="mt-3 rounded-xl border border-white/8 bg-white/4 p-3">
      <div className="mb-3 flex items-center justify-between">
        <span className="text-[12px] font-semibold text-white/60">Password Generator</span>
      </div>
      <div className="mb-3 flex items-center gap-2">
        <span className="text-[12px] text-white/50 shrink-0">Length: {length}</span>
        <input
          type="range"
          min={8}
          max={64}
          value={length}
          onChange={(e) => setLength(Number(e.target.value))}
          className="flex-1 accent-amber-500"
        />
      </div>
      <div className="mb-3 flex flex-wrap gap-3">
        {[
          ['Uppercase', uppercase, setUppercase],
          ['Lowercase', lowercase, setLowercase],
          ['Numbers', numbers, setNumbers],
          ['Symbols', symbols, setSymbols],
        ].map(([label, checked, setter]) => (
          <label key={label} className="flex items-center gap-1.5 text-[12px] text-white/60 cursor-pointer">
            <input type="checkbox" checked={checked} onChange={(e) => setter(e.target.checked)} className="accent-amber-500 rounded" />
            {label}
          </label>
        ))}
      </div>
      {preview && (
        <div className="mb-3 rounded-lg bg-black/30 px-3 py-2 font-mono text-[13px] text-amber-300 break-all">{preview}</div>
      )}
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" onClick={generate}>
          <RefreshCw className="h-3.5 w-3.5" /> Generate
        </Button>
        {preview && (
          <Button size="sm" variant="primary" onClick={() => onInsert(preview)}>
            Use Password
          </Button>
        )}
      </div>
    </div>
  );
}

/* ---------- Tag input ---------- */
function TagInput({ tags, onChange }) {
  const [input, setInput] = useState('');

  const addTag = (value) => {
    const trimmed = value.trim();
    if (trimmed && !tags.includes(trimmed)) {
      onChange([...tags, trimmed]);
    }
    setInput('');
  };

  return (
    <div>
      <div className="flex flex-wrap gap-1.5 mb-2">
        {tags.map((tag) => (
          <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-300">
            {tag}
            <button type="button" onClick={() => onChange(tags.filter((t) => t !== tag))} className="text-amber-300/60 hover:text-amber-300">
              <X className="h-3 w-3" />
            </button>
          </span>
        ))}
      </div>
      <Input
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') { e.preventDefault(); addTag(input); }
        }}
        placeholder="Type tag and press Enter"
      />
    </div>
  );
}

/* ---------- Create / Edit modal ---------- */
function CredentialModal({ open, onClose, credential, categories }) {
  const isEditing = !!credential;

  const form = useForm({
    name: credential?.name ?? '',
    url: credential?.url ?? '',
    username: credential?.username ?? '',
    password: credential?.password ?? '',
    category_id: credential?.category?.id ?? '',
    tags: credential?.tags?.map((t) => t.name) ?? [],
    notes: credential?.notes ?? '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [showGenerator, setShowGenerator] = useState(false);

  const submit = (e) => {
    e.preventDefault();

    const data = {
      ...form.data,
      category_id: form.data.category_id || null,
    };

    // For edit: omit password if not changed (empty means "don't change")
    if (isEditing && !data.password) {
      delete data.password;
    }

    if (isEditing) {
      router.put(`/admin/vault/credentials/${credential.id}`, data, {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (errors) => form.setError(errors),
      });
    } else {
      router.post('/admin/vault/credentials', data, {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (errors) => form.setError(errors),
      });
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEditing ? 'Edit Credential' : 'Add Credential'}
      hint={isEditing ? 'Update login details' : 'Store a new login credential'}
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={submit} disabled={form.processing}>
            {form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create')}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-4">
        <Field label="Name" error={form.errors.name}>
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. GitHub" autoFocus />
        </Field>

        <Field label="URL" error={form.errors.url}>
          <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://github.com" type="url" />
        </Field>

        <Field label="Username / Email" error={form.errors.username}>
          <Input value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} placeholder="user@example.com" />
        </Field>

        <Field label="Password" error={form.errors.password} hint={isEditing ? 'Leave blank to keep current password' : undefined}>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Input
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                type={showPassword ? 'text' : 'password'}
                placeholder={isEditing ? '(unchanged)' : 'Enter password'}
                className="pr-20"
              />
              <div className="absolute right-1.5 top-1/2 -translate-y-1/2 flex gap-0.5">
                <button type="button" onClick={() => setShowPassword(!showPassword)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white">
                  {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </button>
                {form.data.password && (
                  <button type="button" onClick={() => { copyToClipboard(form.data.password); toast.success('Copied!'); }} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white">
                    <Copy className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
            </div>
            <Button size="md" variant="secondary" onClick={() => setShowGenerator(!showGenerator)}>
              <RefreshCw className="h-4 w-4" />
            </Button>
          </div>
          {showGenerator && (
            <PasswordGenerator onInsert={(pw) => { form.setData('password', pw); setShowGenerator(false); toast.success('Password inserted'); }} />
          )}
        </Field>

        <Field label="Category" error={form.errors.category_id}>
          <Select value={form.data.category_id} onChange={(e) => form.setData('category_id', e.target.value)}>
            <option value="">No category</option>
            {categories?.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </Select>
        </Field>

        <Field label="Tags" error={form.errors.tags}>
          <TagInput tags={form.data.tags} onChange={(tags) => form.setData('tags', tags)} />
        </Field>

        <Field label="Notes" error={form.errors.notes}>
          <Textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} rows={3} placeholder="Private notes..." />
        </Field>
      </form>
    </Modal>
  );
}

/* ---------- Credential card ---------- */
function CredentialCard({ credential, categories, onEdit }) {
  const [revealedPassword, setRevealedPassword] = useState(null);
  const [loading, setLoading] = useState(false);

  const fetchPassword = async () => {
    if (revealedPassword) {
      setRevealedPassword(null);
      return;
    }
    setLoading(true);
    try {
      const data = await getJson(`/admin/vault/credentials/${credential.id}`);
      setRevealedPassword(data.password);
    } catch {
      toast.error('Failed to fetch password');
    } finally {
      setLoading(false);
    }
  };

  const copyPassword = async () => {
    try {
      const data = await getJson(`/admin/vault/credentials/${credential.id}`);
      if (data.password) {
        await copyToClipboard(data.password);
        toast.success('Password copied');
      }
    } catch {
      toast.error('Failed to copy password');
    }
  };

  const destroy = () => {
    if (!confirm(`Delete "${credential.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/vault/credentials/${credential.id}`, { preserveScroll: true });
  };

  const hostname = hostnameFrom(credential.url);

  return (
    <Card className="p-4 transition-colors hover:bg-white/8">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-3 min-w-0">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/15">
            <Key className="h-4.5 w-4.5 text-amber-400" strokeWidth={2} />
          </span>
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="text-[14px] font-bold text-white">{credential.name}</h3>
              {credential.category && <Badge color="amber">{credential.category.name}</Badge>}
            </div>
            {credential.username && (
              <p className="mt-0.5 text-[12.5px] text-white/50">{credential.username}</p>
            )}
            {hostname && (
              <div className="mt-1 flex items-center gap-1">
                <Globe className="h-3 w-3 text-white/30" />
                <span className="text-[11.5px] text-white/40">{hostname}</span>
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center gap-1 shrink-0">
          <button type="button" onClick={fetchPassword} disabled={loading} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Show/hide password">
            {loading ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : revealedPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
          </button>
          <button type="button" onClick={copyPassword} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Copy password">
            <Copy className="h-3.5 w-3.5" />
          </button>
          {credential.url && (
            <a href={credential.url} target="_blank" rel="noopener noreferrer" className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Open URL">
              <ExternalLink className="h-3.5 w-3.5" />
            </a>
          )}
          <button type="button" onClick={onEdit} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Edit">
            <Pencil className="h-3.5 w-3.5" />
          </button>
          <button type="button" onClick={destroy} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-rose-500/15 hover:text-rose-400" title="Delete">
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {revealedPassword && (
        <div className="mt-3 rounded-lg bg-black/30 px-3 py-2 font-mono text-[13px] text-amber-300 break-all">
          {revealedPassword}
        </div>
      )}

      {credential.tags?.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1">
          {credential.tags.map((tag) => (
            <Badge key={tag.id} color="slate">{tag.name}</Badge>
          ))}
        </div>
      )}
    </Card>
  );
}

/* ---------- Main page ---------- */
export default function Credentials({ credentials, filters, categories, tags }) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editData, setEditData] = useState(null);
  const [loadingEdit, setLoadingEdit] = useState(false);

  const openCreate = () => {
    setEditData(null);
    setModalOpen(true);
  };

  const openEdit = async (credential) => {
    setLoadingEdit(true);
    try {
      const data = await getJson(`/admin/vault/credentials/${credential.id}`);
      setEditData(data);
      setModalOpen(true);
    } catch {
      toast.error('Failed to load credential');
    } finally {
      setLoadingEdit(false);
    }
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditData(null);
  };

  const applyFilter = (key, value) => {
    router.get('/admin/vault/credentials', {
      ...filters,
      [key]: value || undefined,
    }, { preserveState: true, preserveScroll: true });
  };

  const items = credentials?.data ?? [];
  const paginationLinks = credentials?.links;
  const paginationMeta = credentials?.meta ?? credentials;

  return (
    <VaultLayout
      title="Credentials"
      subtitle="Manage your stored passwords and login details"
      actions={
        <Button variant="primary" onClick={openCreate}>
          <div className="flex items-center justify-center gap-1.5">
            <Plus className="h-4 w-4" /> Add Credential
          </div>
        </Button>
      }
    >
      <Head title="Credentials" />

      {/* Filters */}
      <div className="mb-5 flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
          <Input
            value={filters?.search ?? ''}
            onChange={(e) => applyFilter('search', e.target.value)}
            placeholder="Search credentials..."
            className="pl-10"
          />
        </div>
        <Select value={filters?.category ?? ''} onChange={(e) => applyFilter('category', e.target.value)} className="w-auto min-w-[140px]">
          <option value="">All Categories</option>
          {categories?.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </Select>
        <Select value={filters?.tag ?? ''} onChange={(e) => applyFilter('tag', e.target.value)} className="w-auto min-w-[120px]">
          <option value="">All Tags</option>
          {tags?.map((t) => (
            <option key={t.id} value={t.id}>{t.name}</option>
          ))}
        </Select>
      </div>

      {/* List */}
      {items.length === 0 ? (
        <EmptyState
          icon={Key}
          title="No credentials found"
          hint="Add your first credential to get started."
          action={<Button variant="primary" onClick={openCreate}><Plus className="h-4 w-4" /> Add Credential</Button>}
        />
      ) : (
        <div className="grid gap-3">
          {items.map((cred) => (
            <CredentialCard
              key={cred.id}
              credential={cred}
              categories={categories}
              onEdit={() => openEdit(cred)}
            />
          ))}
        </div>
      )}

      <Pagination links={paginationLinks} meta={paginationMeta} />

      {modalOpen && (
        <CredentialModal
          open={modalOpen}
          onClose={closeModal}
          credential={editData}
          categories={categories}
        />
      )}
    </VaultLayout>
  );
}
