import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, UserPlus } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/livehost/components/ui/dialog';

/**
 * Confirm-and-register prompt for a TikTok creator that appears in the imported
 * live data but isn't a registered LiveAccount yet. Reuses the existing
 * live-accounts store endpoint: registering from just the handle (nickname) is
 * enough — the shop the live promoted is linked as the primary shop so the
 * account is immediately assignable on the calendar.
 */
export default function RegisterCreatorModal({ open, onOpenChange, creator = null, onRegistered = null }) {
  const [processing, setProcessing] = useState(false);

  if (!creator) {
    return null;
  }

  const handle = creator.creatorHandle ?? 'this creator';
  const liveCount = creator.count ?? 1;

  const register = () => {
    setProcessing(true);
    router.post(
      '/livehost/live-accounts',
      {
        nickname: creator.creatorHandle,
        creator_user_id: creator.creatorUserId || null,
        is_active: true,
        needs_review: true,
        shop_ids: creator.platformAccountId ? [creator.platformAccountId] : [],
        primary_shop_id: creator.platformAccountId || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          onOpenChange(false);
          if (onRegistered) {
            onRegistered(creator);
          }
        },
        onFinish: () => setProcessing(false),
      }
    );
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[440px]">
        <DialogHeader className="text-left">
          <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-full bg-[#FEF3C7]">
            <AlertTriangle className="h-[18px] w-[18px] text-[#B45309]" strokeWidth={2.2} />
          </div>
          <DialogTitle className="text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
            Register this creator?
          </DialogTitle>
          <DialogDescription className="text-[13px] leading-relaxed text-[#737373]">
            <span className="font-medium text-[#0A0A0A]">@{handle}</span> shows up in your TikTok
            data ({liveCount} live{liveCount === 1 ? '' : 's'} this week) but isn&rsquo;t a registered
            creator account yet — so you can&rsquo;t assign sessions to it. Register it to add it to
            your accounts and unlock scheduling.
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border border-[#F0F0F0] bg-[#FAFAFA] p-3 text-[12.5px]">
          <div className="flex items-center justify-between gap-2">
            <span className="text-[#737373]">Creator handle</span>
            <span className="font-mono font-medium text-[#0A0A0A]">@{handle}</span>
          </div>
          {creator.platformAccount && (
            <div className="mt-1.5 flex items-center justify-between gap-2">
              <span className="text-[#737373]">Linked shop</span>
              <span className="max-w-[220px] truncate font-medium text-[#0A0A0A]">
                {creator.platformAccount}
              </span>
            </div>
          )}
          <p className="mt-2 border-t border-[#EFEFEF] pt-2 text-[11.5px] text-[#A3A3A3]">
            Marked as “needs review” so you can confirm its details later in Live Accounts.
          </p>
        </div>

        <DialogFooter className="gap-2 sm:gap-2">
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="text-[#737373]">
            Not now
          </Button>
          <Button type="button" onClick={register} disabled={processing} className="gap-1.5">
            <UserPlus className="h-[14px] w-[14px]" strokeWidth={2.2} />
            {processing ? 'Registering…' : 'Register creator'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
