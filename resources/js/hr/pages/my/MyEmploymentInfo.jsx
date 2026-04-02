import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Briefcase,
    Calendar,
    Building2,
    CreditCard,
    FileText,
    Loader2,
    AlertCircle,
    Pencil,
    Check,
    X,
} from 'lucide-react';
import { fetchMyProfile, updateMyProfile } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import StatusBadge from '../../components/StatusBadge';

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

export default function MyEmploymentInfo() {
    const { data: profileData, isLoading, isError, error } = useQuery({
        queryKey: ['my-profile'],
        queryFn: fetchMyProfile,
    });
    const employee = profileData?.data;

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
                    {error?.response?.data?.message || 'Failed to load employment info'}
                </p>
            </div>
        );
    }

    const queryClient = useQueryClient();
    const [editingBank, setEditingBank] = useState(false);
    const [bankName, setBankName] = useState('');
    const [bankAccount, setBankAccount] = useState('');

    const bankMutation = useMutation({
        mutationFn: updateMyProfile,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-profile'] });
            setEditingBank(false);
        },
        onError: () => {},
    });

    const startEditBank = () => {
        setBankName(employee.bank_name || '');
        setBankAccount('');
        setEditingBank(true);
    };

    const saveBank = () => {
        const data = { bank_name: bankName };
        if (bankAccount) {
            data.bank_account_number = bankAccount;
        }
        bankMutation.mutate(data);
    };

    const isProbation = employee.status === 'probation';
    const isContract = employee.employment_type === 'contract';

    return (
        <div className="space-y-4">
            {/* Employment Details */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                        <Briefcase className="h-4 w-4 text-zinc-500" />
                        Employment Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex justify-between py-2 border-b border-zinc-100">
                        <span className="text-sm text-zinc-500">Employment Type</span>
                        <Badge variant="secondary">
                            {employee.employment_type_label || '-'}
                        </Badge>
                    </div>
                    <InfoRow label="Join Date" value={formatDate(employee.join_date)} />
                    <div className="flex justify-between py-2">
                        <span className="text-sm text-zinc-500">Status</span>
                        <StatusBadge status={employee.status} />
                    </div>
                </CardContent>
            </Card>

            {/* Probation Card */}
            {isProbation && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-amber-500" />
                            Probation Period
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InfoRow
                            label="Probation End Date"
                            value={formatDate(employee.probation_end_date)}
                        />
                        <InfoRow
                            label="Confirmation Date"
                            value={formatDate(employee.confirmation_date)}
                        />
                        {employee.probation_end_date && (
                            <div className="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3">
                                <p className="text-xs text-amber-800">
                                    Your probation period is scheduled to end on{' '}
                                    <span className="font-medium">
                                        {formatDate(employee.probation_end_date)}
                                    </span>
                                    .
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Bank Information */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base flex items-center gap-2">
                            <CreditCard className="h-4 w-4 text-zinc-500" />
                            Bank Information
                        </CardTitle>
                        {!editingBank && (
                            <button
                                onClick={startEditBank}
                                className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 transition-colors"
                            >
                                <Pencil className="h-3.5 w-3.5" />
                                Edit
                            </button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {editingBank ? (
                        <div className="space-y-3">
                            <div>
                                <label className="text-xs font-medium text-zinc-500 mb-1 block">Bank Name</label>
                                <Input
                                    value={bankName}
                                    onChange={(e) => setBankName(e.target.value)}
                                    placeholder="e.g. Maybank, CIMB, Hong Leong Bank"
                                />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-zinc-500 mb-1 block">Account Number</label>
                                <Input
                                    value={bankAccount}
                                    onChange={(e) => setBankAccount(e.target.value)}
                                    placeholder="Leave empty to keep current"
                                />
                                {employee.masked_bank_account && employee.masked_bank_account !== '-' && (
                                    <p className="text-xs text-zinc-400 mt-1">Current: {employee.masked_bank_account}</p>
                                )}
                            </div>
                            {bankMutation.isError && (
                                <p className="text-xs text-red-500">
                                    {bankMutation.error?.response?.data?.message || 'Failed to update'}
                                </p>
                            )}
                            <div className="flex items-center gap-2 pt-1">
                                <button
                                    onClick={saveBank}
                                    disabled={bankMutation.isPending || !bankName.trim()}
                                    className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    {bankMutation.isPending ? (
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                    ) : (
                                        <Check className="h-3 w-3" />
                                    )}
                                    Save
                                </button>
                                <button
                                    onClick={() => setEditingBank(false)}
                                    disabled={bankMutation.isPending}
                                    className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 bg-zinc-100 rounded-lg hover:bg-zinc-200 disabled:opacity-50 transition-colors"
                                >
                                    <X className="h-3 w-3" />
                                    Cancel
                                </button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <InfoRow label="Bank Name" value={employee.bank_name} />
                            <InfoRow label="Account Number" value={employee.masked_bank_account} />
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Contract Card */}
            {isContract && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <FileText className="h-4 w-4 text-zinc-500" />
                            Contract Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InfoRow
                            label="Contract End Date"
                            value={formatDate(employee.contract_end_date)}
                        />
                        {employee.contract_end_date && (
                            <div className="mt-3 rounded-lg bg-blue-50 border border-blue-200 p-3">
                                <p className="text-xs text-blue-800">
                                    Your contract is valid until{' '}
                                    <span className="font-medium">
                                        {formatDate(employee.contract_end_date)}
                                    </span>
                                    .
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
