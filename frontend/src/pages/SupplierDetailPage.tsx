import { Link, useParams } from 'react-router-dom'
import { useEffect } from 'react'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { SupplierPortfolioGrid } from '../components/suppliers/SupplierPortfolioGrid'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSupplier } from '../services/suppliers'
import type { Supplier } from '../types/api'
import { APP_NAME } from '../utils/constants'
import { absoluteAssetUrl, siteOrigin } from '../utils/seo'

function SupplierPublicSeo({ supplier }: { supplier: Supplier }) {
  useEffect(() => {
    const origin = siteOrigin(window.location.origin) || window.location.origin
    const title = supplier.seo?.title || `${supplier.name} | ${APP_NAME}`
    const description = supplier.seo?.description || supplier.short_description || `ملف المورد ${supplier.name} ضمن شركاء حبر وأبعاد.`
    const canonical = supplier.seo?.canonical_url || `${origin}/suppliers/${supplier.slug}`
    const robots = supplier.seo?.robots || 'index,follow'
    const image = absoluteAssetUrl(supplier.seo?.og_image || supplier.logo, origin)

    document.title = title
    document.documentElement.lang = 'ar'
    document.querySelector('meta[name="description"]')?.setAttribute('content', description)
    document.querySelector('meta[name="robots"]')?.setAttribute('content', robots)

    let canonicalLink = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null
    if (!canonicalLink) {
      canonicalLink = document.createElement('link')
      canonicalLink.rel = 'canonical'
      document.head.append(canonicalLink)
    }
    canonicalLink.href = canonical

    document.querySelector('meta[property="og:title"]')?.setAttribute('content', title)
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', description)
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical)
    if (image) {
      document.querySelector('meta[property="og:image"]')?.setAttribute('content', image)
      document.querySelector('meta[name="twitter:image"]')?.setAttribute('content', image)
    }
    document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', title)
    document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', description)
  }, [supplier])

  return null
}

type SupplierDetailBodyProps = {
  slug: string
}

