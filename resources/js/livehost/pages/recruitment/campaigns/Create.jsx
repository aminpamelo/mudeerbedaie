import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

function slugify(value) {
  return String(value ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 255);
}

export default function CampaignCreate() {
  const form = useForm({
    title: '',
    slug: '',
    description: '',
    target_count: '',
    opens_at: '',
    closes_at: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.post('/livehost/recruitment/campaigns');
  };

  return (
    <>
      <Head title="New Recruitment Campaign" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Recruitment', 'Campaigns', 'New']}
        actions={
          <Link href="/livehost/recruitment/campaigns">
            <Button
              variant="ghost"
              className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to campaigns
            </Button>
          </Link>
        }
      />

      <div className="max-w-3xl p-8">
        <div className="mb-6">
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            New recruitment campaign
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            Campaigns start as drafts. You'll configure stages next, then publish.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="space-y-5 rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
        >
          <Field label="Campaign title" error={form.errors.title}>
            <Input
              name="title"
              value={form.data.title}
              onChange={(e) => {
                const title = e.target.value;
                form.setData((data) => ({
                  ...data,
                  title,
                  slug: data.slug && data.slug !== slugify(data.title) ? data.slug : slugify(title),
                }));
              }}
              autoFocus
              placeholder="Hiring TikTok Live Hosts — April 2026"
            />
          </Field>

          <Field
            label="URL slug"
            hint="Public URL becomes /recruitment/<slug>."
            error={form.errors.slug}
          >
            <Input
              name="slug"
              value={form.data.slug}
              onChange={(e) => form.setData('slug', slugify(e.target.value))}
              placeholder="hiring-tiktok-live-hosts-april-2026"
            />
          </Field>

          <Field label="Description" error={form.errors.description}>
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={5}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              placeholder="What this recruitment is for, role expectations, compensation hints, etc."
            />
          </Field>

          <div className="grid grid-cols-3 gap-4">
            <Field label="Target hires" error={form.errors.target_count}>
              <Input
                type="number"
                min="1"
                name="target_count"
                value={form.data.target_count}
                onChange={(e) => form.setData('target_count', e.target.value)}
              />
            </Field>
            <Field label="Opens at" error={form.errors.opens_at}>
              <Input
                type="datetime-local"
                name="opens_at"
                value={form.data.opens_at}
                onChange={(e) => form.setData('opens_at', e.target.value)}
              />
            </Field>
            <Field label="Closes at" error={form.errors.closes_at}>
              <Input
                type="datetime-local"
                name="closes_at"
                value={form.data.closes_at}
                onChange={(e) => form.setData('closes_at', e.target.value)}
              />
            </Field>
          </div>

          <div className="flex items-center justify-end gap-2 border-t border-[#F0F0F0] pt-4">
            <Link href="/livehost/recruitment/campaigns">
              <Button type="button" variant="ghost" className="text-[#737373]">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create campaign'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

CampaignCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

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
