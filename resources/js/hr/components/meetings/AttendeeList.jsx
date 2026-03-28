import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Users } from 'lucide-react';
import { updateAttendeeStatus } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Badge } from '../ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../ui/select';

const ATTENDANCE_STATUS = {
    invited: { label: 'Invited', variant: 'secondary' },
    attended: { label: 'Attended', variant: 'success' },
    absent: { label: 'Absent', variant: 'destructive' },
    excused: { label: 'Excused', variant: 'warning' },
};

const ROLE_BADGE = {
    organizer: { label: 'Organizer', className: 'bg-indigo-100 text-indigo-800 border-transparent' },
    note_taker: { label: 'Note Taker', className: 'bg-purple-100 text-purple-800 border-transparent' },
    attendee: { label: 'Attendee', variant: 'secondary' },
};

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

export default function AttendeeList({ meetingId, attendees, noteTakerId, organizerId, onUpdate }) {
    const queryClient = useQueryClient();

    const statusMut = useMutation({
        mutationFn: ({ employeeId, status }) => updateAttendeeStatus(meetingId, employeeId, { attendance_status: status }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', meetingId] });
            onUpdate?.();
        },
    });

    function getRole(attendee) {
        const empId = attendee.employee_id || attendee.id;
        if (empId === organizerId) return 'organizer';
        if (empId === noteTakerId) return 'note_taker';
        return 'attendee';
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5 text-zinc-400" />
                    Attendees
                    <Badge variant="secondary">{attendees.length}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                {attendees.length === 0 ? (
                    <p className="text-sm text-zinc-500">No attendees added.</p>
                ) : (
                    <div className="space-y-2">
                        {attendees.map((att) => {
                            const employee = att.employee || att;
                            const empId = att.employee_id || att.id;
                            const role = getRole(att);
                            const roleConfig = ROLE_BADGE[role];
                            const attStatus = att.attendance_status || att.pivot?.attendance_status || 'invited';
                            const statusConfig = ATTENDANCE_STATUS[attStatus] || ATTENDANCE_STATUS.invited;

                            return (
                                <div
                                    key={empId}
                                    className="flex items-center justify-between rounded-lg border border-zinc-100 px-3 py-2"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-600">
                                            {employee.profile_photo_url ? (
                                                <img
                                                    src={employee.profile_photo_url}
                                                    alt={employee.full_name}
                                                    className="h-9 w-9 rounded-full object-cover"
                                                />
                                            ) : (
                                                getInitials(employee.full_name || employee.name)
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-zinc-900">
                                                {employee.full_name || employee.name}
                                            </p>
                                            <p className="text-xs text-zinc-500">
                                                {employee.department?.name || ''}
                                            </p>
                                        </div>
                                        <Badge variant={roleConfig.variant} className={roleConfig.className}>
                                            {roleConfig.label}
                                        </Badge>
                                    </div>
                                    <div>
                                        <Select
                                            value={attStatus}
                                            onValueChange={(v) => statusMut.mutate({ employeeId: empId, status: v })}
                                        >
                                            <SelectTrigger className="h-8 w-[120px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="invited">Invited</SelectItem>
                                                <SelectItem value="attended">Attended</SelectItem>
                                                <SelectItem value="absent">Absent</SelectItem>
                                                <SelectItem value="excused">Excused</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
