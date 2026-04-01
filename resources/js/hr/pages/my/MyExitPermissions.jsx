import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    DoorOpen,
    Plus,
    CheckCircle2,
    XCircle,
    Hourglass,
    Loader2,
    Trash2,
    Download,
} from 'lucide-react';
import { getMyExitPermissions, cancelMyExitPermission, getExitPermissionPdfUrl } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';

// ---- Helpers ----
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '--:--';
    if (timeStr.length === 5) return timeStr;
    return new Date(`2000-01-01T${timeStr}`).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'secondary', icon: Hourglass, color: 'text-amber-600' },
    approved: { label: 'Approved', variant: 'default', icon: CheckCircle2, color: 'text-emerald-600' },
    rejected: { label: 'Rejected', variant: 'destructive', icon: XCircle, color: 'text-red-600' },
    cancelled: { label: 'Cancelled', variant: 'outline', icon: XCircle, color: 'text-zinc-500' },
};

const ERRAND_CONFIG = {
    company: { label: 'Urusan Syarikat', badgeClass: 'bg-blue-100 text-blue-700' },
    personal: { label: 'Urusan Peribadi', badgeClass: 'bg-purple-100 text-purple-700' },
};

// ========== MAIN COMPONENT ==========
export default function MyExitPermissions() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['my-exit-permissions'],
        queryFn: () => getMyExitPermissions(),
    });
    const permissions = data?.data ?? [];

    const cancelMut = useMutation({
        mutationFn: cancelMyExitPermission,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-exit-permissions'] });
        },
    });

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Exit Permissions</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Manage your office exit permission requests</p>
                </div>
                <Button size="sm" onClick={() => navigate('/my/exit-permissions/apply')}>
                    <Plus className="h-4 w-4 mr-1" /> Apply for Exit Permission
                </Button>
            </div>

            {/* Requests List */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Exit Permission Requests</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : permissions.length === 0 ? (
                        <div className="py-8 text-center">
                            <DoorOpen className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                            <p className="text-sm text-zinc-500">
                                No exit permission requests yet. Apply for one when you need to leave during work hours.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {permissions.map((permission) => {
                                const statusCfg = STATUS_CONFIG[permission.status] || STATUS_CONFIG.pending;
                                const errandCfg = ERRAND_CONFIG[permission.errand_type];
                                return (
                                    <div
                                        key={permission.id}
                                        className="flex items-start justify-between rounded-lg border border-zinc-100 p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {permission.permission_number}
                                                </p>
                                                <Badge variant={statusCfg.variant} className="text-[10px]">
                                                    {statusCfg.label}
                                                </Badge>
                                                {errandCfg && (
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${errandCfg.badgeClass}`}
                                                    >
                                                        {errandCfg.label}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-0.5">
                                                {formatDate(permission.exit_date)} · {formatTime(permission.exit_time)} → {formatTime(permission.return_time)}
                                            </p>
                                            {permission.purpose && (
                                                <p className="text-xs text-zinc-500 mt-0.5 truncate">
                                                    {permission.purpose}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 shrink-0 ml-2">
                                            {permission.status === 'approved' && (
                                                <a
                                                    href={getExitPermissionPdfUrl(permission.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center justify-center h-7 w-7 rounded text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 transition-colors"
                                                    title="Download PDF"
                                                >
                                                    <Download className="h-3.5 w-3.5" />
                                                </a>
                                            )}
                                            {permission.status === 'pending' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 w-7 p-0 text-red-500 hover:text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm('Cancel this exit permission request?')) {
                                                            cancelMut.mutate(permission.id);
                                                        }
                                                    }}
                                                    disabled={cancelMut.isPending}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
