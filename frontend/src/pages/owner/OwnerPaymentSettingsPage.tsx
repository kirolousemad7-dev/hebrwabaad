import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { OwnerPaymentAccountsPanel } from '../../components/owner/OwnerPaymentAccountsPanel'
import { OwnerPageHeader, ownerCardClass, ownerPrimaryButtonClass, ownerSecondaryButtonClass } from '../../components/owner/ownerChrome'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { getOwnerPaymentSettings, updateOwnerPaymentSettings } from '../../services/payments'
import type { OwnerPaymentSettings } from '../../types/api'
import { describeApiError } from '../../utils/errors'

export function OwnerPaymentSettingsPage() {
  const toast = useToast()
  const [settings, setSettings] = useState<OwnerPaymentSettings | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getOwnerPaymentSettings()
      setSettings(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل إعدادات الدفع.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function saveCard() {
    if (!settings || busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const response = await updateOwnerPaymentSettings({
        card_enabled: settings.card_enabled,
      })
      setSettings(response.data)
      toast.success('تم حفظ إعداد البطاقة.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الإعدادات.'))
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <CatalogSkeleton variant="list" label="جاري تحميل إعدادات الدفع..." />
  }

  if (error && !settings) {
    return <CatalogErrorState message={error} onRetry={() => void load()} />
  }

  if (!settings) {
    return null
  }

  function patch(changes: Partial<OwnerPaymentSettings>) {
    setSettings((current) => (current === null ? current : { ...current, ...changes }))
  }

  return (
    <section className="space-y-6">
      <OwnerPageHeader
        title="إعدادات الدفع"
        description="حسابات الاستلام التي يراها العملاء عند إنستاباي أو التحويل البنكي. مفاتيح PayTabs تبقى في بيئة الخادم فقط."
        action={
          <Link to="/owner/payments" className={ownerSecondaryButtonClass}>
            المدفوعات
          </Link>
        }
      />

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <article className={`${ownerCardClass} space-y-2 p-5`}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-semibold">الدفع بالبطاقة</h2>
          <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
            {settings.card_provider} · {settings.card_environment}
          </span>
        </div>
        <FeedbackBanner kind={settings.card_configured ? 'success' : 'warning'}>
          {settings.card_configured
            ? 'بوابة البطاقة مهيأة من متغيرات بيئة الخادم.'
            : 'بوابة البطاقة غير مهيأة. أضف PAYTABS_PROFILE_ID وPAYTABS_SERVER_KEY وPAYTABS_BASE_URL في بيئة الخادم فقط.'}
        </FeedbackBanner>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={settings.card_enabled}
            onChange={(event) => patch({ card_enabled: event.target.checked })}
          />
          تفعيل الدفع بالبطاقة
        </label>
        <button type="button" className={ownerPrimaryButtonClass} disabled={busy} onClick={() => void saveCard()}>
          {busy ? 'جاري الحفظ...' : 'حفظ إعداد البطاقة'}
        </button>
      </article>

      <OwnerPaymentAccountsPanel />
    </section>
  )
}