function SupplierDetailBody({ slug }: SupplierDetailBodyProps) {
  const { state, reload } = useAsyncData(() => getPublicSupplier(slug))

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل المورد..." />
  }

  if (state.status === 'error') {
    if (state.message.toLowerCase().includes('not found') || state.message.includes('404')) {
      return (
        <CatalogEmptyState
          title="لم نجد هذا المورد."
          description="قد يكون غير منشور أو أن الرابط غير صحيح."
          actions={[{ to: '/suppliers', label: 'كل الموردين', variant: 'primary' }]}
        />
      )
    }

    return <CatalogErrorState message={`تعذر تحميل المورد. ${state.message}`} onRetry={() => void reload()} />
  }

  const supplier = state.data
  const portfolio = supplier.portfolio ?? supplier.portfolio_preview ?? []
  const products = supplier.products ?? []
  const whyPoints = [
    supplier.years_experience ? `${supplier.years_experience} سنة خبرة` : null,
    supplier.min_order_info,
    supplier.brand_description,
    ...supplier.specialties.slice(0, 3),
  ].filter((value): value is string => Boolean(value && value.trim()))

  return (
    <article className="space-y-8">
      <SupplierPublicSeo supplier={supplier} />
      <header
        className="overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 text-white"
        style={supplier.cover_image ? { backgroundImage: `url(${supplier.cover_image})`, backgroundSize: 'cover' } : undefined}
      >
        <div className="flex min-w-0 flex-col gap-4 bg-slate-950/70 p-6 sm:flex-row sm:items-start">
          <img src={supplier.logo} alt={`شعار ${supplier.name}`} width={80} height={80} className="h-20 w-20 shrink-0 rounded-2xl object-cover" />
          <div className="min-w-0 space-y-2">
            <p className="text-sm text-brand-primary">المورد</p>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-semibold">{supplier.name}</h1>
              {supplier.featured ? <span className="rounded-full bg-brand-primary-soft px-2 py-0.5 text-xs text-brand-primary">مميّز</span> : null}
            </div>
            <p className="text-sm text-white/70">{supplier.location}{supplier.category ? ` · ${supplier.category}` : ''}</p>
            <p className="max-w-2xl text-sm leading-7 text-white/80">{supplier.short_description}</p>
            <div className="flex flex-wrap gap-2 pt-2">
              {supplier.phone ? <a className="rounded-full bg-brand-primary px-4 py-2 text-sm text-white" href={`tel:${supplier.phone}`}>تواصل</a> : null}
              <Link to="/contact" className="rounded-full border border-white/30 px-4 py-2 text-sm">طلب عبر حبر وأبعاد</Link>
            </div>
          </div>
        </div>
      </header>

      <section className="space-y-2">
        <h2 className="text-xl font-semibold">عن المورد</h2>
        <p className="max-w-3xl leading-8 text-slate-600">{supplier.description ?? supplier.short_description}</p>
      </section>

      <section className="space-y-2">
        <h2 className="text-xl font-semibold">التخصصات</h2>
        <ul className="flex flex-wrap gap-2">
          {supplier.specialties.map((specialty) => (
            <li key={specialty} className="rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-800">
              {specialty}
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-2">
        <h2 className="text-xl font-semibold">الخدمات</h2>
        <ul className="flex flex-wrap gap-2">
          {supplier.services.map((service) => (
            <li key={service} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700">
              {service}
            </li>
          ))}
        </ul>
      </section>

      <SupplierPortfolioGrid items={portfolio} />

      {whyPoints.length > 0 ? (
        <section className="space-y-3">
          <h2 className="text-xl font-semibold">لماذا تختارنا</h2>
          <ul className="grid gap-3 sm:grid-cols-2">
            {whyPoints.map((point) => (
              <li key={point} className="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-7 text-slate-700">
                {point}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {products.length > 0 ? (
        <section className="space-y-3">
          <h2 className="text-xl font-semibold">المنتجات</h2>
          <ul className="grid gap-3 sm:grid-cols-2">
            {products.map((product) => (
              <li key={product.slug} className="rounded-2xl border border-slate-200 bg-white p-4">
                <p className="font-medium">{product.name}</p>
                <p className="text-sm text-slate-600">{product.short_description}</p>
                <Link className="mt-2 inline-block text-sm underline" to={`/suppliers/${encodeURIComponent(slug)}/products/${encodeURIComponent(product.slug)}`}>
                  عرض المنتج
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {(supplier.phone || supplier.email || supplier.website) ? (
        <section className="space-y-2 rounded-2xl border bg-white p-5">
          <h2 className="text-xl font-semibold">تواصل</h2>
          {supplier.phone ? <p>هاتف: {supplier.phone}</p> : null}
          {supplier.email ? <p>بريد: {supplier.email}</p> : null}
          {supplier.website ? <p>موقع: {supplier.website}</p> : null}
        </section>
      ) : null}

      <section className="rounded-3xl bg-slate-900 p-6 text-white">
        <h2 className="text-2xl font-semibold">ابدأ طلبك مع حبر وأبعاد</h2>
        <p className="mt-2 text-white/70">نربطك بالمورد المناسب بعد اعتماد المحتوى.</p>
        <Link to="/contact" className="mt-4 inline-flex min-h-11 rounded-full bg-brand-primary px-5 text-sm font-medium text-white">تواصل معنا</Link>
      </section>

      <p className="text-sm text-slate-600">
        <Link
          to="/suppliers"
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          العودة إلى الموردين
        </Link>
      </p>
    </article>
  )
}

export function SupplierDetailPage() {
  const { slug = '' } = useParams()

  if (slug === '') {
    return (
      <CatalogEmptyState
        title="لم نجد هذا المورد."
        description="يمكنك العودة إلى قائمة الموردين."
        actions={[{ to: '/suppliers', label: 'كل الموردين', variant: 'primary' }]}
      />
    )
  }

  return <SupplierDetailBody key={slug} slug={slug} />
}
