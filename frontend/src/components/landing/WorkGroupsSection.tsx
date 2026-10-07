import { Link } from 'react-router-dom'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicSectors } from '../../services/sectors'
import { resolveMediaUrl } from '../../utils/mediaUrl'

export function WorkGroupsSection() {
  const { state } = useAsyncData(getPublicSectors)
  const sectors = state.status === 'ready' ? state.data : []

  if (sectors.length === 0) {
    return null
  }

  return (
    <section id="work-groups" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container">
        <div className="rounded-3xl border border-brand-ink-100 bg-brand-paper p-4 sm:p-5">
          <header className="mb-4 max-w-2xl space-y-1">
            <h2 className="text-2xl font-bold text-brand-ink-900">مجموعات الأعمال</h2>
            <p className="text-sm leading-6 text-brand-ink-500">قطاعات يمكن ترتيبها من لوحة التحكم، وكل مجموعة تعرض عناصرها في مكان واحد.</p>
          </header>
          <ul className="grid gap-3 md:grid-cols-2">
            {sectors.map((sector) => {
              const image = resolveMediaUrl(sector.cover_image)
              return (
                <li key={sector.id} className="flex gap-3 rounded-2xl bg-white p-3">
                  {image ? (
                    <img src={image} alt="" className="h-16 w-16 shrink-0 rounded-xl object-cover" loading="lazy" width={64} height={64} />
                  ) : (
                    <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-brand-ink-900 text-xs text-white">مجموعة</span>
                  )}
                  <div className="min-w-0">
                    <h3 className="font-semibold text-brand-ink-900">{sector.name_ar}</h3>
                    {sector.description ? <p className="mt-1 line-clamp-2 text-sm leading-6 text-brand-ink-500">{sector.description}</p> : null}
                    <p className="mt-2 text-xs text-brand-ink-400">
                      {(sector.packages?.length ?? 0).toLocaleString('en-US')} باقات · {(sector.services?.length ?? 0).toLocaleString('en-US')} خدمات
                    </p>
                    <Link to={`/sectors/${sector.slug}`} className="mt-1 inline-flex text-sm font-medium text-brand-cobalt-700">
                      عرض المجموعة
                    </Link>
                  </div>
                </li>
              )
            })}
          </ul>
        </div>
      </div>
    </section>
  )
}
