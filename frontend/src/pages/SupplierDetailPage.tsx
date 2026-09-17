import { Link, useParams } from 'react-router-dom'
import { useEffect } from 'react'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { ProfileChip, SupplierProfileView } from '../components/suppliers/SupplierProfileView'
import { SupplierPortfolioGrid } from '../components/suppliers/SupplierPortfolioGrid'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSupplier } from '../services/suppliers'
import type { Supplier } from '../types/api'
import { APP_NAME } from '../utils/constants'
import { absoluteAssetUrl, siteOrigin } from '../utils/seo'
import { supplierPublicSections } from '../utils/suppliersProfile'

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
  const sectionsMeta = supplierPublicSections(supplier)

  return (
    <article className="space-y-8">
      <SupplierPublicSeo supplier={supplier} />
      <SupplierProfileView
        name={supplier.name}
        logo={supplier.logo}
        coverImage={supplier.cover_image}
        shortDescription={supplier.short_description}
        statusBadges={
          <>
            {supplier.featured ? <ProfileChip>مميّز</ProfileChip> : null}
            {supplier.category ? <ProfileChip>{supplier.category}</ProfileChip> : null}
            {supplier.city || supplier.location ? <ProfileChip>{supplier.city || supplier.location}</ProfileChip> : null}
          </>
        }
        actions={
          <>
            {supplier.phone ? <a className="rounded-full bg-slate-900 px-4 py-2 text-sm text-white" href={`tel:${supplier.phone}`}>تواصل</a> : null}
            <Link to="/contact" className="rounded-full border px-4 py-2 text-sm">طلب عبر حبر وأبعاد</Link>
          </>
        }
        sections={[
          {
            id: 'about',
            title: 'عن المورد',
            body: <p className="leading-8">{sectionsMeta.find((s) => s.id === 'about')?.content}</p>,
          },
          {
            id: 'services',
            title: 'الخدمات',
            body: (
              <ul className="flex flex-wrap gap-2">
                {(supplier.services ?? []).map((service) => (
                  <li key={service}><ProfileChip>{service}</ProfileChip></li>
                ))}
              </ul>
            ),
          },
          {
            id: 'specialties',
            title: 'التخصصات',
            body: (
              <ul className="flex flex-wrap gap-2">
                {(supplier.specialties ?? []).map((specialty) => (
                  <li key={specialty}><ProfileChip>{specialty}</ProfileChip></li>
                ))}
              </ul>
            ),
          },
          {
            id: 'products',
            title: 'المنتجات',
            body: products.length > 0 ? (
              <ul className="grid gap-3 sm:grid-cols-2">
                {products.map((product) => (
                  <li key={product.slug} className="rounded-xl border p-3">
                    <p className="font-medium">{product.name}</p>
                    <p className="text-slate-600">{product.short_description}</p>
                    <Link className="mt-2 inline-block underline" to={`/suppliers/${encodeURIComponent(slug)}/products/${encodeURIComponent(product.slug)}`}>عرض المنتج</Link>
                  </li>
                ))}
              </ul>
            ) : <p className="text-slate-500">لا منتجات منشورة.</p>,
          },
          {
            id: 'portfolio',
            title: 'المعرض',
            body: <SupplierPortfolioGrid items={portfolio} />,
          },
          {
            id: 'areas',
            title: 'مناطق الخدمة',
            body: (supplier.service_areas ?? []).length ? (
              <ul className="flex flex-wrap gap-2">{(supplier.service_areas ?? []).map((area) => <li key={area}><ProfileChip>{area}</ProfileChip></li>)}</ul>
            ) : undefined,
          },
          {
            id: 'certs',
            title: 'الشهادات',
            body: (supplier.certifications ?? []).length ? (
              <ul className="flex flex-wrap gap-2">{(supplier.certifications ?? []).map((cert) => <li key={cert}><ProfileChip>{cert}</ProfileChip></li>)}</ul>
            ) : undefined,
          },
          {
            id: 'availability',
            title: 'التوفر ومدة التسليم',
            body: sectionsMeta.find((s) => s.id === 'availability')?.content || undefined,
          },
          {
            id: 'contact',
            title: 'التواصل',
            body: (supplier.phone || supplier.email || supplier.website) ? (
              <div className="space-y-1">
                {supplier.phone ? <p>هاتف: {supplier.phone}</p> : null}
                {supplier.email ? <p>بريد: {supplier.email}</p> : null}
                {supplier.website ? <p>موقع: {supplier.website}</p> : null}
              </div>
            ) : <p className="text-slate-500">بيانات التواصل غير ظاهرة للعامة.</p>,
          },
        ]}
      />

      <section className="rounded-3xl bg-slate-900 p-6 text-white">
        <h2 className="text-2xl font-semibold">ابدأ طلبك مع حبر وأبعاد</h2>
        <p className="mt-2 text-white/70">نربطك بالمورد المناسب بعد اعتماد المحتوى.</p>
        <Link to="/contact" className="mt-4 inline-flex min-h-11 rounded-full bg-brand-primary px-5 text-sm font-medium text-white">تواصل معنا</Link>
      </section>

      <p className="text-sm text-slate-600">
        <Link to="/suppliers" className="underline">العودة إلى الموردين</Link>
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
