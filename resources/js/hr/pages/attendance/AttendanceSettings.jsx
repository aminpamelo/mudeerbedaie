import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Save,
    Loader2,
    MapPin,
    Navigation,
    Building2,
    Info,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import PageHeader from '../../components/PageHeader';
import { fetchOfficeLocation, updateOfficeLocation } from '../../lib/api';

export default function AttendanceSettings() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({
        latitude: '',
        longitude: '',
        radius_meters: '200',
        require_location: true,
    });
    const [saved, setSaved] = useState(false);
    const [detectingLocation, setDetectingLocation] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'settings', 'office-location'],
        queryFn: fetchOfficeLocation,
    });

    useEffect(() => {
        if (data?.data) {
            const s = data.data;
            setForm({
                latitude: s.latitude ? String(s.latitude) : '',
                longitude: s.longitude ? String(s.longitude) : '',
                radius_meters: s.radius_meters ? String(s.radius_meters) : '200',
                require_location: Boolean(s.require_location),
            });
        }
    }, [data]);

    const updateMutation = useMutation({
        mutationFn: updateOfficeLocation,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'settings', 'office-location'] });
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        },
    });

    function handleChange(key, value) {
        setForm((p) => ({ ...p, [key]: value }));
    }

    const detectCurrentLocation = useCallback(() => {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        setDetectingLocation(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setForm((p) => ({
                    ...p,
                    latitude: String(position.coords.latitude),
                    longitude: String(position.coords.longitude),
                }));
                setDetectingLocation(false);
            },
            (error) => {
                alert('Failed to get location: ' + error.message);
                setDetectingLocation(false);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }, []);

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
            </div>
        );
    }

    const hasValidCoords = form.latitude && form.longitude && !isNaN(form.latitude) && !isNaN(form.longitude);

    return (
        <div className="space-y-6">
            <PageHeader
                title="Attendance Settings"
                description="Configure office location and clock-in requirements"
                action={
                    <Button
                        onClick={() => updateMutation.mutate(form)}
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

            {/* Office Location */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5 text-indigo-600" />
                                Office Location
                            </CardTitle>
                            <CardDescription>
                                Set the GPS coordinates of your office. Employees must be within the specified radius to clock in.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={detectCurrentLocation}
                            disabled={detectingLocation}
                        >
                            {detectingLocation ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Navigation className="mr-2 h-4 w-4" />
                            )}
                            {detectingLocation ? 'Detecting...' : 'Use My Location'}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">
                                Latitude
                            </label>
                            <input
                                type="text"
                                value={form.latitude}
                                onChange={(e) => handleChange('latitude', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                placeholder="e.g. 3.1390"
                            />
                            <p className="mt-1 text-xs text-slate-400">
                                Range: -90 to 90
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">
                                Longitude
                            </label>
                            <input
                                type="text"
                                value={form.longitude}
                                onChange={(e) => handleChange('longitude', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                placeholder="e.g. 101.6869"
                            />
                            <p className="mt-1 text-xs text-slate-400">
                                Range: -180 to 180
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">
                                Radius (meters)
                            </label>
                            <input
                                type="number"
                                min="50"
                                max="5000"
                                value={form.radius_meters}
                                onChange={(e) => handleChange('radius_meters', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                placeholder="200"
                            />
                            <p className="mt-1 text-xs text-slate-400">
                                Allowed distance from office (50–5000m)
                            </p>
                        </div>
                    </div>

                    {/* Map Preview */}
                    {hasValidCoords && (
                        <div className="mt-6">
                            <div className="overflow-hidden rounded-lg border border-slate-200">
                                <iframe
                                    title="Office Location"
                                    width="100%"
                                    height="300"
                                    style={{ border: 0 }}
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    src={`https://www.openstreetmap.org/export/embed.html?bbox=${form.longitude - 0.005},${form.latitude - 0.003},${Number(form.longitude) + 0.005},${Number(form.latitude) + 0.003}&layer=mapnik&marker=${form.latitude},${form.longitude}`}
                                />
                            </div>
                            <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                                <MapPin className="h-3.5 w-3.5" />
                                {form.latitude}, {form.longitude} — {form.radius_meters}m radius
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Location Requirement */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MapPin className="h-5 w-5 text-indigo-600" />
                        Clock-In Requirements
                    </CardTitle>
                    <CardDescription>
                        Control whether employees need to be at the office to clock in
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-start gap-4">
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={form.require_location}
                                onChange={(e) => handleChange('require_location', e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300" />
                        </label>
                        <div>
                            <p className="text-sm font-medium text-slate-700">
                                Require office location for clock-in
                            </p>
                            <p className="mt-0.5 text-sm text-slate-500">
                                When enabled, employees selecting "Office" must be within {form.radius_meters || '200'}m of the office to clock in.
                                WFH clock-ins are not affected.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-3">
                        <div className="flex gap-2">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <div className="text-sm text-blue-700">
                                <p className="font-medium">How it works</p>
                                <ul className="mt-1 list-disc space-y-1 pl-4 text-blue-600">
                                    <li>Employees choosing <strong>Office</strong> must enable GPS and be within the radius</li>
                                    <li>Employees choosing <strong>WFH</strong> can clock in from anywhere</li>
                                    <li>GPS coordinates are recorded in the attendance log for audit</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Current Status */}
            <Card>
                <CardHeader>
                    <CardTitle>Current Configuration</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-center">
                            <p className="text-xs font-medium text-slate-500">Latitude</p>
                            <p className="mt-1 text-sm font-semibold text-slate-800">
                                {form.latitude || '—'}
                            </p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-center">
                            <p className="text-xs font-medium text-slate-500">Longitude</p>
                            <p className="mt-1 text-sm font-semibold text-slate-800">
                                {form.longitude || '—'}
                            </p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-center">
                            <p className="text-xs font-medium text-slate-500">Radius</p>
                            <p className="mt-1 text-sm font-semibold text-slate-800">
                                {form.radius_meters}m
                            </p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-center">
                            <p className="text-xs font-medium text-slate-500">Location Check</p>
                            <div className="mt-1">
                                <Badge variant={form.require_location ? 'default' : 'secondary'}>
                                    {form.require_location ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
