import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSectors, type PublicSector } from '../services/sectors'
import { resolveSectorSlug, SOLUTION_PUBLIC_SLUGS, SOLUTION_SECTOR_ALIASES } from '../utils/catalogRoutes'

function publicSlugForSector(sectorSlug: string): string {
  const alias = Object.entries(SOLUTION_SECTOR_ALIASES).find(([, resolved]) => resolved === sectorSlug)
  return alias?.[0] ?? sectorSlug
}

export function SolutionsPage() {
  const { state, reload } = useAsyncData(getPublicSectors)
  const resolved = new Set(SOLUTION_PUBLIC_SLUGS.map((slug) => resolveSectorSlug(slug)))
  const sectors =
    state.status === 'ready' ? state.data.filter((sector) => resolved.has(sector.slug)) : []

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'حلول القطاعات' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">حلول متخصصة حسب القطاع</h1>
        <p className="max-w-3xl text-slate-600">
          حلول تجمع الخدمات المناسبة لكل قطاع — من التصوير والمحتوى إلى المتاجر والطباعة والفعاليات.
        </p>
        <PublicCta to="/consultant">اكتشف احتياجك</PublicCta>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="list" label="جاري تحميل الحلول..." /> : null}
      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الحلول. ${state.message}`} onRetry={() => void reload()} />
      ) : null}
      {state.status === 'ready' && sectors.length === 0 ? (
        <CatalogEmptyState
          title="الحلول قيد التجهيز"
          description="يمكنك تصفح كل القطاعات أو البدء من المستشار الذكي."
          actions={[{ to: '/sectors', label: 'كل القطاعات', variant: 'primary' }]}
        />
      ) : null}
      {state.status === 'ready' && sectors.length > 0 ? (
        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {sectors.map((sector) => (
            <SolutionCard key={sector.id} sector={sector} to={`/solutions/${publicSlugForSector(sector.slug)}`} />
          ))}
        </ul>
      ) : null}
    </section>
  )
}

function SolutionCard({ sector, to }: { sector: PublicSector; to: string }) {
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
      <PublicCta to={to} variant="secondary">
        اكتشف الحل المناسب
      </PublicCta>
    </li>
  )
}
