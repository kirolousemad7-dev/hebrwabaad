import { FormEvent, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createOwnerInvoice,
  getOwnerInvoice,
  updateOwnerInvoice,
  type InvoiceItem,
} from '../../services/invoices'
import { describeApiError } from '../../utils/errors'

type LineDraft = {
  description: string
  quantity: string
  unit_price: string
  discount_amount: string
  tax_amount: string
}

const emptyLine = (): LineDraft => ({
  description: '',
  quantity: '1',
  unit_price: '0',
  discount_amount: '0',
  tax_amount: '0',
})

export function OwnerInvoiceEditorPage() {
  const { invoiceId } = useParams()
  const isEdit = Boolean(invoiceId)
  const navigate = useNavigate()
  const [customerId, setCustomerId] = useState('')
  const [dueDate, setDueDate] = useState('')
  const [notes, setNotes] = useState('')
  const [internalNotes, setInternalNotes] = useState('')
  const [terms, setTerms] = useState('')
  const [items, setItems] = useState<LineDraft[]>([emptyLine()])
  const [loading, setLoading] = useState(isEdit)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!invoiceId) return
    void (async () => {
      setLoading(true)
      try {
        const response = await getOwnerInvoice(invoiceId)
        const invoice = response.data
        setCustomerId(String(invoice.customer?.id ?? ''))
        setDueDate(invoice.due_date ?? '')
        setNotes(invoice.notes ?? '')
        setInternalNotes(invoice.internal_notes ?? '')
        setTerms(invoice.terms ?? '')
        setItems(
          invoice.items.map((item: InvoiceItem) => ({
            description: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount_amount: item.discount_amount ?? '0',
            tax_amount: item.tax_amount ?? '0',
          })),
        )
      } catch (caught) {
        setError(describeApiError(caught, 'تعذر تحميل الفاتورة.'))
      } finally {
        setLoading(false)
      }
    })()
  }, [invoiceId])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    const body = {
      customer_id: Number(customerId),
      due_date: dueDate || undefined,
      notes: notes || null,
      internal_notes: internalNotes || null,
      terms: terms || null,
      items: items.map((item) => ({
        description: item.description,
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
        discount_amount: Number(item.discount_amount || 0),
        tax_amount: Number(item.tax_amount || 0),
      })),
    }
    try {
      const response = isEdit && invoiceId
        ? await updateOwnerInvoice(invoiceId, body)
        : await createOwnerInvoice(body)
      navigate(`/owner/invoices/${response.data.id}`)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الفاتورة.'))
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <DashboardPanelSkeleton label="جاري التحميل" />

  return (
    <section className="space-y-6">
      <header>
        <p className="text-sm text-slate-500">
          <Link to="/owner/invoices" className="underline">
            الفواتير
          </Link>
        </p>
        <h1 className="text-2xl font-semibold">{isEdit ? 'تعديل مسودة' : 'فاتورة جديدة'}</h1>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <form onSubmit={onSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
        <div className="grid gap-3 md:grid-cols-2">
          <label className="text-sm">
            معرف العميل
            <input className="mt-1 w-full rounded-xl border px-3 py-2" value={customerId} onChange={(e) => setCustomerId(e.target.value)} required />
          </label>
          <label className="text-sm">
            تاريخ الاستحقاق
            <input type="date" className="mt-1 w-full rounded-xl border px-3 py-2" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
          </label>
        </div>
        <label className="block text-sm">
          ملاحظات العميل
          <textarea className="mt-1 w-full rounded-xl border px-3 py-2" value={notes} onChange={(e) => setNotes(e.target.value)} />
        </label>
        <label className="block text-sm">
          ملاحظات داخلية
          <textarea className="mt-1 w-full rounded-xl border px-3 py-2" value={internalNotes} onChange={(e) => setInternalNotes(e.target.value)} />
        </label>
        <label className="block text-sm">
          الشروط
          <textarea className="mt-1 w-full rounded-xl border px-3 py-2" value={terms} onChange={(e) => setTerms(e.target.value)} />
        </label>

        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold">البنود</h2>
            <button type="button" className="text-sm underline" onClick={() => setItems((rows) => [...rows, emptyLine()])}>
              إضافة بند
            </button>
          </div>
          {items.map((item, index) => (
            <div key={index} className="grid gap-2 rounded-xl border border-slate-100 p-3 md:grid-cols-5">
              <input className="rounded-lg border px-2 py-1 text-sm md:col-span-2" placeholder="الوصف" value={item.description} onChange={(e) => setItems((rows) => rows.map((row, i) => (i === index ? { ...row, description: e.target.value } : row)))} required />
              <input className="rounded-lg border px-2 py-1 text-sm" placeholder="الكمية" value={item.quantity} onChange={(e) => setItems((rows) => rows.map((row, i) => (i === index ? { ...row, quantity: e.target.value } : row)))} required />
              <input className="rounded-lg border px-2 py-1 text-sm" placeholder="السعر" value={item.unit_price} onChange={(e) => setItems((rows) => rows.map((row, i) => (i === index ? { ...row, unit_price: e.target.value } : row)))} required />
              <button type="button" className="text-sm text-rose-700" onClick={() => setItems((rows) => rows.filter((_, i) => i !== index))}>
                حذف
              </button>
            </div>
          ))}
        </div>

        <button type="submit" disabled={busy} className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">
          حفظ
        </button>
      </form>
    </section>
  )
}
