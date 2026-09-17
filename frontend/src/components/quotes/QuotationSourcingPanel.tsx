import { FormEvent, useEffect, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import {
  getQuotationSourcing,
  markSupplierQuoteUnderReview,
  rejectSupplierQuote,
  replaceSupplierQuote,
  requestSupplierQuote,
  searchSourcingSuppliers,
  selectSupplierQuote,
  type QuotationSourcingPayload,
  type QuotationSupplierQuote,
} from '../../services/quotationSourcing'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

type Props = {
  quotationId: number
  currency: string
}

export function QuotationSourcingPanel({ quotationId, currency }: Props) {
  const [sourcing, setSourcing] = useState<QuotationSourcingPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [supplierQuery, setSupplierQuery] = useState('')
  const [suppliers, setSuppliers] = useState<Array<{ id: number; name: string }>>([])
  const [selectedSupplierByItem, setSelectedSupplierByItem] = useState<Record<number, string>>({})
  const [replaceFor, setReplaceFor] = useState<number | null>(null)
  const [replaceSupplierId, setReplaceSupplierId] = useState('')

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getQuotationSourcing(quotationId)
      setSourcing(response.data.sourcing)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل بيانات التوريد.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [quotationId])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void searchSourcingSuppliers(supplierQuery)
        .then((response) => setSuppliers(response.data.items))
        .catch(() => setSuppliers([]))
    }, 250)
    return () => window.clearTimeout(timer)
  }, [supplierQuery])

  async function runAction(action: () => Promise<unknown>, successMessage: string) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await action()
      setNotice(successMessage)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ العملية.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleRequest(itemId: number) {
    const supplierId = Number(selectedSupplierByItem[itemId] || 0)
    if (!supplierId) {
      setError('اختر مورداً أولاً.')
      return
    }
    await runAction(
      () => requestSupplierQuote(quotationId, itemId, { supplier_id: supplierId }),
      'تم طلب عرض السعر من المورد.',
    )
  }

  async function handleReplace(quote: QuotationSupplierQuote) {
    const supplierId = Number(replaceSupplierId || 0)
    if (!supplierId) {
      setError('اختر المورد البديل.')
      return
    }
    await runAction(
      () =>
        replaceSupplierQuote(quote.id, {
          supplier_id: supplierId,
          rejection_reason: 'استبدال مورد',
        }),
      'تم استبدال المورد.',
    )
    setReplaceFor(null)
    setReplaceSupplierId('')
  }

  if (loading && !sourcing) {
    return <p className="text-sm text-slate-500">جاري تحميل التوريد...</p>
  }

  if (!sourcing) {
    return <FeedbackBanner kind="error">{error || 'لا توجد بيانات.'}</FeedbackBanner>
  }

  return (
    <div className="space-y-4">
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      <div className="rounded-2xl border border-slate-200 bg-[#F7F5EF] p-4">
        <h3 className="text-sm font-semibold text-[#111318]">هوامش التوريد (داخلي فقط)</h3>
        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-xs text-slate-500">سعر العميل</dt>
            <dd className="font-medium">{formatMoney(Number(sourcing.totals.customer_total), currency)}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">تكلفة المورد المختار</dt>
            <dd className="font-medium">{formatMoney(Number(sourcing.totals.selected_supplier_cost), currency)}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">الهامش الإجمالي</dt>
            <dd className="font-medium text-[#315CFF]">{formatMoney(Number(sourcing.totals.gross_margin), currency)}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">نسبة الهامش</dt>
            <dd className="font-medium">
              {sourcing.totals.margin_percentage != null ? `${sourcing.totals.margin_percentage}%` : '—'}
            </dd>
          </div>
        </dl>
      </div>

      <label className="block max-w-md space-y-1 text-sm">
        <span>بحث الموردين</span>
        <input
          className={fieldClass}
          value={supplierQuery}
          onChange={(event) => setSupplierQuery(event.target.value)}
          placeholder="اسم المورد..."
        />
      </label>

      {sourcing.items.length === 0 ? (
        <p className="text-sm text-slate-500">أضف بنوداً في العرض أولاً ثم اطلب عروض الموردين.</p>
      ) : (
        sourcing.items.map((item) => (
          <article key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
            <header className="mb-3 flex flex-wrap items-start justify-between gap-2">
              <div>
                <h4 className="font-semibold text-[#111318]">{item.description}</h4>
                <p className="text-xs text-slate-500">
                  سعر العميل: {formatMoney(Number(item.customer_price), currency)}
                  {item.margin ? ` · هامش: ${formatMoney(Number(item.margin.gross_margin), currency)}` : ''}
                </p>
              </div>
              <div className="flex flex-wrap items-end gap-2">
                <select
                  className={fieldClass}
                  value={selectedSupplierByItem[item.id] || ''}
                  onChange={(event) =>
                    setSelectedSupplierByItem((prev) => ({ ...prev, [item.id]: event.target.value }))
                  }
                >
                  <option value="">اختر مورداً لطلب عرض</option>
                  {suppliers.map((supplier) => (
                    <option key={supplier.id} value={supplier.id}>
                      {supplier.name}
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  disabled={busy}
                  className="rounded-lg bg-[#315CFF] px-3 py-2 text-sm text-white disabled:opacity-50"
                  onClick={() => void handleRequest(item.id)}
                >
                  طلب عرض
                </button>
              </div>
            </header>

            {item.supplier_options.length === 0 ? (
              <p className="text-sm text-slate-500">لا توجد خيارات موردين لهذا البند بعد.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-right text-xs text-slate-500">
                      <th className="px-2 py-2 font-medium">المورد</th>
                      <th className="px-2 py-2 font-medium">التكلفة</th>
                      <th className="px-2 py-2 font-medium">التسليم</th>
                      <th className="px-2 py-2 font-medium">الحالة</th>
                      <th className="px-2 py-2 font-medium">الهامش</th>
                      <th className="px-2 py-2 font-medium">إجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    {item.supplier_options.map((option) => (
                      <tr key={option.id} className="border-b border-slate-100 align-top">
                        <td className="px-2 py-2 font-medium text-[#111318]">{option.supplier?.name || '—'}</td>
                        <td className="px-2 py-2" dir="ltr">
                          {option.cost != null ? formatMoney(Number(option.cost), option.currency || currency) : '—'}
                        </td>
                        <td className="px-2 py-2">{option.delivery_days != null ? `${option.delivery_days} يوم` : '—'}</td>
                        <td className="px-2 py-2">{option.status_label_ar || option.status}</td>
                        <td className="px-2 py-2">
                          {option.margin
                            ? `${formatMoney(Number(option.margin.gross_margin), currency)} (${option.margin.margin_percentage ?? '—'}%)`
                            : '—'}
                        </td>
                        <td className="px-2 py-2">
                          <div className="flex flex-wrap gap-1">
                            {option.status === 'RECEIVED' ? (
                              <button
                                type="button"
                                disabled={busy}
                                className="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-50"
                                onClick={() =>
                                  void runAction(
                                    () => markSupplierQuoteUnderReview(option.id),
                                    'تم نقل العرض للمراجعة.',
                                  )
                                }
                              >
                                مراجعة
                              </button>
                            ) : null}
                            {['REQUESTED', 'RECEIVED', 'UNDER_REVIEW'].includes(String(option.status)) ? (
                              <button
                                type="button"
                                disabled={busy || option.cost == null}
                                className="rounded bg-emerald-600 px-2 py-1 text-xs text-white disabled:opacity-50"
                                onClick={() =>
                                  void runAction(() => selectSupplierQuote(option.id), 'تم اختيار المورد.')
                                }
                              >
                                اختيار
                              </button>
                            ) : null}
                            {option.status !== 'REJECTED' ? (
                              <button
                                type="button"
                                disabled={busy}
                                className="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 disabled:opacity-50"
                                onClick={() =>
                                  void runAction(() => rejectSupplierQuote(option.id, 'مرفوض'), 'تم الرفض.')
                                }
                              >
                                رفض
                              </button>
                            ) : null}
                            <button
                              type="button"
                              disabled={busy}
                              className="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-50"
                              onClick={() => {
                                setReplaceFor(option.id)
                                setReplaceSupplierId('')
                              }}
                            >
                              استبدال
                            </button>
                          </div>
                          {option.notes ? (
                            <p className="mt-1 text-xs text-slate-500">ملاحظات: {option.notes}</p>
                          ) : null}
                          {replaceFor === option.id ? (
                            <form
                              className="mt-2 flex flex-wrap items-end gap-2"
                              onSubmit={(event: FormEvent) => {
                                event.preventDefault()
                                void handleReplace(option)
                              }}
                            >
                              <select
                                className={fieldClass}
                                value={replaceSupplierId}
                                onChange={(event) => setReplaceSupplierId(event.target.value)}
                                required
                              >
                                <option value="">مورد بديل</option>
                                {suppliers.map((supplier) => (
                                  <option key={supplier.id} value={supplier.id}>
                                    {supplier.name}
                                  </option>
                                ))}
                              </select>
                              <button
                                type="submit"
                                disabled={busy}
                                className="rounded-lg bg-[#111318] px-3 py-2 text-xs text-white disabled:opacity-50"
                              >
                                تأكيد الاستبدال
                              </button>
                            </form>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </article>
        ))
      )}
    </div>
  )
}
