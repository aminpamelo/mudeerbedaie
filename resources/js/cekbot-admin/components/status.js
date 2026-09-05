import { CheckCircle2, QrCode, Loader2, Power, AlertCircle, Smartphone } from 'lucide-react';

/**
 * Map a WAHA session status to a display label, badge colour and icon.
 * Mirrors the states defined on the CekbotSession model.
 */
export function statusMeta(status) {
  switch (status) {
    case 'WORKING':
      return { label: 'Bersambung', color: 'emerald', Icon: CheckCircle2, spin: false };
    case 'SCAN_QR_CODE':
      return { label: 'Perlu scan QR', color: 'amber', Icon: QrCode, spin: false };
    case 'STARTING':
      return { label: 'Sedang mula…', color: 'blue', Icon: Loader2, spin: true };
    case 'STOPPED':
      return { label: 'Dihentikan', color: 'slate', Icon: Power, spin: false };
    case 'FAILED':
      return { label: 'Gagal', color: 'red', Icon: AlertCircle, spin: false };
    default:
      return { label: 'Belum disambung', color: 'slate', Icon: Smartphone, spin: false };
  }
}
