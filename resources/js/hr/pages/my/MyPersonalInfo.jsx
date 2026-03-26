import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Phone,
    Mail,
    MapPin,
    User,
    Pencil,
    X,
    Save,
    Loader2,
    AlertCircle,
} from 'lucide-react';
import { fetchMyProfile, updateMyProfile } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function InfoRow({ label, value }) {
    return (
        <div className="flex justify-between py-2 border-b border-zinc-100 last:border-0">
            <span className="text-sm text-zinc-500">{label}</span>
            <span className="text-sm font-medium text-zinc-900 text-right">{value || '-'}</span>
        </div>
    );
}

export default function MyPersonalInfo() {
    const queryClient = useQueryClient();
    const [editingSection, setEditingSection] = useState(null);
    const [formData, setFormData] = useState({});
    const [saveError, setSaveError] = useState(null);

    const { data: profileData, isLoading, isError, error } = useQuery({
        queryKey: ['my-profile'],
        queryFn: fetchMyProfile,
    });
    const employee = profileData?.data;

    const mutation = useMutation({
        mutationFn: updateMyProfile,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-profile'] });
            setEditingSection(null);
            setFormData({});
            setSaveError(null);
        },
        onError: (err) => {
            setSaveError(err?.response?.data?.message || 'Failed to save changes');
        },
    });

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <AlertCircle className="h-10 w-10 text-red-400 mb-3" />
                <p className="text-sm text-zinc-600">
                    {error?.response?.data?.message || 'Failed to load profile'}
                </p>
            </div>
        );
    }

    function startEdit(section, fields) {
        const initialData = {};
        fields.forEach((f) => {
            initialData[f] = employee[f] || '';
        });
        setFormData(initialData);
        setEditingSection(section);
        setSaveError(null);
    }

    function cancelEdit() {
        setEditingSection(null);
        setFormData({});
        setSaveError(null);
    }

    function handleSave() {
        mutation.mutate(formData);
    }

    function updateField(field, value) {
        setFormData((prev) => ({ ...prev, [field]: value }));
    }

    const isEditing = (section) => editingSection === section;

    return (
        <div className="space-y-4">
            {/* Contact Information */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base flex items-center gap-2">
                            <Phone className="h-4 w-4 text-zinc-500" />
                            Contact
                        </CardTitle>
                        {!isEditing('contact') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => startEdit('contact', ['phone', 'personal_email'])}
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('contact') ? (
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="phone">Phone Number</Label>
                                <Input
                                    id="phone"
                                    value={formData.phone}
                                    onChange={(e) => updateField('phone', e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label htmlFor="personal_email">Personal Email</Label>
                                <Input
                                    id="personal_email"
                                    type="email"
                                    value={formData.personal_email}
                                    onChange={(e) => updateField('personal_email', e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            {saveError && (
                                <p className="text-xs text-red-600">{saveError}</p>
                            )}
                            <div className="flex gap-2 pt-1">
                                <Button size="sm" onClick={handleSave} disabled={mutation.isPending}>
                                    {mutation.isPending ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Save className="h-3.5 w-3.5" />
                                    )}
                                    Save
                                </Button>
                                <Button size="sm" variant="outline" onClick={cancelEdit}>
                                    <X className="h-3.5 w-3.5" />
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <InfoRow label="Phone" value={employee.phone} />
                            <InfoRow label="Personal Email" value={employee.personal_email} />
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Address */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base flex items-center gap-2">
                            <MapPin className="h-4 w-4 text-zinc-500" />
                            Address
                        </CardTitle>
                        {!isEditing('address') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    startEdit('address', [
                                        'address_line_1',
                                        'address_line_2',
                                        'city',
                                        'state',
                                        'postcode',
                                    ])
                                }
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('address') ? (
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="address_line_1">Address Line 1</Label>
                                <Input
                                    id="address_line_1"
                                    value={formData.address_line_1}
                                    onChange={(e) => updateField('address_line_1', e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label htmlFor="address_line_2">Address Line 2</Label>
                                <Input
                                    id="address_line_2"
                                    value={formData.address_line_2}
                                    onChange={(e) => updateField('address_line_2', e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        value={formData.city}
                                        onChange={(e) => updateField('city', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="state">State</Label>
                                    <Input
                                        id="state"
                                        value={formData.state}
                                        onChange={(e) => updateField('state', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="postcode">Postcode</Label>
                                <Input
                                    id="postcode"
                                    value={formData.postcode}
                                    onChange={(e) => updateField('postcode', e.target.value)}
                                    className="mt-1 w-1/2"
                                />
                            </div>
                            {saveError && (
                                <p className="text-xs text-red-600">{saveError}</p>
                            )}
                            <div className="flex gap-2 pt-1">
                                <Button size="sm" onClick={handleSave} disabled={mutation.isPending}>
                                    {mutation.isPending ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Save className="h-3.5 w-3.5" />
                                    )}
                                    Save
                                </Button>
                                <Button size="sm" variant="outline" onClick={cancelEdit}>
                                    <X className="h-3.5 w-3.5" />
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <InfoRow label="Address Line 1" value={employee.address_line_1} />
                            <InfoRow label="Address Line 2" value={employee.address_line_2} />
                            <InfoRow label="City" value={employee.city} />
                            <InfoRow label="State" value={employee.state} />
                            <InfoRow label="Postcode" value={employee.postcode} />
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Personal Details */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base flex items-center gap-2">
                            <User className="h-4 w-4 text-zinc-500" />
                            Personal Details
                        </CardTitle>
                        {!isEditing('personal') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => startEdit('personal', ['marital_status'])}
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {isEditing('personal') ? (
                        <div className="space-y-3">
                            <InfoRow label="Gender" value={employee.gender} />
                            <InfoRow label="Date of Birth" value={formatDate(employee.date_of_birth)} />
                            <InfoRow label="Religion" value={employee.religion} />
                            <InfoRow label="Race" value={employee.race} />
                            <InfoRow label="IC Number" value={employee.masked_ic} />
                            <div>
                                <Label htmlFor="marital_status">Marital Status</Label>
                                <Input
                                    id="marital_status"
                                    value={formData.marital_status}
                                    onChange={(e) => updateField('marital_status', e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            {saveError && (
                                <p className="text-xs text-red-600">{saveError}</p>
                            )}
                            <div className="flex gap-2 pt-1">
                                <Button size="sm" onClick={handleSave} disabled={mutation.isPending}>
                                    {mutation.isPending ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Save className="h-3.5 w-3.5" />
                                    )}
                                    Save
                                </Button>
                                <Button size="sm" variant="outline" onClick={cancelEdit}>
                                    <X className="h-3.5 w-3.5" />
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <InfoRow label="Gender" value={employee.gender} />
                            <InfoRow label="Date of Birth" value={formatDate(employee.date_of_birth)} />
                            <InfoRow label="Religion" value={employee.religion} />
                            <InfoRow label="Race" value={employee.race} />
                            <InfoRow label="Marital Status" value={employee.marital_status} />
                            <InfoRow label="IC Number" value={employee.masked_ic} />
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
