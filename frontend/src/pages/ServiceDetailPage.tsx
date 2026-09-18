import { useEffect } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicService } from '../services/catalog'
import type { Service } from '../types/api'
import { formatDuration, formatMoney, servicePriceLabel, SERVICE_CATEGORY_LABELS } from '../utils/catalog'
import { APP_NAME } from '../utils/constants'
import { resolveMediaUrl } from '../utils/mediaUrl'
import { buildRequestQuotePath } from '../utils/quoteRequests'
import { absoluteAssetUrl, siteOrigin } from '../utils/seo'

function ServicePublicSeo({ service }: { service: Service }) {
  useEffect(() => {
    const origin = siteOrigin(window.location.origin) || window.location.origin
    const title = service.seo?.title || `${service.name} | ${APP_NAME}`
    const description =
      service.seo?.description || service.summary || service.short_description || `خدمة ${service.name} من حبر وأبعاد.`
    const canonical = service.seo?.canonical_url || `${origin}/services/${service.slug}`
    const robots = service.seo?.robots || 'index,follow'
    const ogTitle = service.seo?.og_title || title
    const ogDescription = service.seo?.og_description || description
    const image = absoluteAssetUrl(service.seo?.og_image || service.hero_image, origin)

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

    document.querySelector('meta[property="og:title"]')?.setAttribute('content', ogTitle)
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', ogDescription)
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical)
    if (image) {
      document.querySelector('meta[property="og:image"]')?.setAttribute('content', image)
      document.querySelector('meta[name="twitter:image"]')?.setAttribute('content', image)
    }
    document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', ogTitle)
    document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', ogDescription)
  }, [service])

  return null
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="text-lg font-semibold text-[#111318]">{title}</h2>
      {children}
    </section>
  )
}

