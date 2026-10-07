import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { MediaLightbox } from '../ui/MediaLightbox'
import { useAsyncData } from '../../hooks/useAsyncData'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
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
  const { resolveContent } = usePublicMarketing()
  const [filter, setFilter] = useState<PortfolioCategory | 'all'>('all')
  const [player, setPlayer] = useState<{ title: string; src: string } | null>(null)
  const [imagePreview, setImagePreview] = useState<{ title: string; src: string } | null>(null)
  const items = state.status === 'ready' ? state.data : EMPTY_ITEMS
  const visible = useMemo(
    () => (filter === 'all' ? items : items.filter((item) => item.category === filter)),
    [filter, items],
  )
  const shown = compact ? visible.slice(0, 6) : visible
  const hasMore = compact && visible.length > 6

  const eyebrow = resolveContent('portfolio', 'eyebrow', 'أعمالنا')
  const title = resolveContent('portfolio', 'title', 'أعمال تتحدث عنّا')
  const description = resolveContent(
    'portfolio',
    'description',
    'نماذج مختارة من المعرض المنشور عبر المنصة.',
  )

  return (
    <section id="portfolio" className="marketing-section scroll-mt-24 bg-brand-ink-900 text-white">
      <div className="marketing-container">
        <header className="mb-10 max-w-2xl space-y-3">
          <p className="brand-label text-brand-cobalt-300">{eyebrow}</p>
          <h2 className="text-[clamp(1.75rem,1.3rem+1.4vw,2.75rem)] font-bold leading-tight">{title}</h2>
          <p className="leading-8 text-white/65">{description}</p>
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
                  <div className="relative">
                    {imageSrc ? (
                      <button type="button" className="block w-full" onClick={() => setImagePreview({ title: item.title, src: imageSrc })}>
                        <img
                          src={imageSrc}
                          alt={item.title}
                          className="h-40 w-full object-cover"
                          loading="lazy"
                          width={640}
                          height={160}
                        />
                      </button>
                    ) : (
                      <div className="flex h-40 items-end bg-gradient-to-br from-white/10 via-[#315CFF]/20 to-[#315CFF]/30 p-5">
                        <span className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70">
                          {CATEGORY_LABELS[item.category]}
                        </span>
                      </div>
                    )}
                  </div>
                  <div className="space-y-2 p-4">
                    <h3 className="font-semibold">{item.title}</h3>
                    {item.description ? <p className="line-clamp-2 text-sm leading-6 text-white/70">{item.description}</p> : null}
                    <div className="flex flex-wrap gap-2 text-sm">
                      {item.video_url || item.has_video ? (
                        <button
                          type="button"
                          className="min-h-10 rounded-full bg-white px-3 text-slate-900"
                          onClick={() => {
                            if (item.video_url) {
                              setPlayer({ title: item.title, src: item.video_url })
                            }
                          }}
                        >
                          تشغيل الفيديو
                        </button>
                      ) : null}
                      {item.catalog_pdf_url ? (
                        <>
                          <a className="min-h-10 rounded-full bg-white/10 px-3 leading-10" href={item.catalog_pdf_url} target="_blank" rel="noreferrer">عرض الكتالوج</a>
                          <a className="min-h-10 rounded-full bg-white/10 px-3 leading-10" href={item.catalog_pdf_url} download>تحميل</a>
                        </>
                      ) : null}
                      {item.profile_pdf_url ? (
                        <>
                          <a className="min-h-10 rounded-full bg-white/10 px-3 leading-10" href={item.profile_pdf_url} target="_blank" rel="noreferrer">عرض البروفايل</a>
                          <a className="min-h-10 rounded-full bg-white/10 px-3 leading-10" href={item.profile_pdf_url} download>تحميل</a>
                        </>
                      ) : null}
                      <Link to={detailPath(item)} className="min-h-10 leading-10 text-[#9db4ff]">التفاصيل</Link>
                    </div>
                  </div>
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
      <MediaLightbox open={imagePreview !== null} imageSrc={imagePreview?.src} title={imagePreview?.title} onClose={() => setImagePreview(null)} />
      <MediaLightbox open={player !== null} videoSrc={player?.src} title={player?.title} onClose={() => setPlayer(null)} />
    </section>
  )
}
