import { Badge } from './ui/badge';

const statusConfig = {
    active: { label: 'Active', variant: 'success' },
    probation: { label: 'Probation', variant: 'warning' },
    resigned: { label: 'Resigned', variant: 'secondary' },
    terminated: { label: 'Terminated', variant: 'destructive' },
    on_leave: { label: 'On Leave', variant: 'outline' },
};

export default function StatusBadge({ status }) {
    const config = statusConfig[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}
