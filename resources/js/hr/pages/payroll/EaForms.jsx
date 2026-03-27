import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Download,
    FileText,
    Loader2,
    Users,
    Archive,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import PageHeader from '../../components/PageHeader';
import { fetchEmployees, downloadEaForm, downloadEaForms } from '../../lib/api';

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function downloadBlob(data, filename) {
    const url = window.URL.createObjectURL(new Blob([data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

export default function EaForms() {
    const currentYear = new Date().getFullYear();
    const [selectedYear, setSelectedYear] = useState(currentYear - 1);
    const [downloadingId, setDownloadingId] = useState(null);
    const [downloadingBulk, setDownloadingBulk] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'ea-forms', 'employees', selectedYear],
        queryFn: () => fetchEmployees({ status: 'active', per_page: 200 }),
    });

    const employees = data?.data || [];

    async function handleDownloadIndividual(employee) {
        setDownloadingId(employee.id);
        try {
            const blob = await downloadEaForm(employee.id);
            downloadBlob(blob, `EA-${employee.employee_id}-${selectedYear}.pdf`);
        } catch (e) {
            console.error('Download failed', e);
        } finally {
            setDownloadingId(null);
        }
    }

    async function handleDownloadAll() {
        setDownloadingBulk(true);
        try {
            const blob = await downloadEaForms(selectedYear);
            downloadBlob(blob, `EA-Forms-${selectedYear}.zip`);
        } catch (e) {
            console.error('Bulk download failed', e);
        } finally {
            setDownloadingBulk(false);
        }
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="EA Forms"
                description="Generate and download annual EA forms (Form EA) for employees"
                action={
                    <Button onClick={handleDownloadAll} disabled={downloadingBulk} variant="outline">
                        {downloadingBulk ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Archive className="mr-2 h-4 w-4" />
                        )}
                        Download All (ZIP)
                    </Button>
                }
            />

            {/* Year Selector */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <label className="text-sm font-medium text-zinc-700">Tax Year:</label>
                        <select
                            value={selectedYear}
                            onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {[currentYear - 3, currentYear - 2, currentYear - 1, currentYear].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        <p className="text-sm text-zinc-500">
                            EA forms are generated based on finalized payroll runs for the selected year.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Employees Table */}
            <Card>
                <CardHeader>
                    <CardTitle>EA Forms — {selectedYear}</CardTitle>
                    <CardDescription>{employees.length} employees</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-8 w-28 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : employees.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Users className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No active employees found</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>IC / Passport</TableHead>
                                    <TableHead className="text-right">Download</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employees.map((emp) => (
                                    <TableRow key={emp.id}>
                                        <TableCell>
                                            <div>
                                                <p className="font-medium text-zinc-900">{emp.full_name}</p>
                                                <p className="text-xs text-zinc-500">{emp.employee_id}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {emp.department?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {emp.ic_number || emp.passport_number || '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleDownloadIndividual(emp)}
                                                disabled={downloadingId === emp.id}
                                            >
                                                {downloadingId === emp.id ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Download className="mr-2 h-4 w-4" />
                                                )}
                                                EA Form
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
