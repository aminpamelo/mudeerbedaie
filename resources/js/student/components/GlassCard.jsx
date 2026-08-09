import { cn } from '@/student/lib/utils';

export default function GlassCard({ children, className, as: Tag = 'div', ...props }) {
  return (
    <Tag className={cn('glass-card rounded-2xl shadow-sm', className)} {...props}>
      {children}
    </Tag>
  );
}
