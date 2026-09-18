import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { getPublicSeo } from '../../services/seo'
import { APP_NAME } from '../../utils/constants'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import {
  DEFAULT_PAGE_SEO,
  absoluteAssetUrl,
  absoluteUrl,
  buildPageSchema,
  isPrivateSeoPath,
  isIndexedCatalogPath,
  isSupplierPublicPath,
  seoKeyFromPath,
  siteOrigin,
  type SeoPage,
} from '../../utils/seo'

function upsertMeta(selector: string, attributes: Record<string, string>) {
  let element = document.head.querySelector(selector) as HTMLMetaElement | HTMLLinkElement | null

  if (!element) {
    const tag = selector.startsWith('link') ? 'link' : 'meta'
    element = document.createElement(tag)
    document.head.append(element)
  }

  Object.entries(attributes).forEach(([key, value]) => {
    element?.setAttribute(key, value)
  })
}

function upsertJsonLd(data: unknown) {
  document.getElementById('hebr-jsonld')?.remove()

  if (!data) {
    return
  }

  const script = document.createElement('script')
  script.id = 'hebr-jsonld'
  script.type = 'application/ld+json'
  script.textContent = JSON.stringify(data)
  document.head.append(script)
}

function applyTags(input: {
  title: string
  description: string
  robots: string
  canonical: string
  keywords?: string | null
  ogTitle: string
  ogDescription: string
  ogImage?: string
  twitterTitle: string
  twitterDescription: string
  twitterImage?: string
  schema: unknown
  siteName: string
}) {
  document.title = input.title
  document.documentElement.lang = 'ar'
  document.documentElement.dir = 'rtl'
  upsertMeta('meta[name="description"]', { name: 'description', content: input.description })
  upsertMeta('meta[name="robots"]', { name: 'robots', content: input.robots })
  if (input.keywords) {
    upsertMeta('meta[name="keywords"]', { name: 'keywords', content: input.keywords })
  }
  upsertMeta('link[rel="canonical"]', { rel: 'canonical', href: input.canonical })
  upsertMeta('meta[property="og:type"]', { property: 'og:type', content: 'website' })
  upsertMeta('meta[property="og:locale"]', { property: 'og:locale', content: 'ar_AR' })
  upsertMeta('meta[property="og:site_name"]', { property: 'og:site_name', content: input.siteName })
  upsertMeta('meta[property="og:title"]', { property: 'og:title', content: input.ogTitle })
  upsertMeta('meta[property="og:description"]', { property: 'og:description', content: input.ogDescription })
  upsertMeta('meta[property="og:url"]', { property: 'og:url', content: input.canonical })
  if (input.ogImage) {
    upsertMeta('meta[property="og:image"]', { property: 'og:image', content: input.ogImage })
  }
  upsertMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: 'summary_large_image' })
  upsertMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: input.twitterTitle })
  upsertMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: input.twitterDescription })
  if (input.twitterImage) {
    upsertMeta('meta[name="twitter:image"]', { name: 'twitter:image', content: input.twitterImage })
  }
  upsertJsonLd(input.schema)
}

