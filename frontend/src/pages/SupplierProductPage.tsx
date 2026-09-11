import { Link, useParams } from 'react-router-dom'
import { useEffect } from 'react'
import { CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { useAsyncData } from '../hooks/useAsyncData'
import { apiGet } from '../services/api'
import { APP_NAME } from '../utils/constants'
import { absoluteAssetUrl, siteOrigin } from '../utils/seo'

type PublicProduct = {
  name: string
  slug: string
  short_description: string | null
  description: string | null
  images: string[]
  category: string | null
  specifications: Record<string, unknown> | unknown[] | null
  price: string | null
  currency: string
  contact_for_price: boolean
  seo?: { title: string | null; description: string | null; robots: string; og_image: string | null; canonical_url: string | null }
}

export function SupplierProductPage() {
  const { slug = '', productSlug = '' } = useParams()
  const { state, reload } = useAsyncData(() => apiGet<PublicProduct>(`/api/suppliers/${encodeURIComponent(slug)}/products/${encodeURIComponent(productSlug)}`))

  useEffect(() => {
    if (state.status !== 'ready') return
    const origin = siteOrigin(window.location.origin) || window.location.origin
    const product = state.data
    const title = product.seo?.title || `${product.name} | ${APP_NAME}`
    const description = product.seo?.description || product.short_description || APP_NAME
    const robots = product.seo?.robots || 'index,follow'
    const canonical = product.seo?.canonical_url || `${origin}/suppliers/${slug}/products/${productSlug}`
    document.title = title
    document.documentElement.lang = 'ar'
    document.querySelector('meta[name="description"]')?.setAttribute('content', description)
    document.querySelector('meta[name="robots"]')?.setAttribute('content', robots)
    let canonicalLink = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null
    if (!canonicalLink) {
      canonicalLink = document.createElement('link')
      canonicalLink.rel = 'canonical'
      document.head.append(canonicalLink)
    }
    canonicalLink.href = canonical
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', title)
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', description)
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical)
    const image = absoluteAssetUrl(product.seo?.og_image || product.images[0], origin)
    if (image) {
      document.querySelector('meta[property="og:image"]')?.setAttribute('content', image)
      document.querySelector('meta[name="twitter:image"]')?.setAttribute('content', image)
    }
    document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', title)
    document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', description)
  }, [state, slug, productSlug])

  if (state.status === 'loading') return <CatalogSkeleton label="جاري تحميل المنتج..." />
  if (state.status === 'error') {
    return <CatalogErrorState message={state.message} onRetry={() => void reload()} />
  }

  const product = state.data

  return (
    <article className="space-y-6">
      <p className="text-sm"><Link to={`/suppliers/${encodeURIComponent(slug)}`} className="underline">العودة لملف المورد</Link></p>
      <h1 className="text-3xl font-semibold">{product.name}</h1>
      {product.images[0] ? <img src={product.images[0]} alt="" className="max-h-96 w-full rounded-3xl object-cover" /> : null}
      <p className="leading-8 text-slate-600">{product.description || product.short_description}</p>
      <p className="font-medium">{product.contact_for_price ? 'السعر عند التواصل' : `${product.price} ${product.currency}`}</p>
    </article>
  )
}
