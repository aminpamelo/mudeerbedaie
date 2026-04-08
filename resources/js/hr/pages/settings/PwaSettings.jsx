import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Save,
    Loader2,
    Upload,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import PageHeader from '../../components/PageHeader';
import { fetchPwaSettings, updatePwaSettings } from '../../lib/api';

export default function PwaSettings() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({
        pwa_app_name: 'Mudeer HR',
        pwa_short_name: 'HR',
        pwa_description: '',
        pwa_theme_color: '#1e40af',
        pwa_background_color: '#ffffff',
        pwa_display: 'standalone',
        pwa_orientation: 'portrait-primary',
        pwa_start_url: '/hr/clock',
        pwa_scope: '/hr',
        pwa_push_enabled: false,
        pwa_vapid_public: '',
        pwa_vapid_private: '',
        pwa_vapid_subject: '',
        pwa_icon_192_url: null,
        pwa_icon_512_url: null,
        pwa_icon_192_file: null,
        pwa_icon_512_file: null,
        pwa_icon_192_preview: null,
        pwa_icon_512_preview: null,
    });
    const [saved, setSaved] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'pwa', 'settings'],
        queryFn: fetchPwaSettings,
    });

    useEffect(() => {
        if (data?.data) {
            const settings = data.data;
            setForm((p) => ({
                ...p,
                pwa_app_name: settings.pwa_app_name || 'Mudeer HR',
                pwa_short_name: settings.pwa_short_name || 'HR',
                pwa_description: settings.pwa_description || '',
                pwa_theme_color: settings.pwa_theme_color || '#1e40af',
                pwa_background_color: settings.pwa_background_color || '#ffffff',
                pwa_display: settings.pwa_display || 'standalone',
                pwa_orientation: settings.pwa_orientation || 'portrait-primary',
                pwa_start_url: settings.pwa_start_url || '/hr/clock',
                pwa_scope: settings.pwa_scope || '/hr',
                pwa_push_enabled: !!settings.pwa_push_enabled,
                pwa_vapid_public: settings.pwa_vapid_public || '',
                pwa_vapid_private: settings.pwa_vapid_private || '',
                pwa_vapid_subject: settings.pwa_vapid_subject || '',
                pwa_vapid_configured: !!settings.pwa_vapid_configured,
                pwa_icon_192_url: settings.pwa_icon_192_url || null,
                pwa_icon_512_url: settings.pwa_icon_512_url || null,
                pwa_icon_192_file: null,
                pwa_icon_512_file: null,
                pwa_icon_192_preview: null,
                pwa_icon_512_preview: null,
            }));
        }
    }, [data]);

    const updateMutation = useMutation({
        mutationFn: updatePwaSettings,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'pwa', 'settings'] });
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        },
    });

    function handleChange(key, value) {
        setForm((p) => ({ ...p, [key]: value }));
    }

    function handleSave() {
        const data = { ...form };
        // Replace file objects for FormData
        if (form.pwa_icon_192_file) {
            data.pwa_icon_192 = form.pwa_icon_192_file;
        }
        if (form.pwa_icon_512_file) {
            data.pwa_icon_512 = form.pwa_icon_512_file;
        }
        // Remove preview/file/url keys that aren't settings
        delete data.pwa_icon_192_file;
        delete data.pwa_icon_512_file;
        delete data.pwa_icon_192_preview;
        delete data.pwa_icon_512_preview;
        delete data.pwa_icon_192_url;
        delete data.pwa_icon_512_url;
        // Remove read-only VAPID fields (managed via .env)
        delete data.pwa_vapid_public;
        delete data.pwa_vapid_private;
        delete data.pwa_vapid_subject;
        delete data.pwa_vapid_configured;
        // Convert boolean to 1/0 for FormData
        data.pwa_push_enabled = form.pwa_push_enabled ? '1' : '0';
        updateMutation.mutate(data);
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="PWA Settings"
                description="Configure Progressive Web App appearance, display, and push notifications"
                action={
                    <Button
                        onClick={handleSave}
                        disabled={updateMutation.isPending}
                    >
                        {updateMutation.isPending ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="mr-2 h-4 w-4" />
                        )}
                        {saved ? 'Saved!' : 'Save Settings'}
                    </Button>
                }
            />

            {/* Branding */}
            <Card>
                <CardHeader>
                    <CardTitle>Branding</CardTitle>
                    <CardDescription>App name, colors, and icons for the installed PWA</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                App Name
                            </label>
                            <input
                                type="text"
                                value={form.pwa_app_name}
                                onChange={(e) => handleChange('pwa_app_name', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. Mudeer HR"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Short Name
                            </label>
                            <input
                                type="text"
                                value={form.pwa_short_name}
                                onChange={(e) => handleChange('pwa_short_name', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. HR"
                            />
                            <p className="mt-1 text-xs text-zinc-400">
                                Displayed on home screen
                            </p>
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Description
                            </label>
                            <textarea
                                value={form.pwa_description}
                                onChange={(e) => handleChange('pwa_description', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                rows={2}
                                placeholder="Brief description of the app..."
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Theme Color
                            </label>
                            <div className="flex gap-2">
                                <input
                                    type="color"
                                    value={form.pwa_theme_color}
                                    onChange={(e) => handleChange('pwa_theme_color', e.target.value)}
                                    className="h-10 w-14 cursor-pointer rounded-lg border border-zinc-300"
                                />
                                <input
                                    type="text"
                                    value={form.pwa_theme_color}
                                    onChange={(e) => handleChange('pwa_theme_color', e.target.value)}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                    placeholder="#1e40af"
                                />
                            </div>
                            <p className="mt-1 text-xs text-zinc-400">
                                Browser chrome color
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Background Color
                            </label>
                            <div className="flex gap-2">
                                <input
                                    type="color"
                                    value={form.pwa_background_color}
                                    onChange={(e) => handleChange('pwa_background_color', e.target.value)}
                                    className="h-10 w-14 cursor-pointer rounded-lg border border-zinc-300"
                                />
                                <input
                                    type="text"
                                    value={form.pwa_background_color}
                                    onChange={(e) => handleChange('pwa_background_color', e.target.value)}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                    placeholder="#ffffff"
                                />
                            </div>
                            <p className="mt-1 text-xs text-zinc-400">
                                Splash screen background
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                App Icon (192x192)
                            </label>
                            <div className="flex items-center gap-4">
                                {(form.pwa_icon_192_preview || form.pwa_icon_192_url) && (
                                    <img
                                        src={form.pwa_icon_192_preview || form.pwa_icon_192_url}
                                        alt="Icon 192"
                                        className="h-16 w-16 rounded-lg border border-zinc-200 object-cover"
                                    />
                                )}
                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-50">
                                    <Upload className="h-4 w-4" />
                                    Choose File
                                    <input
                                        type="file"
                                        accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files[0];
                                            if (file) {
                                                handleChange('pwa_icon_192_file', file);
                                                handleChange('pwa_icon_192_preview', URL.createObjectURL(file));
                                            }
                                        }}
                                    />
                                </label>
                            </div>
                            <p className="mt-1 text-xs text-zinc-400">
                                PNG or SVG, recommended 192x192px
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                App Icon (512x512)
                            </label>
                            <div className="flex items-center gap-4">
                                {(form.pwa_icon_512_preview || form.pwa_icon_512_url) && (
                                    <img
                                        src={form.pwa_icon_512_preview || form.pwa_icon_512_url}
                                        alt="Icon 512"
                                        className="h-16 w-16 rounded-lg border border-zinc-200 object-cover"
                                    />
                                )}
                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-50">
                                    <Upload className="h-4 w-4" />
                                    Choose File
                                    <input
                                        type="file"
                                        accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files[0];
                                            if (file) {
                                                handleChange('pwa_icon_512_file', file);
                                                handleChange('pwa_icon_512_preview', URL.createObjectURL(file));
                                            }
                                        }}
                                    />
                                </label>
                            </div>
                            <p className="mt-1 text-xs text-zinc-400">
                                PNG or SVG, recommended 512x512px
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Display Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>Display Settings</CardTitle>
                    <CardDescription>Control how the PWA appears when installed on a device</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Display Mode
                            </label>
                            <select
                                value={form.pwa_display}
                                onChange={(e) => handleChange('pwa_display', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="standalone">Standalone</option>
                                <option value="fullscreen">Fullscreen</option>
                                <option value="minimal-ui">Minimal UI</option>
                                <option value="browser">Browser</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Orientation
                            </label>
                            <select
                                value={form.pwa_orientation}
                                onChange={(e) => handleChange('pwa_orientation', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="any">Any</option>
                                <option value="portrait-primary">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Start URL
                            </label>
                            <input
                                type="text"
                                value={form.pwa_start_url}
                                onChange={(e) => handleChange('pwa_start_url', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="/hr/clock"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Scope
                            </label>
                            <input
                                type="text"
                                value={form.pwa_scope}
                                onChange={(e) => handleChange('pwa_scope', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="/hr"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Push Notifications */}
            <Card>
                <CardHeader>
                    <CardTitle>Push Notifications</CardTitle>
                    <CardDescription>Configure Web Push via VAPID for real-time notifications</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        <label className="flex items-center gap-3">
                            <input
                                type="checkbox"
                                checked={form.pwa_push_enabled}
                                onChange={(e) => handleChange('pwa_push_enabled', e.target.checked)}
                                className="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                            />
                            <span className="text-sm font-medium text-zinc-700">Enable Push Notifications</span>
                        </label>

                        {form.pwa_push_enabled && (
                            <div className="grid grid-cols-1 gap-6">
                                {form.pwa_vapid_configured ? (
                                    <>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                                VAPID Public Key
                                            </label>
                                            <input
                                                type="text"
                                                value={form.pwa_vapid_public}
                                                readOnly
                                                className="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                                VAPID Private Key
                                            </label>
                                            <input
                                                type="password"
                                                value={form.pwa_vapid_private}
                                                readOnly
                                                className="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                                VAPID Subject
                                            </label>
                                            <input
                                                type="text"
                                                value={form.pwa_vapid_subject}
                                                readOnly
                                                className="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500"
                                            />
                                        </div>
                                        <p className="text-xs text-zinc-400">
                                            VAPID keys are configured via environment variables (.env). To change them, update VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, and VAPID_SUBJECT in your .env file.
                                        </p>
                                    </>
                                ) : (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                                        <p className="text-sm text-amber-800">
                                            VAPID keys are not configured. Add VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, and VAPID_SUBJECT to your .env file to enable push notifications.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
