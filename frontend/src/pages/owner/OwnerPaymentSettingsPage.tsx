import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { getOwnerPaymentSettings, updateOwnerPaymentSettings } from '../../services/payments'
import type { OwnerPaymentSettings } from '../../types/api'
import { describeApiError } from '../../utils/errors'

const EMPTY_VALUE = 'غير مضاف بعد'

type TextFieldProps = {
  label: string
  value: string | null
  onChange: (value: string) => void
  ltr?: boolean
  multiline?: boolean
}

function TextField({ label, value, onChange, ltr = false, multiline = false }: TextFieldProps) {
  return (
    <label className="block space-y-1 text-sm">
      <span>{label}</span>
      {multiline ? (
        <textarea
          value={value ?? ''}
          onChange={(event) => onChange(event.target.value)}
          className="min-h-24 w-full rounded-xl border border-slate-300 px-3 py-2"
        />
      ) : (
        <input
          dir={ltr ? 'ltr' : undefined}
          value={value ?? ''}
          onChange={(event) => onChange(event.target.value)}
          placeholder={EMPTY_VALUE}
          className="w-full rounded-xl border border-slate-300 px-3 py-2"
        />
      )}
    </label>
  )
}

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

  async function save(event: FormEvent) {
    event.preventDefault()

    if (!settings || busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const response = await updateOwnerPaymentSettings({
        card_enabled: settings.card_enabled,
        instapay_enabled: settings.instapay_enabled,
        instapay_account_name: settings.instapay_account_name,
        instapay_bank_name: settings.instapay_bank_name,
        instapay_account_number: settings.instapay_account_number,
        instapay_handle: settings.instapay_handle,
        instapay_phone: settings.instapay_phone,
        instapay_instructions: settings.instapay_instructions,
        instapay_notes: settings.instapay_notes,
        bank_transfer_enabled: settings.bank_transfer_enabled,
        bank_name: settings.bank_name,
        bank_account_name: settings.bank_account_name,
        bank_account_number: settings.bank_account_number,
        bank_iban: settings.bank_iban,
        bank_swift: settings.bank_swift,
        bank_branch: settings.bank_branch,
        bank_instructions: settings.bank_instructions,
        bank_notes: settings.bank_notes,
      })
      setSettings(response.data)
      toast.success('تم حفظ إعدادات الدفع.')
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
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">إعدادات الدفع</h1>
        <p className="text-sm text-slate-600">
          بيانات الحسابات الظاهرة للعملاء عند الدفع اليدوي. مفاتيح PayTabs تبقى في بيئة الخادم فقط ولا تُعرض أو تُحرَّر هنا.
        </p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <article className="space-y-2 rounded-2xl border border-slate-200 bg-white p-5">
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
      </article>

      <form className="space-y-6" onSubmit={(event) => void save(event)}>
        <fieldset className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
          <legend className="px-1 font-semibold">التحويل البنكي</legend>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.bank_transfer_enabled}
              onChange={(event) => patch({ bank_transfer_enabled: event.target.checked })}
            />
            تفعيل التحويل البنكي
          </label>
          {settings.bank_transfer_enabled && !settings.bank_transfer_ready ? (
            <FeedbackBanner kind="warning">
              أكمل اسم البنك واسم صاحب الحساب ورقم الحساب أو IBAN وتعليمات الدفع حتى يظهر الخيار للعملاء.
            </FeedbackBanner>
          ) : null}
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              label="اسم البنك"
              value={settings.bank_name}
              onChange={(value) => patch({ bank_name: value })}
            />
            <TextField
              label="اسم صاحب الحساب"
              value={settings.bank_account_name}
              onChange={(value) => patch({ bank_account_name: value })}
            />
            <TextField
              label="رقم الحساب"
              value={settings.bank_account_number}
              onChange={(value) => patch({ bank_account_number: value })}
              ltr
            />
            <TextField
              label="IBAN"
              value={settings.bank_iban}
              onChange={(value) => patch({ bank_iban: value })}
              ltr
            />
            <TextField
              label="SWIFT/BIC"
              value={settings.bank_swift}
              onChange={(value) => patch({ bank_swift: value })}
              ltr
            />
            <TextField
              label="الفرع"
              value={settings.bank_branch}
              onChange={(value) => patch({ bank_branch: value })}
            />
          </div>
          <TextField
            label="تعليمات الدفع"
            value={settings.bank_instructions}
            onChange={(value) => patch({ bank_instructions: value })}
            multiline
          />
          <TextField
            label="ملاحظات إضافية"
            value={settings.bank_notes}
            onChange={(value) => patch({ bank_notes: value })}
            multiline
          />
        </fieldset>

        <fieldset className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
          <legend className="px-1 font-semibold">إنستاباي</legend>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.instapay_enabled}
              onChange={(event) => patch({ instapay_enabled: event.target.checked })}
            />
            تفعيل إنستاباي
          </label>
          {settings.instapay_enabled && !settings.instapay_ready ? (
            <FeedbackBanner kind="warning">
              أكمل اسم الحساب ورقم الحساب أو معرّف إنستاباي وتعليمات الدفع حتى يظهر الخيار للعملاء.
            </FeedbackBanner>
          ) : null}
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              label="اسم الحساب"
              value={settings.instapay_account_name}
              onChange={(value) => patch({ instapay_account_name: value })}
            />
            <TextField
              label="البنك"
              value={settings.instapay_bank_name}
              onChange={(value) => patch({ instapay_bank_name: value })}
            />
            <TextField
              label="رقم الحساب / IBAN"
              value={settings.instapay_account_number}
              onChange={(value) => patch({ instapay_account_number: value })}
              ltr
            />
            <TextField
              label="رقم/معرف إنستاباي"
              value={settings.instapay_handle}
              onChange={(value) => patch({ instapay_handle: value })}
              ltr
            />
            <TextField
              label="رقم الهاتف المرتبط"
              value={settings.instapay_phone}
              onChange={(value) => patch({ instapay_phone: value })}
              ltr
            />
          </div>
          <TextField
            label="تعليمات الدفع"
            value={settings.instapay_instructions}
            onChange={(value) => patch({ instapay_instructions: value })}
            multiline
          />
          <TextField
            label="ملاحظات إضافية"
            value={settings.instapay_notes}
            onChange={(value) => patch({ instapay_notes: value })}
            multiline
          />
        </fieldset>

        <button
          type="submit"
          disabled={busy}
          className="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-50"
        >
          {busy ? 'جاري الحفظ...' : 'حفظ الإعدادات'}
        </button>
      </form>

      <Link to="/owner/payments" className="inline-block text-sm underline">
        العودة إلى المدفوعات
      </Link>
    </section>
  )
}
