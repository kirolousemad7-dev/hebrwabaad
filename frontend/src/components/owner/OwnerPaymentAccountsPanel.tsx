import { FormEvent, useEffect, useState } from 'react'
import { CatalogSkeleton } from '../catalog/CatalogStatus'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import {
  archiveOwnerPaymentAccount,
  createOwnerPaymentAccount,
  getOwnerPaymentAccounts,
  makeOwnerPaymentAccountDefault,
  updateOwnerPaymentAccount,
} from '../../services/payments'
import type { OwnerPaymentAccount } from '../../types/api'
import { describeApiError } from '../../utils/errors'
import {
  ownerCardClass,
  ownerDangerButtonClass,
  ownerInputClass,
  ownerPrimaryButtonClass,
  ownerSecondaryButtonClass,
  ownerTableHeadClass,
  ownerTableWrapClass,
} from './ownerChrome'

type Method = 'INSTAPAY' | 'BANK_TRANSFER'

type Draft = {
  method: Method
  account_holder_name: string
  instapay_address: string
  mobile_number: string
  bank_name: string
  account_number: string
  iban: string
  branch_name: string
  instructions: string
  is_active: boolean
  is_default: boolean
}

const EMPTY: Draft = {
  method: 'INSTAPAY',
  account_holder_name: '',
  instapay_address: '',
  mobile_number: '',
  bank_name: '',
  account_number: '',
  iban: '',
  branch_name: '',
  instructions: '',
  is_active: true,
  is_default: false,
}

function methodLabel(method: string): string {
  return method === 'BANK_TRANSFER' ? 'تحويل بنكي' : 'إنستاباي'
}

