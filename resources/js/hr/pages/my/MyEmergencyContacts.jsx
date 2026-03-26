import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Phone,
    Mail,
    Plus,
    Pencil,
    Trash2,
    X,
    Save,
    Users,
    Loader2,
    AlertCircle,
} from 'lucide-react';
import {
    fetchMyEmergencyContacts,
    createMyEmergencyContact,
    updateMyEmergencyContact,
    deleteMyEmergencyContact,
} from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';

const emptyContact = {
    name: '',
    relationship: '',
    phone: '',
    email: '',
    is_primary: false,
};

function ContactForm({ contact, onSave, onCancel, isPending, error }) {
    const [formData, setFormData] = useState(contact);

    function updateField(field, value) {
        setFormData((prev) => ({ ...prev, [field]: value }));
    }

    return (
        <Card>
            <CardContent className="pt-4 space-y-3">
                <div>
                    <Label htmlFor="contact-name">Full Name</Label>
                    <Input
                        id="contact-name"
                        value={formData.name}
                        onChange={(e) => updateField('name', e.target.value)}
                        placeholder="Contact full name"
                        className="mt-1"
                    />
                </div>
                <div>
                    <Label htmlFor="contact-relationship">Relationship</Label>
                    <Input
                        id="contact-relationship"
                        value={formData.relationship}
                        onChange={(e) => updateField('relationship', e.target.value)}
                        placeholder="e.g. Spouse, Parent, Sibling"
                        className="mt-1"
                    />
                </div>
                <div>
                    <Label htmlFor="contact-phone">Phone Number</Label>
                    <Input
                        id="contact-phone"
                        value={formData.phone}
                        onChange={(e) => updateField('phone', e.target.value)}
                        placeholder="Phone number"
                        className="mt-1"
                    />
                </div>
                <div>
                    <Label htmlFor="contact-email">Email (Optional)</Label>
                    <Input
                        id="contact-email"
                        type="email"
                        value={formData.email}
                        onChange={(e) => updateField('email', e.target.value)}
                        placeholder="Email address"
                        className="mt-1"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="contact-primary"
                        checked={formData.is_primary}
                        onChange={(e) => updateField('is_primary', e.target.checked)}
                        className="rounded border-zinc-300"
                    />
                    <Label htmlFor="contact-primary" className="text-sm font-normal">
                        Primary emergency contact
                    </Label>
                </div>
                {error && (
                    <p className="text-xs text-red-600">{error}</p>
                )}
                <div className="flex gap-2 pt-1">
                    <Button
                        size="sm"
                        onClick={() => onSave(formData)}
                        disabled={isPending}
                    >
                        {isPending ? (
                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                            <Save className="h-3.5 w-3.5" />
                        )}
                        Save
                    </Button>
                    <Button size="sm" variant="outline" onClick={onCancel}>
                        <X className="h-3.5 w-3.5" />
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function MyEmergencyContacts() {
    const queryClient = useQueryClient();
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [formError, setFormError] = useState(null);

    const { data: contactsData, isLoading, isError, error } = useQuery({
        queryKey: ['my-emergency-contacts'],
        queryFn: fetchMyEmergencyContacts,
    });
    const contacts = contactsData?.data ?? [];

    const createMutation = useMutation({
        mutationFn: createMyEmergencyContact,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] });
            setShowAddForm(false);
            setFormError(null);
        },
        onError: (err) => {
            setFormError(err?.response?.data?.message || 'Failed to create contact');
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateMyEmergencyContact(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] });
            setEditingId(null);
            setFormError(null);
        },
        onError: (err) => {
            setFormError(err?.response?.data?.message || 'Failed to update contact');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteMyEmergencyContact,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-emergency-contacts'] });
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
                    {error?.response?.data?.message || 'Failed to load emergency contacts'}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with Add Button */}
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-zinc-700">
                    {contacts.length} contact{contacts.length !== 1 ? 's' : ''}
                </h3>
                {!showAddForm && (
                    <Button
                        size="sm"
                        onClick={() => {
                            setShowAddForm(true);
                            setEditingId(null);
                            setFormError(null);
                        }}
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Contact
                    </Button>
                )}
            </div>

            {/* Add Form */}
            {showAddForm && (
                <ContactForm
                    contact={emptyContact}
                    onSave={(data) => createMutation.mutate(data)}
                    onCancel={() => {
                        setShowAddForm(false);
                        setFormError(null);
                    }}
                    isPending={createMutation.isPending}
                    error={formError}
                />
            )}

            {/* Contact List */}
            {contacts.length === 0 && !showAddForm ? (
                <Card>
                    <CardContent className="py-10 text-center">
                        <Users className="h-10 w-10 text-zinc-300 mx-auto mb-3" />
                        <p className="text-sm text-zinc-500">No emergency contacts added yet</p>
                        <p className="text-xs text-zinc-400 mt-1">
                            Add at least one emergency contact for safety purposes
                        </p>
                    </CardContent>
                </Card>
            ) : (
                contacts.map((contact) =>
                    editingId === contact.id ? (
                        <ContactForm
                            key={contact.id}
                            contact={{
                                name: contact.name || '',
                                relationship: contact.relationship || '',
                                phone: contact.phone || '',
                                email: contact.email || '',
                                is_primary: contact.is_primary || false,
                            }}
                            onSave={(data) =>
                                updateMutation.mutate({ id: contact.id, data })
                            }
                            onCancel={() => {
                                setEditingId(null);
                                setFormError(null);
                            }}
                            isPending={updateMutation.isPending}
                            error={formError}
                        />
                    ) : (
                        <Card key={contact.id}>
                            <CardContent className="pt-4 pb-4">
                                <div className="flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-medium text-zinc-900">
                                                {contact.name}
                                            </p>
                                            {contact.is_primary && (
                                                <Badge variant="success" className="text-[10px]">
                                                    Primary
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-zinc-500 mt-0.5">
                                            {contact.relationship}
                                        </p>
                                        <div className="mt-2 space-y-1">
                                            {contact.phone && (
                                                <div className="flex items-center gap-1.5 text-xs text-zinc-600">
                                                    <Phone className="h-3 w-3" />
                                                    <span>{contact.phone}</span>
                                                </div>
                                            )}
                                            {contact.email && (
                                                <div className="flex items-center gap-1.5 text-xs text-zinc-600">
                                                    <Mail className="h-3 w-3" />
                                                    <span>{contact.email}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0 ml-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => {
                                                setEditingId(contact.id);
                                                setShowAddForm(false);
                                                setFormError(null);
                                            }}
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-red-600 hover:text-red-700 hover:bg-red-50"
                                            onClick={() => {
                                                if (window.confirm('Delete this emergency contact?')) {
                                                    deleteMutation.mutate(contact.id);
                                                }
                                            }}
                                            disabled={deleteMutation.isPending}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )
                )
            )}
        </div>
    );
}