export function SeoHead() {
  const location = useLocation()
  const pageKey = seoKeyFromPath(location.pathname)
  const { settings } = usePlatformSettings()
  const siteName = settings.seo.site_title || 'حبر وأبعاد | Hebr & Ab3ad'
  const defaultOg = resolveMediaUrl(settings.seo.default_og_image_url) || '/brand/logo.png'
  const robotsDefault = settings.seo.robots_index === false ? 'noindex,nofollow' : 'index,follow'

  useEffect(() => {
    const runtimeOrigin = window.location.origin
    const origin = siteOrigin(runtimeOrigin) || runtimeOrigin
    const canonical = absoluteUrl(location.pathname, origin)
    const privatePage = isPrivateSeoPath(location.pathname)
    const supplierPublic = isSupplierPublicPath(location.pathname)

    if (privatePage) {
      const authTitles: Record<string, string> = {
        '/login': `تسجيل الدخول | ${APP_NAME}`,
        '/register': `إنشاء حساب | ${APP_NAME}`,
        '/forgot-password': `استعادة كلمة المرور | ${APP_NAME}`,
        '/reset-password': `تعيين كلمة المرور | ${APP_NAME}`,
      }
      const privateTitle =
        authTitles[location.pathname] ||
        (location.pathname.startsWith('/owner')
          ? `لوحة المالك | ${APP_NAME}`
          : location.pathname.startsWith('/workspace')
            ? `مساحة العمل | ${APP_NAME}`
            : location.pathname.startsWith('/dashboard') || location.pathname.startsWith('/customer')
              ? `حسابي | ${APP_NAME}`
              : `${APP_NAME} | منطقة خاصة`)

      applyTags({
        title: privateTitle,
        description: 'منطقة خاصة داخل منصة حبر وأبعاد وغير مخصّصة للفهرسة.',
        robots: 'noindex,nofollow',
        canonical,
        ogTitle: APP_NAME,
        ogDescription: 'منطقة خاصة داخل منصة حبر وأبعاد.',
        twitterTitle: APP_NAME,
        twitterDescription: 'منطقة خاصة داخل منصة حبر وأبعاد.',
        schema: null,
        siteName,
      })
      return
    }

    if (supplierPublic || isIndexedCatalogPath(location.pathname)) {
      applyTags({
        title: supplierPublic ? `الموردون | ${APP_NAME}` : `${settings.seo.default_meta_title || siteName}`,
        description:
          settings.seo.default_meta_description ||
          'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف.',
        robots: 'index,follow',
        canonical,
        ogTitle: supplierPublic ? `الموردون | ${APP_NAME}` : settings.seo.default_meta_title || siteName,
        ogDescription:
          settings.seo.default_meta_description ||
          'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف.',
        twitterTitle: supplierPublic ? `الموردون | ${APP_NAME}` : settings.seo.default_meta_title || siteName,
        twitterDescription:
          settings.seo.default_meta_description ||
          'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف.',
        ogImage: absoluteAssetUrl(defaultOg, origin),
        twitterImage: absoluteAssetUrl(defaultOg, origin),
        schema: null,
        siteName,
      })
      return
    }

    if (!pageKey) {
      applyTags({
        title: `الصفحة غير موجودة | ${APP_NAME}`,
        description: 'الصفحة التي تبحث عنها غير موجودة.',
        robots: 'noindex,nofollow',
        canonical,
        ogTitle: APP_NAME,
        ogDescription: 'الصفحة التي تبحث عنها غير موجودة.',
        twitterTitle: APP_NAME,
        twitterDescription: 'الصفحة التي تبحث عنها غير موجودة.',
        schema: null,
        siteName,
      })
      return
    }

    const defaults = DEFAULT_PAGE_SEO[pageKey]
    let cancelled = false

    const applyDefaults = (seo?: SeoPage | null) => {
      const title = seo?.title || defaults.title || settings.seo.default_meta_title || siteName
      const description =
        seo?.description || defaults.description || settings.seo.default_meta_description || ''
      const ogImage = absoluteAssetUrl(seo?.og_image || defaultOg, origin)

      applyTags({
        title,
        description,
        robots: seo?.robots || defaults.robots || robotsDefault,
        canonical: seo?.canonical_url || canonical,
        keywords: seo?.keywords || defaults.keywords,
        ogTitle: seo?.og_title || title,
        ogDescription: seo?.og_description || description,
        ogImage,
        twitterTitle: seo?.twitter_title || seo?.og_title || title,
        twitterDescription: seo?.twitter_description || seo?.og_description || description,
        twitterImage: absoluteAssetUrl(seo?.twitter_image || seo?.og_image || defaultOg, origin),
        schema: buildPageSchema(pageKey, origin, defaults, seo?.schema_json),
        siteName,
      })
    }

    applyDefaults(null)

    getPublicSeo(pageKey)
      .then((response) => {
        if (!cancelled) {
          applyDefaults(response.data)
        }
      })
      .catch(() => {
        // Defaults already applied.
      })

    return () => {
      cancelled = true
    }
  }, [
    location.pathname,
    pageKey,
    siteName,
    defaultOg,
    robotsDefault,
    settings.seo.default_meta_title,
    settings.seo.default_meta_description,
  ])

  return null
}