export function OwnerPaymentAccountsPanel() {
  const toast = useToast()
  const [accounts, setAccounts] = useState<OwnerPaymentAccount[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [draft, setDraft] = useState<Draft>(EMPTY)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerPaymentAccounts()
      setAccounts(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل حسابات الاستلام.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  function patch(changes: Partial<Draft>) {
    setDraft((current) => ({ ...current, ...changes }))
  }

  function beginEdit(account: OwnerPaymentAccount) {
    setEditingId(account.id)
    setDraft({
      method: account.method === 'BANK_TRANSFER' ? 'BANK_TRANSFER' : 'INSTAPAY',
      account_holder_name: account.account_holder_name,
      instapay_address: account.instapay_address ?? '',
      mobile_number: account.mobile_number ?? '',
      bank_name: account.bank_name ?? '',
      account_number: account.account_number ?? '',
      iban: account.iban ?? '',
      branch_name: account.branch_name ?? '',
      instructions: account.instructions,
      is_active: account.is_active,
      is_default: account.is_default,
    })
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setError(null)
    const payload = {
      account_holder_name: draft.account_holder_name.trim(),
      instapay_address: draft.instapay_address.trim() || null,
      mobile_number: draft.mobile_number.trim() || null,
      bank_name: draft.bank_name.trim() || null,
      account_number: draft.account_number.trim() || null,
      iban: draft.iban.trim() || null,
      branch_name: draft.branch_name.trim() || null,
      instructions: draft.instructions.trim(),
      is_active: draft.is_active,
      is_default: draft.is_default,
    }
    try {
      if (editingId === null) {
        await createOwnerPaymentAccount({ method: draft.method, ...payload })
        toast.success('تمت إضافة حساب الاستلام.')
      } else {
        await updateOwnerPaymentAccount(editingId, payload)
        toast.success('تم تحديث حساب الاستلام.')
      }
      setEditingId(null)
      setDraft(EMPTY)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الحساب.'))
    } finally {
      setBusy(false)
    }
  }

  async function run(action: () => Promise<unknown>, success: string) {
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      await action()
      toast.success(success)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ الإجراء.'))
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <CatalogSkeleton variant="list" label="جاري تحميل حسابات الاستلام..." />
  }

  return (
    <div className="space-y-4">
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {accounts.length === 0 ? (
        <p className={`${ownerCardClass} p-5 text-sm text-slate-600`}>لا توجد حسابات استلام بعد. أضف حساب إنستاباي أو تحويلاً بنكياً.</p>
      ) : (
        <div className={ownerTableWrapClass}>
          <table className="min-w-full text-sm">
            <thead className={ownerTableHeadClass}>
              <tr>
                <th className="px-3 py-2">الطريقة</th>
                <th className="px-3 py-2">صاحب الحساب</th>
                <th className="px-3 py-2">المعرّف</th>
                <th className="px-3 py-2">الحالة</th>
                <th className="px-3 py-2">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              {accounts.map((account) => (
                <tr key={account.id} className="border-b border-slate-100 align-top">
                  <td className="px-3 py-3">
                    {methodLabel(account.method)}
                    {account.is_default ? <span className="mt-1 block text-xs text-brand-cobalt-700">افتراضي</span> : null}
                  </td>
                  <td className="px-3 py-3">{account.account_holder_name}</td>
                  <td className="px-3 py-3" dir="ltr">
                    {account.method === 'INSTAPAY' ? account.instapay_address : account.iban || account.account_number}
                  </td>
                  <td className="px-3 py-3">
                    {account.archived_at ? 'مؤرشف' : account.is_active ? 'نشط' : 'متوقف'}
                    <span className="mt-1 block text-xs text-slate-500">{account.payments_count.toLocaleString('ar-SA')} دفعة</span>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex flex-wrap gap-2">
                      <button type="button" className={ownerSecondaryButtonClass} disabled={busy} onClick={() => beginEdit(account)}>
                        تعديل
                      </button>
                      {!account.archived_at ? (
                        <button
                          type="button"
                          className={ownerSecondaryButtonClass}
                          disabled={busy}
                          onClick={() => void run(() => updateOwnerPaymentAccount(account.id, { is_active: !account.is_active }), account.is_active ? 'تم إيقاف الحساب.' : 'تم تفعيل الحساب.')}
                        >
                          {account.is_active ? 'إيقاف' : 'تفعيل'}
                        </button>
                      ) : null}
                      {!account.is_default && !account.archived_at ? (
                        <button
                          type="button"
                          className={ownerSecondaryButtonClass}
                          disabled={busy}
                          onClick={() => void run(() => makeOwnerPaymentAccountDefault(account.id), 'تم تعيين الحساب الافتراضي.')}
                        >
                          افتراضي
                        </button>
                      ) : null}
                      {!account.archived_at ? (
                        <button
                          type="button"
                          className={ownerDangerButtonClass}
                          disabled={busy}
                          onClick={() => void run(() => archiveOwnerPaymentAccount(account.id), 'تمت أرشفة الحساب مع الإبقاء على سجل الدفعات.')}
                        >
                          أرشفة
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <form className={`${ownerCardClass} space-y-4 p-5`} onSubmit={(event) => void save(event)}>
        <h2 className="font-semibold">{editingId === null ? 'إضافة حساب استلام' : 'تعديل حساب الاستلام'}</h2>
        {editingId === null ? (
          <label className="block space-y-1 text-sm">
            <span>طريقة الاستلام</span>
            <select className={ownerInputClass} value={draft.method} onChange={(event) => patch({ method: event.target.value === 'BANK_TRANSFER' ? 'BANK_TRANSFER' : 'INSTAPAY' })}>
              <option value="INSTAPAY">إنستاباي</option>
              <option value="BANK_TRANSFER">تحويل بنكي</option>
            </select>
          </label>
        ) : (
          <p className="text-sm text-slate-600">الطريقة: {methodLabel(draft.method)}</p>
        )}
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block space-y-1 text-sm">
            <span>اسم صاحب الحساب</span>
            <input required className={ownerInputClass} value={draft.account_holder_name} onChange={(event) => patch({ account_holder_name: event.target.value })} />
          </label>
          {draft.method === 'INSTAPAY' ? (
            <>
              <label className="block space-y-1 text-sm">
                <span>عنوان إنستاباي (IPA)</span>
                <input required dir="ltr" className={ownerInputClass} value={draft.instapay_address} onChange={(event) => patch({ instapay_address: event.target.value })} />
              </label>
              <label className="block space-y-1 text-sm">
                <span>رقم الجوال (اختياري)</span>
                <input dir="ltr" className={ownerInputClass} value={draft.mobile_number} onChange={(event) => patch({ mobile_number: event.target.value })} />
              </label>
            </>
          ) : (
            <>
              <label className="block space-y-1 text-sm">
                <span>اسم البنك</span>
                <input required className={ownerInputClass} value={draft.bank_name} onChange={(event) => patch({ bank_name: event.target.value })} />
              </label>
              <label className="block space-y-1 text-sm">
                <span>رقم الحساب</span>
                <input dir="ltr" className={ownerInputClass} value={draft.account_number} onChange={(event) => patch({ account_number: event.target.value })} />
              </label>
              <label className="block space-y-1 text-sm">
                <span>IBAN</span>
                <input dir="ltr" className={ownerInputClass} value={draft.iban} onChange={(event) => patch({ iban: event.target.value })} />
              </label>
              <label className="block space-y-1 text-sm">
                <span>الفرع (اختياري)</span>
                <input className={ownerInputClass} value={draft.branch_name} onChange={(event) => patch({ branch_name: event.target.value })} />
              </label>
            </>
          )}
        </div>
        <label className="block space-y-1 text-sm">
          <span>تعليمات الدفع</span>
          <textarea required className={`${ownerInputClass} min-h-24`} value={draft.instructions} onChange={(event) => patch({ instructions: event.target.value })} />
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={draft.is_active} onChange={(event) => patch({ is_active: event.target.checked })} />
          الحساب نشط ويظهر للعملاء
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={draft.is_default} onChange={(event) => patch({ is_default: event.target.checked })} />
          حساب الاستلام الافتراضي لهذه الطريقة
        </label>
        <div className="flex flex-wrap gap-2">
          <button type="submit" className={ownerPrimaryButtonClass} disabled={busy}>
            {busy ? 'جاري الحفظ...' : editingId === null ? 'إضافة الحساب' : 'حفظ التعديل'}
          </button>
          {editingId !== null ? (
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              onClick={() => {
                setEditingId(null)
                setDraft(EMPTY)
              }}
            >
              إلغاء التعديل
            </button>
          ) : null}
        </div>
      </form>
    </div>
  )
}
