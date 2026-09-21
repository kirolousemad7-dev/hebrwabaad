import { type ReactNode, useEffect, useMemo } from 'react'
import { useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../catalog/CatalogStatus'
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

  const { state, reload } = useAsyncData(async () => {
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

    return <CatalogErrorState message={`تعذر تحميل الصفحة. ${state.message}`} onRetry={() => void reload()} />
  }

  const page = state.data
  if (!page) {
    if (softFail) {
      return <div className="space-y-8">{children}</div>
    }

    return (
      <CatalogEmptyState
        title="الصفحة غير متاحة"
        description="قد تكون الصفحة مسودة أو غير منشورة بعد."
        actions={[{ to: '/', label: 'الرئيسية', variant: 'primary' }]}
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

  return (
    <div className="space-y-10">
      <CmsPageSeo page={page} />
      <PageHero
        eyebrow={isAbout ? 'من نحن' : isContact ? 'تواصل' : 'صفحات المنصة'}
        title={page.title}
        description={page.meta_description}
        tone={isAbout ? 'ink' : 'paper'}
      />

      <div className={showToc ? 'grid gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]' : ''}>
        <AnimatedSection>
          <article
            className="rounded-3xl border border-brand-ink-100 bg-white px-5 py-8 shadow-sm sm:px-8"
            dir="rtl"
          >
            {contentHasHeading ? null : (
              <h2 className="mb-6 text-2xl font-semibold text-brand-ink-900">{page.title}</h2>
            )}
            <div
              className="cms-content max-w-none text-base leading-8 text-brand-ink-700 [&_a]:font-medium [&_a]:text-brand-cobalt-700 [&_a]:underline-offset-4 hover:[&_a]:underline [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-brand-ink-900 [&_h2]:mt-10 [&_h2]:scroll-mt-28 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-brand-ink-900 [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-3 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:ps-6"
              dangerouslySetInnerHTML={{ __html: contentHtml }}
            />
          </article>
        </AnimatedSection>

        {showToc ? (
          <aside className="hidden lg:block">
            <nav
              aria-label="أقسام الصفحة"
              className="sticky top-24 space-y-3 rounded-2xl border border-brand-ink-100 bg-white p-4 shadow-sm"
            >
              <p className="text-xs font-semibold text-brand-ink-500">في هذه الصفحة</p>
              <ul className="space-y-2 text-sm">
                {headings.map((heading) => (
                  <li key={heading.id}>
                    <a
                      href={`#${heading.id}`}
                      className="block rounded-lg px-2 py-1.5 text-brand-ink-500 transition hover:bg-brand-cobalt-100 hover:text-brand-cobalt-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                      onClick={(event) => {
                        event.preventDefault()
                        document.getElementById(heading.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
                      }}
                    >
                      {heading.text}
                    </a>
                  </li>
                ))}
              </ul>
            </nav>
          </aside>
        ) : null}
      </div>

      {children ? <AnimatedSection delay={0.05}>{children}</AnimatedSection> : null}
    </div>
  )
}
