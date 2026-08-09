import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FolderOpen, Plus, Pencil, Trash2 } from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, Badge, Button, Field, Input, EmptyState, Modal } from '@/vault/components/Ui';

/* ---------- Create / Edit modal ---------- */
function CategoryModal({ open, onClose, category }) {
  const isEditing = !!category;

  const form = useForm({
    name: category?.name ?? '',
    icon: category?.icon ?? '',
    color: category?.color ?? '',
    sort_order: category?.sort_order ?? 0,
  });

  const submit = (e) => {
    e.preventDefault();

    if (isEditing) {
      router.put(`/admin/vault/categories/${category.id}`, form.data, {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (errors) => form.setError(errors),
      });
    } else {
      router.post('/admin/vault/categories', form.data, {
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
      title={isEditing ? 'Edit Category' : 'Create Category'}
      hint="Organize credentials into groups"
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
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Social Media" autoFocus />
        </Field>

        <Field label="Icon" error={form.errors.icon} hint="Emoji or short icon name">
          <Input value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="e.g. 🔑" />
        </Field>

        <Field label="Color" error={form.errors.color} hint="CSS color for visual distinction">
          <Input value={form.data.color} onChange={(e) => form.setData('color', e.target.value)} placeholder="e.g. #F59E0B" />
        </Field>

        <Field label="Sort Order" error={form.errors.sort_order}>
          <Input type="number" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} />
        </Field>
      </form>
    </Modal>
  );
}

/* ---------- Main page ---------- */
export default function Categories({ categories }) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editData, setEditData] = useState(null);

  const openCreate = () => {
    setEditData(null);
    setModalOpen(true);
  };

  const openEdit = (category) => {
    setEditData(category);
    setModalOpen(true);
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditData(null);
  };

  const destroy = (category) => {
    if (!confirm(`Delete "${category.name}"? Credentials will be uncategorized.`)) return;
    router.delete(`/admin/vault/categories/${category.id}`, { preserveScroll: true });
  };

  return (
    <VaultLayout
      title="Categories"
      subtitle="Organize your credentials into groups"
      actions={
        <Button variant="primary" onClick={openCreate}>
          <div className="flex items-center justify-center gap-1.5">
            <Plus className="h-4 w-4" /> Add Category
          </div>
        </Button>
      }
    >
      <Head title="Categories" />

      {(!categories || categories.length === 0) ? (
        <EmptyState
          icon={FolderOpen}
          title="No categories yet"
          hint="Create your first category to organize credentials."
          action={<Button variant="primary" onClick={openCreate}><Plus className="h-4 w-4" /> Add Category</Button>}
        />
      ) : (
        <div className="grid gap-3">
          {categories.map((cat) => (
            <Card key={cat.id} className="flex items-center justify-between gap-4 p-4 transition-colors hover:bg-white/8">
              <div className="flex items-center gap-3 min-w-0">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-[18px]">
                  {cat.icon || '📁'}
                </span>
                <div className="min-w-0">
                  <h3 className="text-[14px] font-bold text-white">{cat.name}</h3>
                  <div className="mt-0.5 flex items-center gap-2">
                    <Badge color="slate">{cat.credentials_count} credential{cat.credentials_count !== 1 ? 's' : ''}</Badge>
                    {cat.color && (
                      <span className="flex items-center gap-1 text-[11px] text-white/40">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: cat.color }} />
                        {cat.color}
                      </span>
                    )}
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <button type="button" onClick={() => openEdit(cat)} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white" title="Edit">
                  <Pencil className="h-3.5 w-3.5" />
                </button>
                <button type="button" onClick={() => destroy(cat)} className="grid h-8 w-8 place-items-center rounded-lg text-white/40 hover:bg-rose-500/15 hover:text-rose-400" title="Delete">
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </Card>
          ))}
        </div>
      )}

      {modalOpen && (
        <CategoryModal
          open={modalOpen}
          onClose={closeModal}
          category={editData}
        />
      )}
    </VaultLayout>
  );
}
