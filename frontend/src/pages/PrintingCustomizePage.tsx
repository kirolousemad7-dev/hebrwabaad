import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState } from '../components/catalog/CatalogStatus'
import { PrintingPricingStatus } from '../components/printing/PrintingPricingStatus'
import { PrintingRequestForm } from '../components/printing/PrintingRequestForm'
import { PublicCta } from '../components/public/PublicCta'
import { useAuth } from '../context/AuthContext'
import type { PrintingRequest } from '../types/api'
import { formatMoney } from '../utils/catalog'
import { PRINTING_CATEGORIES } from '../utils/printing'
import { getPrintingProductBySlug } from '../utils/printingProducts'

function formatRequestDate(value: string): string {
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, (month ?? 1) - 1, day ?? 1)

  return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' })
}

export function PrintingCustomizePage() {
  const { slug = '' } = useParams()
  const { user } = useAuth()
  const product = getPrintingProductBySlug(slug)
  const [submitted, setSubmitted] = useState<PrintingRequest | null>(null)
  const categoryName = product
    ? PRINTING_CATEGORIES.find((category) => category.id === product.category)?.name
    : null

  if (!product) {
    return (
      <CatalogEmptyState
        title="لم نجد هذا المنتج في كتالوج الطباعة الحالي."
        description="يمكنك العودة إلى فئات الطباعة واختيار منتج متاح."
        actions={[{ to: '/printing-packaging', label: 'الطباعة والتغليف', variant: 'primary' }]}
      />
    )
  }

  if (user && user.role !== 'CUSTOMER') {
    return (
      <section className="space-y-4">
        <h1 className="text-2xl font-semibold">تخصيص المنتج</h1>
        <p className="text-sm text-slate-600">طلب الطباعة المخصصة متاح لحسابات العملاء فقط.</p>
        <PublicCta to="/printing-packaging">العودة إلى المنتجات</PublicCta>
      </section>
    )
  }

  if (submitted) {
    return (
      <section className="space-y-6">
        <p className="text-sm font-medium text-slate-500">تخصيص الطباعة</p>
        <h1 className="text-2xl font-semibold">تم إرسال طلب الطباعة بنجاح</h1>
        <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-5">
          <p className="text-sm text-slate-600">رقم الطلب: {submitted.id}</p>
          <p className="font-medium">{submitted.product_name}</p>
          <p className="text-sm text-slate-600">الكمية: {submitted.quantity.toLocaleString('ar-SA')}</p>
          <p className="text-sm text-slate-600">تاريخ التسليم المطلوب: {formatRequestDate(submitted.required_date)}</p>
          <p className="text-sm text-slate-500">الحالة: قيد المراجعة. الدفع غير مفعّل في هذه المرحلة.</p>
        </div>
        <PrintingPricingStatus request={submitted} />
        <div className="flex flex-wrap gap-3">
          <PublicCta to={`/customer/printing-requests/${submitted.id}`}>متابعة الطلب</PublicCta>
          <PublicCta to="/printing-packaging" variant="secondary">
            الطباعة والتغليف
          </PublicCta>
          <PublicCta to="/dashboard" variant="secondary">
            لوحة العميل
          </PublicCta>
        </div>
      </section>
    )
  }

  return (
    <section className="space-y-6">
      <p className="text-sm font-medium text-slate-500">تخصيص الطباعة</p>
      <h1 className="text-2xl font-semibold">تخصيص المنتج</h1>
      <div className="flex min-w-0 flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:flex-row">
        <img
          src={product.image}
          alt={product.imageAlt}
          width={160}
          height={100}
          className="aspect-[16/10] w-full max-w-40 rounded-lg object-cover"
        />
        <div className="min-w-0 space-y-1">
          <h2 className="font-semibold">{product.name}</h2>
          {categoryName ? <p className="text-sm text-slate-500">{categoryName}</p> : null}
          <p className="text-sm text-slate-600">{product.summary}</p>
          <p className="text-sm font-medium">يبدأ من {formatMoney(product.startingPrice, product.currency)}</p>
          <p className="text-xs text-slate-500">السعر الابتدائي للتقدير فقط، ويُحسب سعر تقديري بعد الإرسال إن أمكن.</p>
        </div>
      </div>
      <PrintingRequestForm product={product} onSuccess={setSubmitted} />
      <p className="text-sm text-slate-600">
        <Link
          to={`/printing-packaging?category=${product.category}#printing-products`}
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          العودة إلى المنتجات
        </Link>
      </p>
    </section>
  )
}
