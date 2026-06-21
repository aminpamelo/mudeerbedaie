import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export default function ProgramCreate() {
  const { assignableLeaders } = usePage().props;
  const form = useForm({
    title: '',
    description: '',
    leader_user_id: '',
    starts_at: '',
    ends_at: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      leader_user_id: data.leader_user_id || null,
      starts_at: data.starts_at || null,
      ends_at: data.ends_at || null,
    }));
    form.post('/livehost/mentoring/programs');
  };

  return (
    <>
      <Head title="New Mentoring Program" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Mentoring', 'Programs', 'New']}
        actions={
          <Link href="/livehost/mentoring/programs">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to programs
            </Button>
          </Link>
        }
      />

      <div className="max-w-3xl p-8">
        <div className="mb-6">
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            New mentoring program
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            Programs start as drafts with a default 5-stage pipeline (Onboarding → Graduated). You'll
            tune stages and enrol mentees next, then activate.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="space-y-5 rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
        >
          <Field label="Program title" error={form.errors.title}>
            <Input
              name="title"
              value={form.data.title}
              onChange={(e) => form.setData('title', e.target.value)}
              autoFocus
              placeholder="New Host Mentoring — Cohort June 2026"
            />
          </Field>

          <Field label="Description" error={form.errors.description}>
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={4}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              placeholder="Goals of this cohort, who it's for, what success looks like."
            />
          </Field>

          <Field
            label="Program leader (top host)"
            hint="The top host who mentors this cohort. You can change this later."
            error={form.errors.leader_user_id}
          >
            <select
              value={form.data.leader_user_id}
              onChange={(e) => form.setData('leader_user_id', e.target.value)}
              className="h-9 w-full appearance-none rounded-md border border-input bg-transparent px-3 text-sm text-[#0A0A0A] shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-emerald focus-visible:ring-[3px] focus-visible:ring-emerald/20"
            >
              <option value="">— No leader yet —</option>
              {(assignableLeaders ?? []).map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                  {u.is_top_host_eligible ? ' ★ (top-host eligible)' : ''}
                </option>
              ))}
            </select>
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Starts at" error={form.errors.starts_at}>
              <Input
                type="date"
                name="starts_at"
                value={form.data.starts_at}
                onChange={(e) => form.setData('starts_at', e.target.value)}
              />
            </Field>
            <Field label="Ends at" error={form.errors.ends_at}>
              <Input
                type="date"
                name="ends_at"
                value={form.data.ends_at}
                onChange={(e) => form.setData('ends_at', e.target.value)}
              />
            </Field>
          </div>

          <div className="flex items-center justify-end gap-2 border-t border-[#F0F0F0] pt-4">
            <Link href="/livehost/mentoring/programs">
              <Button type="button" variant="ghost" className="text-[#737373]">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create program'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

ProgramCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, hint, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {hint && !error && <p className="text-[11.5px] text-[#737373]">{hint}</p>}
      {error && <p className="mt-1 text-xs text-[#F43F5E]">{error}</p>}
    </div>
  );
}
