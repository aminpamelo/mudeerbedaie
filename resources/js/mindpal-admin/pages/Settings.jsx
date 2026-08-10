import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import MindpalLayout from '@/mindpal-admin/layouts/MindpalLayout';
import { Card, Button, Field, Input, Textarea, Select } from '@/mindpal-admin/components/Ui';

export default function Settings({ settings }) {
  const form = useForm({
    model: settings.model,
    max_tokens: settings.max_tokens,
    temperature: settings.temperature,
    rate_limit: settings.rate_limit,
    max_file_size_mb: settings.max_file_size_mb,
    chunk_size: settings.chunk_size,
    system_prompt: settings.system_prompt || '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.put('/admin/mindpal/settings');
  };

  return (
    <MindpalLayout title="Settings" subtitle="Configure MindPal AI behaviour and limits">
      <Head title="Settings" />

      <form onSubmit={submit}>
        <div className="space-y-6">
          <Card className="p-5">
            <h2 className="mb-4 text-[15px] font-bold text-white">AI Model</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Model" error={form.errors.model}>
                <Select
                  value={form.data.model}
                  onChange={(e) => form.setData('model', e.target.value)}
                >
                  <option value="gpt-4o-mini">GPT-4o Mini</option>
                  <option value="gpt-4o">GPT-4o</option>
                  <option value="gpt-4-turbo">GPT-4 Turbo</option>
                </Select>
              </Field>

              <Field label="Max Tokens" hint="100 - 4000" error={form.errors.max_tokens}>
                <Input
                  type="number"
                  min={100}
                  max={4000}
                  value={form.data.max_tokens}
                  onChange={(e) => form.setData('max_tokens', parseInt(e.target.value) || 0)}
                />
              </Field>

              <Field label="Temperature" hint="0 - 2 (lower = more focused)" error={form.errors.temperature}>
                <Input
                  type="number"
                  min={0}
                  max={2}
                  step={0.1}
                  value={form.data.temperature}
                  onChange={(e) => form.setData('temperature', parseFloat(e.target.value) || 0)}
                />
              </Field>

              <Field label="Rate Limit (per hour)" hint="1 - 500 requests" error={form.errors.rate_limit}>
                <Input
                  type="number"
                  min={1}
                  max={500}
                  value={form.data.rate_limit}
                  onChange={(e) => form.setData('rate_limit', parseInt(e.target.value) || 0)}
                />
              </Field>
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="mb-4 text-[15px] font-bold text-white">Document Processing</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Max File Size (MB)" hint="1 - 100 MB" error={form.errors.max_file_size_mb}>
                <Input
                  type="number"
                  min={1}
                  max={100}
                  value={form.data.max_file_size_mb}
                  onChange={(e) => form.setData('max_file_size_mb', parseInt(e.target.value) || 0)}
                />
              </Field>

              <Field label="Chunk Size (tokens)" hint="100 - 2000" error={form.errors.chunk_size}>
                <Input
                  type="number"
                  min={100}
                  max={2000}
                  value={form.data.chunk_size}
                  onChange={(e) => form.setData('chunk_size', parseInt(e.target.value) || 0)}
                />
              </Field>
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="mb-4 text-[15px] font-bold text-white">System Prompt</h2>
            <Field
              label="Custom System Prompt"
              hint="Optional. This prompt is prepended to every AI conversation."
              error={form.errors.system_prompt}
            >
              <Textarea
                value={form.data.system_prompt}
                onChange={(e) => form.setData('system_prompt', e.target.value)}
                placeholder="You are a helpful assistant for students studying at..."
                rows={6}
              />
            </Field>
          </Card>

          <div className="flex justify-end">
            <Button variant="primary" type="submit" disabled={form.processing}>
              <Save className="h-4 w-4" />
              {form.processing ? 'Saving...' : 'Save Settings'}
            </Button>
          </div>
        </div>
      </form>
    </MindpalLayout>
  );
}
