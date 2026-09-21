import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicPortfolio, type PortfolioCategory, type PortfolioItem } from '../../services/marketing'
import { resolveMediaUrl } from '../../utils/mediaUrl'

const EMPTY_ITEMS: PortfolioItem[] = []

const FILTERS: Array<{ value: PortfolioCategory | 'all'; label: string }> = [
  { value: 'all', label: 'الكل' },
  { value: 'web', label: 'مواقع' },
  { value: 'branding', label: 'هوية' },
  { value: 'social', label: 'سوشيال' },
  { value: 'video', label: 'فيديو' },
  { value: 'marketing', label: 'تسويق' },
  { value: 'events', label: 'فعاليات' },
  { value: 'printing', label: 'طباعة' },
]

const CATEGORY_LABELS: Record<PortfolioCategory, string> = {
  web: 'مواقع',
  branding: 'هوية',
  social: 'سوشيال',
  video: 'فيديو',
  marketing: 'تسويق',
  events: 'فعاليات',
  printing: 'طباعة',
}

function detailPath(item: PortfolioItem) {
  return `/portfolio/${encodeURIComponent(item.slug || String(item.id))}`
}

export function PortfolioSection({ compact = true }: { compact?: boolean }) {
  const { state } = useAsyncData(getPublicPortfolio)
  const [filter, setFilter] = useState<PortfolioCategory | 'all'>('all')
  const items = state.status === 'ready' ? state.data : EMPTY_ITEMS
  const visible = useMemo(
    () => (filter === 'all' ? items : items.filter((item) => item.category === filter)),
    [filter, items],
  )
  const shown = compact ? visible.slice(0, 6) : visible
  const hasMore = compact && visible.length > 6

  return (
    <section id="portfolio" className="marketing-section scroll-mt-24 bg-brand-ink-900 text-white">
      <div className="marketing-container">
        <header className="mb-10 max-w-2xl space-y-3">
          <p className="brand-label text-brand-cobalt-300">أعمالنا</p>
          <h2 className="text-[clamp(1.75rem,1.3rem+1.4vw,2.75rem)] font-bold leading-tight">أعمال تتحدث عنّا</h2>
          <p className="leading-8 text-white/65">نماذج مختارة من المعرض المنشور عبر المنصة.</p>
        </header>

        {items.length > 0 ? (
          <div className="mb-8 flex flex-wrap gap-2">
            {FILTERS.filter((option) => option.value === 'all' || items.some((item) => item.category === option.value)).map(
              (option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setFilter(option.value)}
                  className={`min-h-10 rounded-full px-4 text-sm transition ${
                    filter === option.value
                      ? 'bg-brand-cobalt-500 text-white'
                      : 'bg-white/10 text-white/80 hover:bg-white/15'
                  }`}
                >
                  {option.label}
                </button>
              ),
            )}
          </div>
        ) : null}

        {state.status === 'loading' ? (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true" aria-label="جاري تحميل الأعمال">
            {Array.from({ length: compact ? 3 : 6 }).map((_, index) => (
              <li key={index} className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
                <div className="h-44 animate-pulse bg-white/10" />
                <div className="space-y-3 p-5">
                  <div className="h-3 w-20 animate-pulse rounded bg-white/10" />
                  <div className="h-4 w-2/3 animate-pulse rounded bg-white/10" />
                </div>
              </li>
            ))}
          </ul>
        ) : null}

        {state.status === 'error' ? (
          <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-6 text-sm leading-7 text-white/70" role="status">
            تعذر تحميل الأعمال حالياً. يمكنك متابعة تصفح بقية الصفحة والمحاولة لاحقاً.
          </p>
        ) : null}

        {state.status === 'ready' && items.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-white/15 bg-white/5 px-4 py-10 text-center text-sm leading-7 text-white/65">
            لا توجد أعمال منشورة بعد. ستظهر هنا تلقائياً بعد نشرها من لوحة الإدارة.
          </p>
        ) : null}

        {shown.length > 0 ? (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {shown.map((item) => {
              const imageSrc = resolveMediaUrl(item.cover_url || item.image_url)
              return (
                <li key={item.id} className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
                  <Link
                    to={detailPath(item)}
                    className="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#315CFF]"
                  >
                    <div className="relative">
                      {imageSrc ? (
                        <img
                          src={imageSrc}
                          alt={item.title}
                          className="h-44 w-full object-cover transition group-hover:scale-[1.02]"
                          loading="lazy"
                          width={640}
                          height={176}
                        />
                      ) : (
                        <div className="flex h-44 items-end bg-gradient-to-br from-white/10 via-[#315CFF]/20 to-[#315CFF]/30 p-5">
                          <span className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70">
                            {CATEGORY_LABELS[item.category]}
                          </span>
                        </div>
                      )}
                      {item.has_video ? (
                        <span
                          className="absolute bottom-3 left-3 inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/55 text-white"
                          aria-hidden
                        >
                          ▶
                        </span>
                      ) : null}
                    </div>
                    <div className="space-y-2 p-5">
                      <div className="flex flex-wrap gap-2">
                        <span className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/80">
                          {CATEGORY_LABELS[item.category]}
                        </span>
                        {(item.sectors ?? []).slice(0, 2).map((sector) => (
                          <span key={sector.id} className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/80">
                            {sector.name}
                          </span>
                        ))}
                        {(item.services ?? []).slice(0, 2).map((service) => (
                          <span key={service.id} className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/80">
                            {service.name}
                          </span>
                        ))}
                        {item.is_sample ? (
                          <span className="rounded-full bg-[#315CFF]/25 px-2 py-0.5 text-xs text-[#315CFF]">نموذج عرض</span>
                        ) : null}
                      </div>
                      <h3 className="font-semibold group-hover:text-[#315CFF]">{item.title}</h3>
                      {item.description ? <p className="text-sm leading-7 text-white/70">{item.description}</p> : null}
                      <span className="inline-flex min-h-10 items-center text-sm font-medium text-[#315CFF]">عرض المشروع</span>
                    </div>
                  </Link>
                </li>
              )
            })}
          </ul>
        ) : null}

        {hasMore || (compact && items.length > 0) ? (
          <div className="mt-8">
            <Link
              to="/portfolio"
              className="brand-btn-primary"
            >
              عرض كل الأعمال
            </Link>
          </div>
        ) : null}
      </div>
    </section>
  )
}
