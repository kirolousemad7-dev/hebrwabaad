import { type ReactNode, useEffect } from 'react'
import { useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../catalog/CatalogStatus'
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

  const contentHasHeading = /<h1[\s>]/i.test(page.content)

  return (
    <div className="space-y-10">
      <article className="prose prose-slate mx-auto max-w-3xl space-y-6 text-start" dir="rtl">
        <CmsPageSeo page={page} />
        {contentHasHeading ? null : (
          <header className="not-prose space-y-2">
            <h1 className="text-3xl font-semibold text-slate-900">{page.title}</h1>
          </header>
        )}
        <div
          className="cms-content text-base leading-8 text-slate-800 [&_a]:text-[#315CFF] [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-slate-900 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-3 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:ps-6"
          dangerouslySetInnerHTML={{ __html: page.content }}
        />
      </article>
      {children}
    </div>
  )
}
