import { Head, router, usePage } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import {
  CreditCard, Plus, Trash2, Star, Shield, Zap, Sparkles, X,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Card Row                                                           */
/* ------------------------------------------------------------------ */
function CardRow({ method }) {
  const [confirming, setConfirming] = useState(null); // 'delete' | 'default'

  const handleDelete = () => {
    router.delete(`/payment-methods/${method.id}`, {
      preserveScroll: true,
      onFinish: () => setConfirming(null),
    });
  };

  const handleSetDefault = () => {
    router.patch(`/payment-methods/${method.id}/default`, {}, {
      preserveScroll: true,
      onFinish: () => setConfirming(null),
    });
  };

  return (
    <div className={cn(
      'glass-card flex items-center justify-between gap-4 rounded-2xl p-4 shadow-sm transition-all',
      method.is_default && 'ring-2 ring-violet-300/60 bg-violet-50/30',
    )}>
      <div className="flex items-center gap-4 min-w-0">
        <div className="grid h-12 w-16 shrink-0 place-items-center rounded-xl bg-gray-100">
          <span className="text-[11px] font-extrabold uppercase text-gray-500">
            {method.brand}
          </span>
        </div>
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <p className="text-[14px] font-semibold text-ink">
              {method.brand.charAt(0).toUpperCase() + method.brand.slice(1)} ending in {method.last4}
            </p>
            {method.is_default && <StatusBadge variant="info">Default</StatusBadge>}
            {method.is_expired && <StatusBadge variant="danger">Expired</StatusBadge>}
          </div>
          <p className="text-[12px] text-muted">
            Expires {method.exp_month}/{method.exp_year}
          </p>
          <p className="text-[11px] text-muted-2">Added {method.created_at}</p>
        </div>
      </div>

      <div className="flex items-center gap-2 shrink-0">
        {confirming === 'default' ? (
          <div className="flex items-center gap-1.5">
            <button onClick={handleSetDefault} className="rounded-lg bg-violet-600 px-3 py-1.5 text-[11px] font-semibold text-white">Confirm</button>
            <button onClick={() => setConfirming(null)} className="rounded-lg bg-gray-100 px-3 py-1.5 text-[11px] font-semibold text-gray-600">Cancel</button>
          </div>
        ) : confirming === 'delete' ? (
          <div className="flex items-center gap-1.5">
            <button onClick={handleDelete} className="rounded-lg bg-red-600 px-3 py-1.5 text-[11px] font-semibold text-white">Delete</button>
            <button onClick={() => setConfirming(null)} className="rounded-lg bg-gray-100 px-3 py-1.5 text-[11px] font-semibold text-gray-600">Cancel</button>
          </div>
        ) : (
          <>
            {!method.is_default && (
              <button
                onClick={() => setConfirming('default')}
                className="rounded-xl bg-violet-50 px-3 py-1.5 text-[12px] font-semibold text-violet-700 transition-colors hover:bg-violet-100"
              >
                Set Default
              </button>
            )}
            <button
              onClick={() => setConfirming('delete')}
              className="grid h-8 w-8 place-items-center rounded-xl text-red-400 transition-colors hover:bg-red-50 hover:text-red-600"
            >
              <Trash2 className="h-4 w-4" strokeWidth={2} />
            </button>
          </>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Add Card Modal                                                     */
/* ------------------------------------------------------------------ */
function AddCardModal({ open, onClose, stripeKey }) {
  const cardRef = useRef(null);
  const stripeRef = useRef(null);
  const cardElementRef = useRef(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [setDefault, setSetDefault] = useState(false);

  useEffect(() => {
    if (!open || !stripeKey) return;

    // Load Stripe.js dynamically
    const loadStripe = async () => {
      if (!window.Stripe) {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => mountCard();
        document.head.appendChild(script);
      } else {
        mountCard();
      }
    };

    const mountCard = () => {
      if (!window.Stripe || !cardRef.current) return;
      stripeRef.current = window.Stripe(stripeKey);
      const elements = stripeRef.current.elements();
      cardElementRef.current = elements.create('card', {
        style: {
          base: { fontSize: '16px', color: '#424770', '::placeholder': { color: '#aab7c4' } },
        },
      });
      cardElementRef.current.mount(cardRef.current);
      cardElementRef.current.on('change', ({ error: err }) => setError(err ? err.message : ''));
    };

    loadStripe();

    return () => {
      if (cardElementRef.current) {
        try { cardElementRef.current.destroy(); } catch (e) {}
        cardElementRef.current = null;
      }
    };
  }, [open, stripeKey]);

  const handleSubmit = async () => {
    if (!stripeRef.current || !cardElementRef.current) return;
    setLoading(true);
    setError('');

    try {
      const { token, error: stripeError } = await stripeRef.current.createToken(cardElementRef.current);
      if (stripeError) {
        setError(stripeError.message);
        setLoading(false);
        return;
      }

      router.post('/payment-methods', {
        payment_method_id: token.id,
        set_as_default: setDefault,
      }, {
        preserveScroll: true,
        onFinish: () => {
          setLoading(false);
          onClose();
        },
      });
    } catch (e) {
      setError('An error occurred while processing your card.');
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="glass-card w-full max-w-lg rounded-2xl p-6 shadow-xl">
        <div className="mb-5 flex items-center justify-between">
          <h3 className="text-[17px] font-bold text-ink">{t('student.payment_methods.add_method')}</h3>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-xl hover:bg-violet-50"><X className="h-5 w-5 text-muted" /></button>
        </div>

        <div className="space-y-5">
          <div>
            <p className="mb-2 text-[13px] font-semibold text-ink">{t('student.payment_methods.card_info')}</p>
            <div ref={cardRef} className="rounded-xl border border-violet-200/60 bg-white p-4" />
            {error && <p className="mt-2 text-[12px] font-medium text-red-600">{error}</p>}
          </div>

          <label className="flex items-center gap-2 text-[13px]">
            <input type="checkbox" checked={setDefault} onChange={(e) => setSetDefault(e.target.checked)} className="rounded" />
            {t('student.payment_methods.set_default_option')}
          </label>

          <div className="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-[12px] text-emerald-700">
            <Shield className="h-4 w-4 shrink-0" strokeWidth={2} />
            {t('student.payment_methods.secure_note')}
          </div>

          <div className="flex justify-end gap-3">
            <button onClick={onClose} disabled={loading} className="rounded-xl border border-violet-200 px-4 py-2.5 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-50">
              {t('student.payment_methods.cancel')}
            </button>
            <button onClick={handleSubmit} disabled={loading} className="rounded-xl hero-gradient px-5 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-violet-300/40 transition-all hover:shadow-lg disabled:opacity-60">
              {loading ? t('student.payment_methods.adding') : t('student.payment_methods.add_method')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Benefits                                                           */
/* ------------------------------------------------------------------ */
function Benefits() {
  const items = [
    { icon: Zap, color: 'bg-violet-100 text-violet-600', label: t('student.payment_methods.faster_payments'), desc: t('student.payment_methods.faster_payments_desc') },
    { icon: Shield, color: 'bg-emerald-100 text-emerald-600', label: t('student.payment_methods.secure_storage'), desc: t('student.payment_methods.secure_storage_desc') },
    { icon: Sparkles, color: 'bg-purple-100 text-purple-600', label: t('student.payment_methods.auto_pay'), desc: t('student.payment_methods.auto_pay_desc') },
  ];

  return (
    <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.15s' }}>
      <h3 className="mb-4 text-[15px] font-bold text-ink">{t('student.payment_methods.benefits_title')}</h3>
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
        {items.map((b, i) => (
          <div key={i} className="text-center">
            <div className={cn('mx-auto mb-2 grid h-12 w-12 place-items-center rounded-xl', b.color)}>
              <b.icon className="h-6 w-6" strokeWidth={1.8} />
            </div>
            <p className="text-[13px] font-semibold text-ink">{b.label}</p>
            <p className="mt-0.5 text-[12px] text-muted">{b.desc}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function PaymentMethods() {
  const { paymentMethods, stripePublishableKey } = usePage().props;
  const [showModal, setShowModal] = useState(false);

  const hero = (
    <PageHeader tag={t('student.payment_methods.title')} title={t('student.payment_methods.title')} subtitle={t('student.payment_methods.manage_desc')} />
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('student.payment_methods.title')} />

      <div className="space-y-5 pt-4">
        {/* Add button */}
        {stripePublishableKey && (
          <div className="fade-up flex justify-end">
            <button
              onClick={() => setShowModal(true)}
              className="flex items-center gap-1.5 rounded-xl hero-gradient px-4 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-violet-300/40 transition-all hover:shadow-lg"
            >
              <Plus className="h-4 w-4" strokeWidth={2.5} />
              {t('student.payment_methods.add_method')}
            </button>
          </div>
        )}

        {/* Card list */}
        {paymentMethods.length > 0 ? (
          <div className="fade-up space-y-3" style={{ animationDelay: '0.05s' }}>
            {paymentMethods.map((m) => <CardRow key={m.id} method={m} />)}
          </div>
        ) : (
          <EmptyState
            icon={CreditCard}
            title={t('student.payment_methods.no_methods')}
            description={t('student.payment_methods.no_methods_desc')}
            action={stripePublishableKey ? (
              <button
                onClick={() => setShowModal(true)}
                className="inline-flex items-center gap-2 rounded-xl hero-gradient px-5 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-violet-300/40"
              >
                <Plus className="h-4 w-4" strokeWidth={2.5} />
                {t('student.payment_methods.add_first')}
              </button>
            ) : null}
          />
        )}

        <Benefits />
      </div>

      <AddCardModal
        open={showModal}
        onClose={() => setShowModal(false)}
        stripeKey={stripePublishableKey}
      />
    </StudentLayout>
  );
}
