import { useState, useEffect } from 'react';
import axios from 'axios';
import { useHrAuth } from '../../stores/useHrStore';
import EmployeeAppLayout from '../../layouts/EmployeeAppLayout';
import { motion } from 'framer-motion';
import {
    PhoneIcon, EnvelopeIcon, CalendarDaysIcon, BriefcaseIcon,
    MapPinIcon, IdentificationIcon, UserCircleIcon, BuildingOffice2Icon,
    HeartIcon, CheckBadgeIcon, ClipboardDocumentIcon, ChatBubbleLeftRightIcon,
} from '@heroicons/react/24/outline';

const TABS = [
    { id: 'overview', label: 'Overview', icon: UserCircleIcon },
    { id: 'personal', label: 'Personal', icon: MapPinIcon },
    { id: 'employment', label: 'Employment', icon: BriefcaseIcon },
    { id: 'emergency', label: 'Emergency', icon: HeartIcon },
];

const fmtDate = (d) => {
    if (! d) return null;
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

/** snake_case / array → "Title Case" */
const fmtLabel = (v) => {
    if (v == null) return null;
    const s = Array.isArray(v) ? v.join(', ') : String(v);
    if (! s.trim()) return null;
    return s.split(/[_\s,]+/).filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
};

/** human tenure from a join date, e.g. "2 yr 4 mo" */
const tenure = (d) => {
    if (! d) return null;
    const start = new Date(d);
    const now = new Date();
    let months = (now.getFullYear() - start.getFullYear()) * 12 + (now.getMonth() - start.getMonth());
    if (now.getDate() < start.getDate()) months -= 1;
    if (months < 0) return null;
    const yr = Math.floor(months / 12);
    const mo = months % 12;
    return [yr ? `${yr} yr` : null, mo ? `${mo} mo` : null].filter(Boolean).join(' ') || 'New';
};

export default function MyProfile() {
    const { user } = useHrAuth();
    const [activeTab, setActiveTab] = useState('overview');
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        let mounted = true;
        axios.get('/api/hr/me/profile')
            .then((res) => { if (mounted) setProfile(res.data.data ?? res.data); })
            .catch(() => {})
            .finally(() => { if (mounted) setLoading(false); });
        return () => { mounted = false; };
    }, []);

    const name = profile?.full_name ?? user?.name ?? 'Employee';
    const initials = name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
    const phone = profile?.phone;
    const email = profile?.personal_email;

    const copyId = async () => {
        if (! profile?.employee_id) return;
        try {
            await navigator.clipboard.writeText(profile.employee_id);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch { /* clipboard unavailable */ }
    };

    return (
        <EmployeeAppLayout title="HR Portal">
            <div className="mx-auto w-full max-w-2xl px-4 py-4 space-y-4">
                {/* Profile header */}
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0B1120]"
                >
                    {/* accent glow */}
                    <div className="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-violet-500/10 blur-2xl" aria-hidden="true" />

                    <div className="relative flex items-center gap-4">
                        <div className="grid size-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-xl font-semibold text-white ring-2 ring-white/10">
                            {initials}
                        </div>
                        <div className="min-w-0 flex-1">
                            <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">{name}</h2>
                            <p className="mt-0.5 truncate text-sm text-slate-500 dark:text-zinc-400">
                                {[profile?.department?.name, profile?.position?.title].filter(Boolean).join(' · ') || '—'}
                            </p>
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <button
                                    onClick={copyId}
                                    className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 transition active:scale-95 dark:bg-white/5 dark:text-zinc-300"
                                >
                                    <ClipboardDocumentIcon className="size-3.5" />
                                    {copied ? 'Copied!' : (profile?.employee_id ?? '—')}
                                </button>
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-600 ring-1 ring-emerald-500/30 dark:text-emerald-400">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    {fmtLabel(profile?.status) ?? 'Active'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* tenure strip */}
                    {profile?.join_date && (
                        <div className="relative mt-4 flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-2.5 dark:bg-white/[0.03]">
                            <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-zinc-400">
                                <CalendarDaysIcon className="size-4" />
                                Joined {fmtDate(profile.join_date)}
                            </div>
                            <span className="text-sm font-semibold text-slate-900 dark:text-white">{tenure(profile.join_date)}</span>
                        </div>
                    )}
                </motion.div>

                {/* Quick actions */}
                <div className="grid grid-cols-3 gap-3">
                    <QuickAction icon={PhoneIcon} label="Call" href={phone ? `tel:${phone}` : null} />
                    <QuickAction icon={EnvelopeIcon} label="Email" href={email ? `mailto:${email}` : null} />
                    <QuickAction icon={ChatBubbleLeftRightIcon} label="WhatsApp" href={phone ? `https://wa.me/${phone.replace(/\D/g, '')}` : null} />
                </div>

                {/* Tabs */}
                <div className="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`flex shrink-0 items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition active:scale-95 ${
                                activeTab === tab.id
                                    ? 'bg-violet-600 text-white shadow-sm shadow-violet-600/25'
                                    : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-zinc-400'
                            }`}
                        >
                            <tab.icon className="size-4" />
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Tab content */}
                {loading ? (
                    <Skeleton />
                ) : (
                    <motion.div
                        key={activeTab}
                        initial={{ opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.18 }}
                    >
                        {activeTab === 'overview' && (
                            <div className="space-y-4">
                                <Section title="Contact">
                                    <InfoRow icon={PhoneIcon} label="Phone" value={phone} />
                                    <InfoRow icon={EnvelopeIcon} label="Email" value={email} />
                                </Section>
                                <Section title="Employment">
                                    <InfoRow icon={BuildingOffice2Icon} label="Department" value={profile?.department?.name} />
                                    <InfoRow icon={BriefcaseIcon} label="Type" value={fmtLabel(profile?.employment_type)} />
                                </Section>
                            </div>
                        )}

                        {activeTab === 'personal' && (
                            <Section title="Personal details">
                                <InfoRow icon={IdentificationIcon} label="IC Number" value={profile?.ic_number} />
                                <InfoRow icon={UserCircleIcon} label="Gender" value={fmtLabel(profile?.gender)} />
                                <InfoRow icon={HeartIcon} label="Marital Status" value={fmtLabel(profile?.marital_status)} />
                                <InfoRow icon={MapPinIcon} label="Address" value={[profile?.address_line_1, profile?.city, profile?.state].filter(Boolean).join(', ')} />
                            </Section>
                        )}

                        {activeTab === 'employment' && (
                            <Section title="Employment">
                                <InfoRow icon={BuildingOffice2Icon} label="Department" value={profile?.department?.name} />
                                <InfoRow icon={BriefcaseIcon} label="Position" value={profile?.position?.title} />
                                <InfoRow icon={CalendarDaysIcon} label="Join Date" value={fmtDate(profile?.join_date)} />
                                <InfoRow icon={CheckBadgeIcon} label="Status" value={fmtLabel(profile?.status)} />
                            </Section>
                        )}

                        {activeTab === 'emergency' && (
                            (profile?.emergency_contact_name || profile?.emergency_contact_phone) ? (
                                <Section title="Emergency contact">
                                    <InfoRow icon={UserCircleIcon} label="Contact Name" value={profile?.emergency_contact_name} />
                                    <InfoRow icon={PhoneIcon} label="Contact Phone" value={profile?.emergency_contact_phone} />
                                    <InfoRow icon={HeartIcon} label="Relationship" value={fmtLabel(profile?.emergency_contact_relationship)} />
                                </Section>
                            ) : (
                                <EmptyState
                                    icon={HeartIcon}
                                    title="No emergency contact"
                                    hint="Add a contact so HR can reach someone in an emergency."
                                />
                            )
                        )}
                    </motion.div>
                )}
            </div>
        </EmployeeAppLayout>
    );
}

function QuickAction({ icon: Icon, label, href }) {
    const disabled = ! href;
    const external = href?.startsWith('http');
    return (
        <a
            href={href ?? undefined}
            {...(external ? { target: '_blank', rel: 'noreferrer' } : {})}
            aria-disabled={disabled}
            onClick={(e) => disabled && e.preventDefault()}
            className={`flex flex-col items-center gap-1.5 rounded-2xl border py-3 text-xs font-medium transition active:scale-95 ${
                disabled
                    ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300 dark:border-white/5 dark:bg-white/[0.02] dark:text-zinc-600'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-violet-300 hover:text-violet-600 dark:border-white/5 dark:bg-[#0B1120] dark:text-zinc-200 dark:hover:border-violet-500/40'
            }`}
        >
            <Icon className="size-5" />
            {label}
        </a>
    );
}

function Section({ title, children }) {
    return (
        <div>
            <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{title}</h3>
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/5 dark:bg-[#0B1120]">
                {children}
            </div>
        </div>
    );
}

function InfoRow({ icon: Icon, label, value }) {
    const has = value != null && String(value).trim() !== '';
    return (
        <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-white/5">
            <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-zinc-400">
                <Icon className="size-4.5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs text-slate-400 dark:text-zinc-500">{label}</p>
                {has ? (
                    <p className="truncate text-sm font-medium text-slate-900 dark:text-white">{value}</p>
                ) : (
                    <p className="text-sm italic text-slate-300 dark:text-zinc-600">Not set</p>
                )}
            </div>
        </div>
    );
}

function EmptyState({ icon: Icon, title, hint }) {
    return (
        <div className="flex flex-col items-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center dark:border-white/10 dark:bg-[#0B1120]">
            <div className="grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500">
                <Icon className="size-6" />
            </div>
            <p className="mt-3 text-sm font-medium text-slate-700 dark:text-zinc-200">{title}</p>
            <p className="mt-1 max-w-xs text-xs text-slate-400 dark:text-zinc-500">{hint}</p>
        </div>
    );
}

function Skeleton() {
    return (
        <div className="space-y-3">
            {[0, 1, 2].map((i) => (
                <div key={i} className="h-16 animate-pulse rounded-2xl bg-slate-100 dark:bg-white/5" />
            ))}
        </div>
    );
}
