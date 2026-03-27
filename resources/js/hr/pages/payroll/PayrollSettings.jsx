import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Save,
    Loader2,
    Settings,
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
import { fetchPayrollSettings, updatePayrollSettings } from '../../lib/api';

export default function PayrollSettings() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({
        unpaid_leave_divisor: '26',
        pay_day: '28',
        company_name: '',
        company_address: '',
        company_epf_number: '',
        company_socso_number: '',
        company_tax_number: '',
        bank_name: '',
        bank_account: '',
    });
    const [saved, setSaved] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'settings'],
        queryFn: fetchPayrollSettings,
    });

    useEffect(() => {
        if (data?.data) {
            const settings = data.data;
            setForm({
                unpaid_leave_divisor: settings.unpaid_leave_divisor || '26',
                pay_day: settings.pay_day || '28',
                company_name: settings.company_name || '',
                company_address: settings.company_address || '',
                company_epf_number: settings.company_epf_number || '',
                company_socso_number: settings.company_socso_number || '',
                company_tax_number: settings.company_tax_number || '',
                bank_name: settings.bank_name || '',
                bank_account: settings.bank_account || '',
            });
        }
    }, [data]);

    const updateMutation = useMutation({
        mutationFn: updatePayrollSettings,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'settings'] });
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        },
    });

    function handleChange(key, value) {
        setForm((p) => ({ ...p, [key]: value }));
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
                title="Payroll Settings"
                description="Configure company information and payroll processing defaults"
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

            {/* Processing Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>Processing Defaults</CardTitle>
                    <CardDescription>Settings that affect payroll calculations</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Unpaid Leave Divisor
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                value={form.unpaid_leave_divisor}
                                onChange={(e) => handleChange('unpaid_leave_divisor', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                            <p className="mt-1 text-xs text-zinc-400">
                                Daily rate = Basic Salary / Divisor. Typically 26 (working days) or 22.
                            </p>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Pay Day
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                value={form.pay_day}
                                onChange={(e) => handleChange('pay_day', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                            <p className="mt-1 text-xs text-zinc-400">
                                Day of month when salaries are disbursed.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Company Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Company Information</CardTitle>
                    <CardDescription>Used on payslips and EA forms</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Company Name</label>
                            <input
                                type="text"
                                value={form.company_name}
                                onChange={(e) => handleChange('company_name', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. Syarikat ABC Sdn Bhd"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Company Address</label>
                            <textarea
                                value={form.company_address}
                                onChange={(e) => handleChange('company_address', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                rows={3}
                                placeholder="Full company address..."
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Statutory Registration Numbers */}
            <Card>
                <CardHeader>
                    <CardTitle>Statutory Registration Numbers</CardTitle>
                    <CardDescription>Required for statutory submission files</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">EPF Employer Number</label>
                            <input
                                type="text"
                                value={form.company_epf_number}
                                onChange={(e) => handleChange('company_epf_number', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. 12345678"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">SOCSO Employer Number</label>
                            <input
                                type="text"
                                value={form.company_socso_number}
                                onChange={(e) => handleChange('company_socso_number', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. 12345678"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Tax Employer Number (E Number)</label>
                            <input
                                type="text"
                                value={form.company_tax_number}
                                onChange={(e) => handleChange('company_tax_number', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. E12345678"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Bank Account */}
            <Card>
                <CardHeader>
                    <CardTitle>Salary Payment Bank Account</CardTitle>
                    <CardDescription>Company bank account for salary disbursement</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Bank Name</label>
                            <input
                                type="text"
                                value={form.bank_name}
                                onChange={(e) => handleChange('bank_name', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. Maybank"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Account Number</label>
                            <input
                                type="text"
                                value={form.bank_account}
                                onChange={(e) => handleChange('bank_account', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. 1234567890"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
