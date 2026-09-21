import { type ReactNode, useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogSkeleton } from '../catalog/CatalogStatus'
import { PageHero } from '../marketing/PageHero'
import { AnimatedSection } from '../marketing/AnimatedSection'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getPublicCmsPage, type CmsPage } from '../../services/cmsPages'
import { APP_NAME } from '../../utils/constants'
import { absoluteAssetUrl, siteOrigin } from '../../utils/seo'

type PublicCmsPageProps = {
  slug?: string
  children?: ReactNode
  /** When true, children still render if the CMS page is missing or fails to load. */
  softFail?: boolean
}

function CmsPageSeo({ page }: { page: CmsPage }) {
  useEffect(() => {
    const origin = siteOrigin(window.location.origin) || window.location.origin
    const title = page.meta_title || `${page.title} | ${APP_NAME}`
    const description = page.meta_description || `صفحة من ${APP_NAME}`
    const canonical = `${origin}${page.path || `/${page.slug}`}`
    const image = absoluteAssetUrl(page.og_image, origin)
    const ogTitle = page.og_title || title
    const ogDescription = page.og_description || description

    document.title = title
    document.documentElement.lang = 'ar'
    document.querySelector('meta[name="description"]')?.setAttribute('content', description)
    if (page.meta_keywords) {
      let keywordsMeta = document.head.querySelector('meta[name="keywords"]') as HTMLMetaElement | null
      if (!keywordsMeta) {
        keywordsMeta = document.createElement('meta')
        keywordsMeta.name = 'keywords'
        document.head.append(keywordsMeta)
      }
      keywordsMeta.content = page.meta_keywords
    }

    let canonicalLink = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null
    if (!canonicalLink) {
      canonicalLink = document.createElement('link')
      canonicalLink.rel = 'canonical'
      document.head.append(canonicalLink)
    }
    canonicalLink.href = canonical

    document.querySelector('meta[property="og:type"]')?.setAttribute('content', 'website')
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', ogTitle)
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', ogDescription)
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical)
    if (image) {
      document.querySelector('meta[property="og:image"]')?.setAttribute('content', image)
      document.querySelector('meta[name="twitter:image"]')?.setAttribute('content', image)
    }
    document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', ogTitle)
    document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', ogDescription)

    const schema = {
      '@context': 'https://schema.org',
      '@type': 'WebPage',
      name: page.title,
      description,
      url: canonical,
      dateModified: page.updated_at || undefined,
      isPartOf: { '@type': 'WebSite', name: APP_NAME, url: origin },
    }
    document.getElementById('hebr-cms-jsonld')?.remove()
    const script = document.createElement('script')
    script.id = 'hebr-cms-jsonld'
    script.type = 'application/ld+json'
    script.textContent = JSON.stringify(schema)
    document.head.append(script)

    return () => {
      document.getElementById('hebr-cms-jsonld')?.remove()
    }
  }, [page])

  return null
}

function extractHeadings(html: string): Array<{ id: string; text: string }> {
  const matches = html.matchAll(/<h2[^>]*>(.*?)<\/h2>/gis)
  const headings: Array<{ id: string; text: string }> = []
  let index = 0
  for (const match of matches) {
    const text = match[1].replace(/<[^>]+>/g, '').trim()
    if (!text) continue
    index += 1
    headings.push({ id: `cms-section-${index}`, text })
  }
  return headings
}

function injectHeadingIds(html: string): string {
  let index = 0
  return html.replace(/<h2([^>]*)>/gi, (_full, attrs: string) => {
    index += 1
    if (/id=/i.test(attrs)) {
      return `<h2${attrs}>`
    }
    return `<h2${attrs} id="cms-section-${index}">`
  })
}

export function PublicCmsPage({ slug: slugProp, children, softFail = false }: PublicCmsPageProps) {
  const { slug: paramSlug = '' } = useParams()
  const slug = (slugProp || paramSlug).trim()

  const { state } = useAsyncData(async () => {
    if (!slug) {
      return { data: null as CmsPage | null }
    }

    try {
      return await getPublicCmsPage(slug)
    } catch (caught) {
      if (caught instanceof ApiRequestError && caught.status === 404) {
        return { data: null as CmsPage | null }
      }
      throw caught
    }
  }, [slug])

  if (state.status === 'loading') {
    return (
      <div className="space-y-8">
        <CatalogSkeleton variant="list" label="جاري تحميل الصفحة..." />
        {softFail ? children : null}
      </div>
    )
  }

  if (state.status === 'error') {
    if (softFail) {
      return <div className="space-y-8">{children}</div>
    }

    return (
      <CatalogEmptyState
        title="المحتوى غير متاح حاليًا"
        description="تعذر تحميل هذه الصفحة الآن. يمكنك المحاولة لاحقًا أو العودة للرئيسية."
        actions={[
          { to: '/', label: 'الرئيسية', variant: 'primary' },
          { to: '/contact', label: 'تواصل معنا', variant: 'secondary' },
        ]}
      />
    )
  }

  const page = state.data
  if (!page) {
    if (softFail) {
      return <div className="space-y-8">{children}</div>
    }

    return (
      <CatalogEmptyState
        title="الصفحة غير متاحة"
        description="المحتوى غير متاح حاليًا. قد تكون الصفحة مسودة أو غير منشورة بعد."
        actions={[
          { to: '/', label: 'الرئيسية', variant: 'primary' },
          { to: '/contact', label: 'تواصل معنا', variant: 'secondary' },
        ]}
      />
    )
  }

  return <CmsPageBody page={page}>{children}</CmsPageBody>
}