export function ServiceDetailPage() {
  const { slug = '' } = useParams()
  const { state, reload } = useAsyncData(() => getPublicService(slug), [slug])

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الخدمة..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل الخدمة. ${state.message}`} onRetry={() => void reload()} />
  }

  const service = state.data

  if (!service) {
    return (
      <CatalogEmptyState
        title="الخدمة غير متاحة"
        description="قد تكون الخدمة غير منشورة أو غير موجودة."
        actions={[{ to: '/services', label: 'كل الخدمات', variant: 'primary' }]}
      />
    )
  }

  const quotePath = buildRequestQuotePath({
    source_type: 'SERVICE',
    source_id: service.id,
    title: service.name,
  })
  const ctaTo = service.pricing_mode === 'QUOTE' || !service.is_chargeable ? quotePath : '/consultant'
  const hero = resolveMediaUrl(service.hero_image)

  return (
    <section className="space-y-10">
      <ServicePublicSeo service={service} />
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الخدمات', to: '/services' },
          { name: service.name },
        ]}
      />

      <header className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        {hero ? (
          <img src={hero} alt="" className="h-56 w-full object-cover sm:h-72" />
        ) : (
          <div className="h-40 bg-gradient-to-l from-slate-100 to-[#F7F5EF] sm:h-52" />
        )}
        <div className="space-y-4 p-6 sm:p-8">
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
              {SERVICE_CATEGORY_LABELS[service.category]}
            </span>
            {service.subcategory ? (
              <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{service.subcategory}</span>
            ) : null}
            {(service.tags ?? []).map((tag) => (
              <span key={tag} className="rounded-full bg-[#EEF2FF] px-3 py-1 text-xs text-[#315CFF]">
                {tag}
              </span>
            ))}
          </div>
          <h1 className="text-3xl font-semibold text-[#111318]">{service.name}</h1>
          {service.summary ? <p className="max-w-3xl text-base leading-8 text-slate-600">{service.summary}</p> : null}
          <div className="flex flex-wrap items-baseline gap-4">
            <span className="text-2xl font-semibold">{servicePriceLabel(service)}</span>
            {service.duration_days != null ? (
              <span className="text-sm text-slate-500">{formatDuration(service.duration_days)}</span>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-3">
            <PublicCta to={ctaTo}>{service.is_chargeable ? 'اطلب الخدمة' : 'اطلب تسعير'}</PublicCta>
            <PublicCta to="/packages" variant="secondary">
              تصفح الباقات
            </PublicCta>
          </div>
        </div>
      </header>

      {service.description || service.scope ? (
        <Section title="نظرة عامة">
          {service.description ? <p className="leading-8 text-slate-700">{service.description}</p> : null}
          {service.scope ? <p className="mt-3 leading-8 text-slate-600">{service.scope}</p> : null}
        </Section>
      ) : null}

      {(service.features?.length || service.deliverables?.length) ? (
        <Section title="ماذا نقدّم">
          <ul className="grid gap-2 sm:grid-cols-2">
            {(service.features?.length ? service.features : service.deliverables ?? []).map((item) => (
              <li key={item} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                {item}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {(service.process_steps ?? []).length > 0 ? (
        <Section title="آلية العمل">
          <ol className="grid gap-3">
            {service.process_steps!.map((step, index) => (
              <li key={`${step.title}-${index}`} className="rounded-2xl border border-slate-200 bg-white p-4">
                <p className="font-medium text-[#111318]">
                  <span className="me-2 text-slate-400">{index + 1}.</span>
                  {step.title}
                </p>
                {step.description ? <p className="mt-1 text-sm leading-7 text-slate-600">{step.description}</p> : null}
              </li>
            ))}
          </ol>
        </Section>
      ) : null}

      {(service.packages ?? []).length > 0 ? (
        <Section title="الباقات">
          <ul className="grid gap-3 sm:grid-cols-2">
            {service.packages!.map((pkg) => (
              <li key={pkg.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                <Link to="/packages" className="font-medium hover:underline">
                  {pkg.name}
                </Link>
                <p className="mt-2 text-sm font-semibold">
                  {formatMoney(Number(pkg.price), pkg.currency)}
                </p>
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {(service.addons ?? []).length > 0 ? (
        <Section title="الإضافات">
          <ul className="grid gap-3 sm:grid-cols-2">
            {service.addons!.map((addon) => (
              <li key={addon.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                <p className="font-medium">{addon.name}</p>
                {addon.summary ? <p className="mt-1 text-sm text-slate-600">{addon.summary}</p> : null}
                {addon.price != null ? (
                  <p className="mt-2 text-sm font-semibold">{formatMoney(Number(addon.price), addon.currency)}</p>
                ) : null}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {(service.gallery ?? []).length > 0 ? (
        <Section title="معرض">
          <div className="grid gap-3 sm:grid-cols-3">
            {service.gallery!.map((src) => (
              <img key={src} src={resolveMediaUrl(src) || src} alt="" className="h-40 w-full rounded-2xl object-cover" />
            ))}
          </div>
        </Section>
      ) : null}

      {(service.portfolio ?? []).length > 0 ? (
        <Section title="أعمال ذات صلة">
          <ul className="grid gap-3 sm:grid-cols-2">
            {service.portfolio!.map((item) => (
              <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                <Link to={`/portfolio/${item.slug}`} className="font-medium hover:underline">
                  {item.title}
                </Link>
                {item.description ? <p className="mt-1 text-sm text-slate-600">{item.description}</p> : null}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {(service.faq ?? []).length > 0 ? (
        <Section title="الأسئلة الشائعة">
          <div className="space-y-3">
            {service.faq!.map((item) => (
              <details key={item.question} className="rounded-2xl border border-slate-200 bg-white p-4">
                <summary className="cursor-pointer font-medium text-[#111318]">{item.question}</summary>
                <p className="mt-2 text-sm leading-7 text-slate-600">{item.answer}</p>
              </details>
            ))}
          </div>
        </Section>
      ) : null}

      <div className="rounded-3xl border border-slate-200 bg-[#F7F5EF] p-6 sm:p-8">
        <h2 className="text-xl font-semibold text-[#111318]">جاهز تبدأ؟</h2>
        <p className="mt-2 max-w-2xl text-sm leading-7 text-slate-600">
          تواصل معنا لطلب تسعير أو توصية مناسبة لمشروعك — فريق حبر وأبعاد جاهز للتنفيذ.
        </p>
        <div className="mt-4 flex flex-wrap gap-3">
          <PublicCta to={ctaTo}>ابدأ الآن</PublicCta>
          <PublicCta to="/contact" variant="secondary">
            تواصل معنا
          </PublicCta>
        </div>
      </div>

      {(service.related_services ?? []).length > 0 ? (
        <Section title="خدمات ذات صلة">
          <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {service.related_services!.map((item) => (
              <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                <Link to={`/services/${item.slug}`} className="font-medium hover:underline">
                  {item.name}
                </Link>
                {item.summary ? <p className="mt-1 text-sm text-slate-600">{item.summary}</p> : null}
                <p className="mt-2 text-sm font-semibold">{servicePriceLabel(item)}</p>
              </li>
            ))}
          </ul>
        </Section>
      ) : null}
    </section>
  )
}
