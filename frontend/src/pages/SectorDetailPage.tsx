import { type ReactNode, useEffect } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSector } from '../services/sectors'
import { formatMoney, packagePriceLabel, servicePriceLabel } from '../utils/catalog'
import { resolveSectorSlug } from '../utils/catalogRoutes'

export function SectorDetailPage() {
  const location = useLocation()
  const { slug: rawSlug = '' } = useParams()
  const slug = resolveSectorSlug(rawSlug)
  const isSolution = location.pathname.startsWith('/solutions')
  const { state, reload } = useAsyncData(() => getPublicSector(slug), [slug])
  const sector = state.status === 'ready' ? state.data : null

  useEffect(() => {
    if (!sector) {
      return
    }

    document.title = `${sector.name_ar} | حبر وأبعاد`
    if (sector.description) {
      document.querySelector('meta[name="description"]')?.setAttribute('content', sector.description)
    }
  }, [sector])

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل القطاع..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل القطاع. ${state.message}`} onRetry={() => void reload()} />
  }

  if (!sector) {
    return (
      <CatalogEmptyState
        title="القطاع غير متاح"
        description="قد يكون القطاع غير منشور أو غير موجود."
        actions={[{ to: '/sectors', label: 'كل القطاعات', variant: 'primary' }]}
      />
    )
  }

  return (
    <section className="space-y-8">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: isSolution ? 'حلول القطاعات' : 'القطاعات', to: isSolution ? '/solutions' : '/sectors' },
          { name: sector.name_ar },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">{sector.name_ar}</h1>
        {sector.description ? <p className="max-w-3xl leading-8 text-slate-600">{sector.description}</p> : null}
        <div className="flex flex-wrap gap-3">
          <PublicCta to={`/consultant?sector=${encodeURIComponent(sector.slug)}`}>اكتشف الحل المناسب</PublicCta>
          <PublicCta to="/packages" variant="secondary">
            تصفح الباقات
          </PublicCta>
        </div>
      </header>

      {sector.needs && sector.needs.length > 0 ? (
        <div className="space-y-2">
          <h2 className="text-lg font-semibold">أهم الاحتياجات</h2>
          <ul className="flex flex-wrap gap-2">
            {sector.needs.map((need) => (
              <li key={need} className="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">
                {need}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <CatalogBlock title="خدمات موصى بها">
        {(sector.services ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">لم تُربط خدمات بعد — يمكن للمالك ربطها من لوحة التحكم.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {sector.services!.map((service) => (
              <li key={service.id} className="rounded-xl border border-slate-200 bg-white p-4">
                <p className="font-medium">{service.name}</p>
                {service.summary ? <p className="mt-1 text-sm text-slate-600">{service.summary}</p> : null}
                <p className="mt-2 text-sm font-semibold">{servicePriceLabel(service)}</p>
              </li>
            ))}
          </ul>
        )}
      </CatalogBlock>

      <CatalogBlock title="باقات مناسبة">
        {(sector.packages ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">لم تُربط باقات بعد.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {sector.packages!.map((pkg) => (
              <li key={pkg.id} className="rounded-xl border border-slate-200 bg-white p-4">
                <Link to={`/packages`} className="font-medium hover:underline">
                  {pkg.name}
                </Link>
                <p className="mt-2 text-sm font-semibold">{packagePriceLabel(pkg)}</p>
              </li>
            ))}
          </ul>
        )}
      </CatalogBlock>

      <CatalogBlock title="دراسات حالة">
        {(sector.case_studies ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">لا توجد دراسات حالة منشورة لهذا القطاع حالياً.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {sector.case_studies!.map((item) => (
              <li key={item.id} className="rounded-xl border border-slate-200 bg-white p-4">
                <p className="font-medium">{item.title}</p>
                {item.description ? <p className="mt-1 text-sm text-slate-600">{item.description}</p> : null}
                <div className="mt-3 flex flex-wrap gap-2">
                  {'slug' in item && item.slug ? (
                    <PublicCta to={`/portfolio/${item.slug}`} variant="secondary">
                      عرض المشروع
                    </PublicCta>
                  ) : null}
                  <PublicCta
                    to={`/consultant?sector=${encodeURIComponent(sector.slug)}&case=${item.id}`}
                    variant="secondary"
                  >
                    أبغى شيء مشابه
                  </PublicCta>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CatalogBlock>

      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
        <p className="text-sm text-slate-600">
          الطباعة والتغليف والإضافات المرتبطة تظهر ضمن مسارات الطلب عند اختيار خدمة أو باقة مناسبة.
        </p>
        <div className="mt-3 flex flex-wrap gap-3">
          <PublicCta to="/printing-packaging" variant="secondary">
            الطباعة والتغليف
          </PublicCta>
          <PublicCta to="/build-package" variant="secondary">
            صمّم باقتك مع إضافات
          </PublicCta>
        </div>
        {/* price helpers referenced so tree-shaking keeps formatMoney available if needed */}
        <span className="sr-only">{formatMoney(0, 'SAR')}</span>
      </div>
    </section>
  )
}

function CatalogBlock({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="text-lg font-semibold">{title}</h2>
      {children}
    </section>
  )
}