function CmsPageBody({
  page,
  children,
}: {
  page: CmsPage
  children?: ReactNode
}) {
  const contentHasHeading = /<h1[\s>]/i.test(page.content)
  const headings = useMemo(() => extractHeadings(page.content), [page.content])
  const contentHtml = useMemo(() => injectHeadingIds(page.content), [page.content])
  const isContact = page.slug === 'contact'
  const isAbout = page.slug === 'about'
  const showToc = headings.length >= 3 && !isContact
  const [activeHeading, setActiveHeading] = useState(headings[0]?.id ?? '')

  useEffect(() => {
    if (!showToc) return
    const nodes = headings
      .map((heading) => document.getElementById(heading.id))
      .filter((node): node is HTMLElement => node !== null)
    if (nodes.length === 0) return

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
        if (visible?.target.id) {
          setActiveHeading(visible.target.id)
        }
      },
      { rootMargin: '-25% 0px -55% 0px', threshold: [0.2, 0.45, 0.7] },
    )
    nodes.forEach((node) => observer.observe(node))
    return () => observer.disconnect()
  }, [showToc, headings])

  return (
    <div className="space-y-12">
      <CmsPageSeo page={page} />
      <PageHero
        eyebrow={isAbout ? 'من نحن' : isContact ? 'تواصل' : 'صفحات المنصة'}
        title={page.title}
        description={page.meta_description}
        tone={isAbout ? 'ink' : 'paper'}
        breadcrumbs={[
          { label: 'الرئيسية', to: '/' },
          { label: page.title },
        ]}
      />

      <div className={showToc ? 'grid gap-10 lg:grid-cols-[minmax(0,1fr)_15rem]' : ''}>
        <AnimatedSection>
          <article className="max-w-3xl" dir="rtl">
            {contentHasHeading ? null : (
              <h2 className="mb-8 text-2xl font-semibold text-brand-ink-900">{page.title}</h2>
            )}
            <div
              className="cms-content max-w-none text-base leading-9 text-brand-ink-700 [&_a]:font-medium [&_a]:text-brand-cobalt-700 [&_a]:underline-offset-4 hover:[&_a]:underline [&_h1]:mb-5 [&_h1]:text-[clamp(1.75rem,1.4rem+1.2vw,2.5rem)] [&_h1]:font-bold [&_h1]:leading-tight [&_h1]:text-brand-ink-900 [&_h2]:mt-12 [&_h2]:scroll-mt-28 [&_h2]:border-b [&_h2]:border-brand-ink-100 [&_h2]:pb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-brand-ink-900 [&_h3]:mt-8 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:my-1.5 [&_ol]:my-5 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-4 [&_ul]:my-5 [&_ul]:list-disc [&_ul]:ps-6"
              dangerouslySetInnerHTML={{ __html: contentHtml }}
            />
          </article>
        </AnimatedSection>

        {showToc ? (
          <aside className="hidden lg:block">
            <nav
              aria-label="أقسام الصفحة"
              className="sticky top-24 space-y-3 border-s border-brand-ink-100 ps-4"
            >
              <p className="text-xs font-semibold tracking-wide text-brand-ink-500">في هذه الصفحة</p>
              <ul className="space-y-1 text-sm">
                {headings.map((heading) => {
                  const active = activeHeading === heading.id
                  return (
                    <li key={heading.id}>
                      <a
                        href={`#${heading.id}`}
                        aria-current={active ? 'true' : undefined}
                        className={[
                          'block rounded-lg border-s-2 px-2 py-1.5 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
                          active
                            ? 'border-brand-cobalt-500 bg-brand-paper font-medium text-brand-ink-900'
                            : 'border-transparent text-brand-ink-500 hover:bg-brand-paper hover:text-brand-ink-900',
                        ].join(' ')}
                        onClick={(event) => {
                          event.preventDefault()
                          document.getElementById(heading.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
                          setActiveHeading(heading.id)
                        }}
                      >
                        {heading.text}
                      </a>
                    </li>
                  )
                })}
              </ul>
            </nav>
          </aside>
        ) : null}
      </div>

      {children ? <AnimatedSection>{children}</AnimatedSection> : null}
    </div>
  )
}
