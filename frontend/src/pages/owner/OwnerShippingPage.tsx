import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  OwnerPageHeader,
  ownerCardClass,
  ownerInputClass,
  ownerPrimaryButtonClass,
  ownerSecondaryButtonClass,
  ownerTableHeadClass,
  ownerTableWrapClass,
} from '../../components/owner/ownerChrome'
import { ApiRequestError } from '../../services/api'
import {
  createShipment,
  createShippingOrder,
  getShippingDetail,
  getShippingLabel,
  getShippingQueue,
  quoteShipment,
  retryShipment,
  syncShipment,
  type OwnerShipment,
  type ShippingDetail,
  type ShippingIntegration,
  type ShippingQueueItem,
  type ShippingRecipient,
} from '../../services/shipping'
import { formatPaymentAmount } from '../../utils/payments'

const emptyRecipient: ShippingRecipient = {
  recipient_name: '',
  recipient_phone: '',
  address: '',
  city: '',
  country: 'SA',
  weight_kg: 1,
}

const views = [
  { id: 'awaiting', label: 'بانتظار الشحن' },
  { id: 'errors', label: 'أخطاء المزامنة' },
  { id: 'shipped', label: 'شحنات قائمة' },
  { id: 'all', label: 'الكل' },
] as const

export function OwnerShippingPage() {
  const [params, setParams] = useSearchParams()
  const focus = Number(params.get('order') || 0)
  const [view, setView] = useState<(typeof views)[number]['id']>('awaiting')
  const [items, setItems] = useState<ShippingQueueItem[]>([])
  const [integration, setIntegration] = useState<ShippingIntegration | null>(null)
  const [detail, setDetail] = useState<ShippingDetail | null>(null)
  const [recipient, setRecipient] = useState<ShippingRecipient>(emptyRecipient)
  const [optionId, setOptionId] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const loadQueue = useCallback(async (nextView: (typeof views)[number]['id']) => {
    const response = await getShippingQueue(nextView)
    setItems(response.data.items)
    setIntegration(response.data.integration)
  }, [])

  const loadDetail = useCallback(async (orderId: number) => {
    const response = await getShippingDetail(orderId)
    setDetail(response.data)
    setIntegration(response.data.integration)
    const shipment = response.data.shipment
    setRecipient({
      recipient_name: shipment?.recipient_name ?? '',
      recipient_phone: shipment?.recipient_phone ?? '',
      address: shipment?.address ?? '',
      city: shipment?.city ?? '',
      country: shipment?.country ?? 'SA',
      weight_kg: 1,
    })
    setOptionId(shipment?.delivery_option_id ?? shipment?.quote_options[0]?.delivery_option_id ?? '')
  }, [])

  useEffect(() => {
    let active = true
    setLoading(true)
    setError(null)
    void (async () => {
      try {
        await loadQueue(view)
        if (active && Number.isInteger(focus) && focus > 0) {
          await loadDetail(focus)
        }
      } catch (caught) {
        if (active) {
          setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تحميل الشحن.')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    })()

    return () => {
      active = false
    }
  }, [view, focus, loadQueue, loadDetail])

  function selectOrder(orderId: number) {
    setParams({ order: String(orderId) })
  }

  async function run(action: string, work: () => Promise<void>) {
    if (!detail) {
      return
    }
    setBusy(action)
    setError(null)
    try {
      await work()
      await loadDetail(detail.order.id)
      await loadQueue(view)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تنفيذ عملية الشحن.')
    } finally {
      setBusy(null)
    }
  }

  const shipment = detail?.shipment ?? null
  const enabled = integration?.enabled === true
  const blocked = Boolean(detail?.block_reason) || !enabled

  return (
    <section className="space-y-6">
      <OwnerPageHeader
        title="الشحن"
        description="إنشاء شحنات OTO للطلبات المدفوع ثمنها، مع التتبع وبوليصة الشحن. لا يُشحن طلب تحويله لم يُراجع."
      />

      {integration ? (
        <p className={`${ownerCardClass} px-4 py-3 text-sm text-slate-700`}>
          {integration.enabled
            ? integration.sandbox
              ? 'البيئة التجريبية مفعّلة. لا تُنشأ شحنة حقيقية إلا على حساب التجربة.'
              : 'الربط مع OTO مفعّل على بيئة الإنتاج.'
            : 'تكامل الشحن متوقف. لن يُرسل أي طلب إلى OTO.'}
          {integration.enabled && !integration.configured
            ? ' رمز الربط أو موقع الاستلام غير مكتمل على الخادم.'
            : ''}
        </p>
      ) : null}

      {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p> : null}

      <div className="flex flex-wrap gap-2">
        {views.map((item) => (
          <button
            key={item.id}
            type="button"
            className={view === item.id ? ownerPrimaryButtonClass : ownerSecondaryButtonClass}
            onClick={() => setView(item.id)}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <p className="text-sm text-slate-500">جاري تحميل الشحن...</p> : null}

      {!loading && items.length === 0 ? (
        <p className={`${ownerCardClass} px-4 py-8 text-center text-sm text-slate-600`}>لا توجد طلبات في هذا العرض.</p>
      ) : null}

      {items.length > 0 ? (
        <div className={ownerTableWrapClass}>
          <table className="min-w-full text-sm">
            <thead className={ownerTableHeadClass}>
              <tr>
                <th className="px-3 py-2 font-medium">الطلب</th>
                <th className="px-3 py-2 font-medium">العميل</th>
                <th className="px-3 py-2 font-medium">الحالة</th>
                <th className="px-3 py-2 font-medium">التتبع</th>
                <th className="px-3 py-2 font-medium">التكلفة</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t border-slate-200">
                  <td className="px-3 py-3">
                    <button type="button" className="text-start text-brand-cobalt-700 underline" onClick={() => selectOrder(item.id)}>
                      {item.reference}
                    </button>
                    <p className="text-xs text-slate-500">{item.title}</p>
                  </td>
                  <td className="px-3 py-3">{item.customer_name ?? '—'}</td>
                  <td className="px-3 py-3">{item.shipment?.status_label ?? 'بانتظار الشحن'}</td>
                  <td className="px-3 py-3" dir="ltr">
                    {item.shipment?.tracking_number ?? '—'}
                  </td>
                  <td className="px-3 py-3">
                    {formatPaymentAmount(item.shipment?.shipping_cost, item.shipment?.currency ?? 'SAR')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {detail ? (
        <article className={`${ownerCardClass} space-y-4 p-4 sm:p-5`}>
          <header className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-slate-900">{detail.order.title}</h2>
              <p className="text-xs text-slate-500" dir="ltr">
                {detail.order.reference}
              </p>
              <p className="text-sm text-slate-600">{detail.order.customer_name ?? '—'}</p>
            </div>
            <Link to={`/owner/orders/${detail.order.id}`} className={ownerSecondaryButtonClass}>
              صفحة الطلب
            </Link>
          </header>

          {detail.block_reason ? (
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{detail.block_reason}</p>
          ) : null}

          <ShipmentFacts shipment={shipment} />

          <div className="grid gap-3 sm:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">اسم المستلم</span>
              <input
                className={ownerInputClass}
                value={recipient.recipient_name}
                onChange={(event) => setRecipient({ ...recipient, recipient_name: event.target.value })}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">الجوال</span>
              <input
                className={ownerInputClass}
                dir="ltr"
                value={recipient.recipient_phone}
                onChange={(event) => setRecipient({ ...recipient, recipient_phone: event.target.value })}
              />
            </label>
            <label className="space-y-1 text-sm sm:col-span-2">
              <span className="text-slate-600">العنوان</span>
              <input
                className={ownerInputClass}
                value={recipient.address}
                onChange={(event) => setRecipient({ ...recipient, address: event.target.value })}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">المدينة</span>
              <input
                className={ownerInputClass}
                value={recipient.city}
                onChange={(event) => setRecipient({ ...recipient, city: event.target.value })}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">الدولة</span>
              <input
                className={ownerInputClass}
                dir="ltr"
                maxLength={2}
                value={recipient.country}
                onChange={(event) => setRecipient({ ...recipient, country: event.target.value.toUpperCase() })}
              />
            </label>
          </div>

          {shipment && shipment.quote_options.length > 0 ? (
            <fieldset className="space-y-2">
              <legend className="text-sm font-medium text-slate-800">شركة الشحن والتكلفة</legend>
              {shipment.quote_options.map((option) => (
                <label key={option.delivery_option_id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                  <span className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="delivery_option"
                      checked={optionId === option.delivery_option_id}
                      onChange={() => setOptionId(option.delivery_option_id)}
                    />
                    {option.carrier || option.name}
                    {option.delivery_time ? <span className="text-slate-500">{option.delivery_time}</span> : null}
                  </span>
                  <span>{formatPaymentAmount(option.price, option.currency)}</span>
                </label>
              ))}
            </fieldset>
          ) : null}

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              disabled={blocked || busy !== null}
              onClick={() => void run('quote', async () => { await quoteShipment(detail.order.id, recipient) })}
            >
              {busy === 'quote' ? 'جاري الحساب...' : 'حساب التكلفة'}
            </button>
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              disabled={blocked || busy !== null}
              onClick={() => void run('order', async () => { await createShippingOrder(detail.order.id, recipient) })}
            >
              {busy === 'order' ? 'جاري الإنشاء...' : 'إنشاء طلب الشحن'}
            </button>
            <button
              type="button"
              className={ownerPrimaryButtonClass}
              disabled={blocked || busy !== null || optionId === '' || Boolean(shipment?.shipment_id)}
              onClick={() => void run('book', async () => { await createShipment(detail.order.id, optionId) })}
            >
              {busy === 'book' ? 'جاري الحجز...' : 'إنشاء الشحنة'}
            </button>
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              disabled={!enabled || busy !== null || !shipment?.remote_order_created}
              onClick={() => void run('sync', async () => { await syncShipment(detail.order.id) })}
            >
              تحديث الحالة
            </button>
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              disabled={!enabled || busy !== null || !shipment}
              onClick={() => void run('retry', async () => { await retryShipment(detail.order.id) })}
            >
              إعادة المحاولة
            </button>
            <button
              type="button"
              className={ownerSecondaryButtonClass}
              disabled={!enabled || busy !== null || !shipment?.shipment_id}
              onClick={() =>
                void run('label', async () => {
                  const response = await getShippingLabel(detail.order.id)
                  const url = response.data.awb_url
                  if (url?.startsWith('https://')) {
                    window.open(url, '_blank', 'noopener,noreferrer')
                  }
                })
              }
            >
              طباعة البوليصة
            </button>
          </div>
        </article>
      ) : null}
    </section>
  )
}

function ShipmentFacts({ shipment }: { shipment: OwnerShipment | null }) {
  if (!shipment) {
    return <p className="text-sm text-slate-600">لم تُنشأ شحنة بعد. أدخل عنوان التسليم ثم احسب التكلفة.</p>
  }

  return (
    <dl className="grid gap-3 text-sm sm:grid-cols-2">
      <div>
        <dt className="text-xs text-slate-500">حالة الشحنة</dt>
        <dd>{shipment.status_label}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">شركة الشحن</dt>
        <dd>{shipment.carrier ?? '—'}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">التكلفة</dt>
        <dd>{formatPaymentAmount(shipment.shipping_cost, shipment.currency)}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">رقم التتبع</dt>
        <dd dir="ltr">{shipment.tracking_number ?? '—'}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">التسليم</dt>
        <dd>{shipment.status === 'delivered' ? 'تم تأكيد التسليم' : 'لم يُؤكد التسليم'}</dd>
      </div>
      {shipment.tracking_url ? (
        <div>
          <dt className="text-xs text-slate-500">رابط التتبع</dt>
          <dd>
            <a className="text-brand-cobalt-700 underline" href={shipment.tracking_url} target="_blank" rel="noreferrer">
              متابعة الشحنة
            </a>
          </dd>
        </div>
      ) : null}
      {shipment.error_message ? (
        <div className="sm:col-span-2">
          <dt className="text-xs text-slate-500">خطأ المزامنة</dt>
          <dd className="text-red-700">{shipment.error_message}</dd>
        </div>
      ) : null}
    </dl>
  )
}
