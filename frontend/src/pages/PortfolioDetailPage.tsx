import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { useAsyncData } from '../hooks/useAsyncData'
import {
  getPublicPortfolioDetail,
  type PortfolioItem,
  type PortfolioMediaItem,
  type PortfolioCategory,
} from '../services/marketing'
import { resolveMediaUrl } from '../utils/mediaUrl'

const CATEGORY_LABELS: Record<PortfolioCategory, string> = {
  web: 'مواقع',
  branding: 'هوية',
  social: 'سوشيال',
  video: 'فيديو',
  marketing: 'تسويق',
  events: 'فعاليات',
  printing: 'طباعة',
}

function CaseBlock({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <h2 className="text-lg font-semibold text-[#111318]">{title}</h2>
      <div className="mt-3 text-sm leading-8 text-slate-700">{children}</div>
    </section>
  )
}

function MediaStage({ item, media }: { item: PortfolioItem; media: PortfolioMediaItem[] }) {
  const featured = media.find((row) => row.is_featured) ?? media[0]
  const images = media.filter((row) => row.type === 'IMAGE')
  const [activeImage, setActiveImage] = useState<string | null>(null)
  const [videoError, setVideoError] = useState(false)

  useEffect(() => {
    setVideoError(false)
  }, [featured?.id, featured?.url])

  const projectUrl = item.project_url

  if (!featured && !item.image_url) {
    return (
      <div className="flex min-h-64 items-center justify-center rounded-3xl bg-[#F7F5EF] text-sm text-slate-600">
        لا توجد وسائط منشورة لهذا المشروع بعد.
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="overflow-hidden rounded-3xl border border-slate-200 bg-[#111318]">
        {featured?.type === 'EXTERNAL_VIDEO' && featured.embed?.embed_url && !videoError ? (
          <div className="aspect-video w-full">
            <iframe
              title={featured.title || item.title}
              src={featured.embed.embed_url}
              className="h-full w-full"
              loading="lazy"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
              referrerPolicy="strict-origin-when-cross-origin"
              onError={() => setVideoError(true)}
            />
          </div>
        ) : null}

        {featured?.type === 'UPLOADED_VIDEO' && featured.url && !videoError ? (
          <video
            className="aspect-video w-full bg-black"
            controls
            playsInline
            preload="metadata"
            poster={resolveMediaUrl(featured.thumbnail_url || item.cover_url || item.image_url) || undefined}
            onError={() => setVideoError(true)}
          >
            <source src={resolveMediaUrl(featured.url) || featured.url} />
          </video>
        ) : null}

        {videoError ? (
          <div className="flex aspect-video items-center justify-center bg-[#111318] px-4 text-center text-sm text-white/80">
            تعذر تحميل الفيديو.
          </div>
        ) : null}

        {(!featured || featured.type === 'IMAGE' || featured.type === 'WEBSITE_LINK' || featured.type === 'EXTERNAL_PROJECT_LINK') &&
        !videoError ? (
          <button
            type="button"
            className="block w-full"
            onClick={() => {
              const src = resolveMediaUrl(featured?.url || item.cover_url || item.image_url)
              if (src) setActiveImage(src)
            }}
          >
            <img
              src={resolveMediaUrl(featured?.url || item.cover_url || item.image_url) || undefined}
              alt={item.title}
              className="max-h-[70vh] w-full object-cover"
              loading="eager"
            />
          </button>
        ) : null}
      </div>

      {projectUrl ? (
        <a
          href={projectUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#315CFF] px-5 text-sm font-medium text-white"
        >
          زيارة المشروع
        </a>
      ) : null}

      {images.length > 1 ? (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
          {images.map((image) => {
            const src = resolveMediaUrl(image.url || image.thumbnail_url)
            if (!src) return null
            return (
              <li key={image.id ?? src}>
                <button
                  type="button"
                  className="overflow-hidden rounded-2xl border border-slate-200"
                  onClick={() => setActiveImage(src)}
                >
                  <img src={src} alt={image.title || item.title} className="h-28 w-full object-cover" loading="lazy" />
                </button>
              </li>
            )
          })}
        </ul>
      ) : null}

      {activeImage ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          role="dialog"
          aria-modal="true"
          onClick={() => setActiveImage(null)}
        >
          <img src={activeImage} alt={item.title} className="max-h-full max-w-full rounded-xl object-contain" />
        </div>
      ) : null}
    </div>
  )
}

export function PortfolioDetailPage() {
  const { slug = '' } = useParams()
  const loader = useMemo(() => () => getPublicPortfolioDetail(slug), [slug])
  const { state } = useAsyncData(loader, [slug])

  if (state.status === 'loading') {
    return (
      <div className="mx-auto max-w-5xl px-4 py-16 text-sm text-slate-600">جاري تحميل المشروع...</div>
    )
  }

  if (state.status === 'error' || !state.data) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center">
        <BrandLogo size="auth" className="mx-auto mb-6" />
        <h1 className="text-xl font-semibold text-[#111318]">المشروع غير متاح</h1>
        <p className="mt-2 text-sm text-slate-600">قد يكون غير منشور أو الرابط غير صحيح.</p>
        <Link to="/portfolio" className="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[#315CFF] px-5 text-sm text-white">
          العودة إلى أعمالنا
        </Link>
      </div>
    )
  }

  const item = state.data
  const media = item.media ?? []
  const services = item.services ?? []
  const deliverables = item.deliverables ?? []
  const related = item.related ?? []
  const cta = item.cta

  return (
    <div className="-mx-4 -my-8 bg-[#F7F5EF] sm:-mx-6 sm:-my-10">
      <div className="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6 sm:py-14">
        <div className="space-y-3">
          <Link to="/portfolio" className="text-sm text-[#315CFF]">
            ← أعمالنا
          </Link>
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-white px-3 py-1 text-xs text-slate-700">
              {CATEGORY_LABELS[item.category]}
            </span>
            {(item.sectors ?? []).map((sector) => (
              <Link
                key={sector.id}
                to={`/sectors/${sector.slug}`}
                className="rounded-full bg-white px-3 py-1 text-xs text-slate-700"
              >
                {sector.name}
              </Link>
            ))}
          </div>
          <h1 className="text-3xl font-semibold text-[#111318] sm:text-4xl">{item.title}</h1>
          {item.short_description || item.description ? (
            <p className="max-w-3xl text-base leading-8 text-slate-700">
              {item.short_description || item.description}
            </p>
          ) : null}
        </div>

        <MediaStage item={item} media={media} />

        <div className="grid gap-4 lg:grid-cols-2">
          {item.challenge ? (
            <CaseBlock title="التحدي">
              <p className="whitespace-pre-wrap">{item.challenge}</p>
            </CaseBlock>
          ) : null}
          {item.solution ? (
            <CaseBlock title="الحل">
              <p className="whitespace-pre-wrap">{item.solution}</p>
            </CaseBlock>
          ) : null}
          {item.execution ? (
            <CaseBlock title="ما تم تنفيذه">
              <p className="whitespace-pre-wrap">{item.execution}</p>
            </CaseBlock>
          ) : null}
          {deliverables.length > 0 ? (
            <CaseBlock title="المخرجات">
              <ul className="list-disc space-y-1 pr-5">
                {deliverables.map((row) => (
                  <li key={row}>{row}</li>
                ))}
              </ul>
            </CaseBlock>
          ) : null}
        </div>

        {item.results ? (
          <CaseBlock title="النتائج">
            <p className="whitespace-pre-wrap">{item.results}</p>
          </CaseBlock>
        ) : null}

        {services.length > 0 ? (
          <CaseBlock title="الخدمات التي استُخدمت في هذا المشروع">
            <ul className="flex flex-wrap gap-2">
              {services.map((service) => (
                <li key={service.id}>
                  <Link
                    to={`/build-package?services=${encodeURIComponent(service.slug)}`}
                    className="inline-flex min-h-10 items-center rounded-full border border-slate-200 bg-white px-4 text-sm text-[#111318]"
                  >
                    {service.name}
                  </Link>
                </li>
              ))}
            </ul>
          </CaseBlock>
        ) : null}

        {item.package ? (
          <CaseBlock title="حل مناسب لمشروع مشابه">
            <Link to={`/packages?highlight=${encodeURIComponent(item.package.slug)}`} className="font-medium text-[#315CFF]">
              {item.package.name}
            </Link>
            {item.package.summary ? <p className="mt-2">{item.package.summary}</p> : null}
          </CaseBlock>
        ) : null}

        <section className="rounded-3xl border border-[#315CFF]/20 bg-white p-5 sm:p-6">
          <h2 className="text-xl font-semibold text-[#111318]">أبغى شيء مشابه</h2>
          <p className="mt-2 text-sm leading-7 text-slate-600">
            نفتح لك مسار التصميم أو التسعير مع سياق هذا المشروع — بدون نسخ أسعار قديمة.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            {cta?.similar_path ? (
              <Link
                to={cta.similar_path}
                className="inline-flex min-h-11 items-center rounded-xl bg-[#315CFF] px-5 text-sm font-medium text-white"
              >
                أبغى شيء مشابه
              </Link>
            ) : null}
            {cta?.show_quote_cta && cta.quote_path ? (
              <Link
                to={cta.quote_path}
                className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-medium text-[#111318]"
              >
                اطلب عرض سعر لمشروع مشابه
              </Link>
            ) : null}
            {cta?.consultant_path ? (
              <Link to={cta.consultant_path} className="inline-flex min-h-11 items-center text-sm text-[#315CFF]">
                أو ابدأ مع المستشار
              </Link>
            ) : null}
          </div>
        </section>

        {related.length > 0 ? (
          <section className="space-y-4">
            <h2 className="text-xl font-semibold text-[#111318]">أعمال مشابهة</h2>
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((row) => (
                <li key={row.id}>
                  <Link
                    to={`/portfolio/${row.slug || row.id}`}
                    className="block overflow-hidden rounded-3xl border border-slate-200 bg-white"
                  >
                    <img
                      src={resolveMediaUrl(row.cover_url || row.image_url) || undefined}
                      alt={row.title}
                      className="h-40 w-full object-cover"
                      loading="lazy"
                    />
                    <div className="space-y-1 p-4">
                      <p className="text-xs text-slate-500">{CATEGORY_LABELS[row.category]}</p>
                      <h3 className="font-medium text-[#111318]">{row.title}</h3>
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </div>
  )
}
