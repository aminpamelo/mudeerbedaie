import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Tag, Plus, Pencil, Trash2 } from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, Badge, Button, Field, Input, EmptyState, Modal } from '@/vault/components/Ui';

/* ---------- Create / Edit modal ---------- */
function TagModal({ open, onClose, tag }) {
  const isEditing = !!tag;

  const form = useForm({
    name: tag?.name ?? '',
  });

  const submit = (e) => {
    e.preventDefault();

    if (isEditing) {
      router.put(`/admin/vault/tags/${tag.id}`, form.data, {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (errors) => form.setError(errors),
      });
    } else {
      router.post('/admin/vault/tags', form.data, {
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
      title={isEditing ? 'Edit Tag' : 'Create Tag'}
      hint="Tags help you find credentials quickly"
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
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. production" autoFocus />
        </Field>
      </form>
    </Modal>
  );
}

/* ---------- Main page ---------- */
export default function Tags({ tags }) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editData, setEditData] = useState(null);

  const openCreate = () => {
    setEditData(null);
    setModalOpen(true);
  };

  const openEdit = (tag) => {
    setEditData(tag);
    setModalOpen(true);
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditData(null);
  };

  const destroy = (tag) => {
    if (!confirm(`Delete "${tag.name}"? This will untag any credentials.`)) return;
    router.delete(`/admin/vault/tags/${tag.id}`, { preserveScroll: true });
  };

  return (
    <VaultLayout
      title="Tags"
      subtitle="Label and filter credentials with tags"
      actions={
        <Button variant="primary" onClick={openCreate}>
          <div className="flex items-center justify-center gap-1.5">
            <Plus className="h-4 w-4" /> Add Tag
          </div>
        </Button>
      }
    >
      <Head title="Tags" />

      {(!tags || tags.length === 0) ? (
        <EmptyState
          icon={Tag}
          title="No tags yet"
          hint="Create your first tag to label credentials."
          action={<Button variant="primary" onClick={openCreate}><Plus className="h-4 w-4" /> Add Tag</Button>}
        />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {tags.map((tag) => (
            <Card key={tag.id} className="flex items-center justify-between gap-4 p-4 transition-colors hover:bg-white/8">
              <div className="flex items-center gap-3 min-w-0">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/15">
                  <Tag className="h-4 w-4 text-amber-400" strokeWidth={2} />
                </span>
                <div className="min-w-0">
                  <h3 className="text-[14px] font-bold text-white">{tag.name}</h3>
                  <Badge color="slate">{tag.credentials_count} credential{tag.credentials_count !== 1 ? 's' : ''}</Badge>
                </div>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <button type="button" onClick={() => openEdit(tag)} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Edit">
                  <Pencil className="h-3.5 w-3.5" />
                </button>
                <button type="button" onClick={() => destroy(tag)} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-rose-500/15 hover:text-rose-400" title="Delete">
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </Card>
          ))}
        </div>
      )}

      {modalOpen && (
        <TagModal
          open={modalOpen}
          onClose={closeModal}
          tag={editData}
        />
      )}
    </VaultLayout>
  );
}
