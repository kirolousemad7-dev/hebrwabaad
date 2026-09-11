import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSectors, type PublicSector } from '../services/sectors'

export function SectorsPage() {
  const { state, reload } = useAsyncData(getPublicSectors)

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'القطاعات' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">الحلول حسب قطاع نشاطك</h1>
        <p className="max-w-3xl text-slate-600">
          اختر قطاعك لترى الخدمات والباقات ودراسات الحالة المناسبة — أو ابدأ من اكتشاف احتياجك إن لم تكن متأكداً.
        </p>
        <PublicCta to="/consultant">اكتشف احتياجك</PublicCta>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="list" label="جاري تحميل القطاعات..." /> : null}
      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل القطاعات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}
      {state.status === 'ready' && state.data.length === 0 ? (
        <CatalogEmptyState
          title="القطاعات قيد التجهيز"
          description="سيتم نشر دليل القطاعات بعد اكتمال الربط مع الكتالوج."
          actions={[{ to: '/consultant', label: 'اكتشف احتياجك', variant: 'primary' }]}
        />
      ) : null}
      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {state.data.map((sector) => (
            <SectorCard key={sector.id} sector={sector} />
          ))}
        </ul>
      ) : null}
    </section>
  )
}

function SectorCard({ sector }: { sector: PublicSector }) {
  return (
    <li className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="font-semibold">{sector.name_ar}</h2>
      {sector.description ? <p className="flex-1 text-sm leading-7 text-slate-600">{sector.description}</p> : null}
      {sector.needs && sector.needs.length > 0 ? (
        <ul className="flex flex-wrap gap-1.5">
          {sector.needs.slice(0, 6).map((need) => (
            <li key={need} className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700">
              {need}
            </li>
          ))}
        </ul>
      ) : null}
      <PublicCta to={`/sectors/${sector.slug}`} variant="secondary">
        اكتشف الحل المناسب
      </PublicCta>
    </li>
  )
}
