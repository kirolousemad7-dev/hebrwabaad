import { useEffect } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicBlogPost, type BlogPost } from '../services/blog'
import { APP_NAME } from '../utils/constants'
import { resolveMediaUrl } from '../utils/mediaUrl'
import { absoluteAssetUrl, siteOrigin } from '../utils/seo'

function BlogPostSeo({ post }: { post: BlogPost }) {
  useEffect(() => {
    const origin = siteOrigin(window.location.origin) || window.location.origin
    const title = post.seo?.title || post.seo_title || `${post.title} | ${APP_NAME}`
    const description = post.seo?.meta_description || post.meta_description || post.excerpt || `مقال من مدونة ${APP_NAME}`
    const canonical = post.seo?.canonical_url || post.canonical_url || `${origin}/blog/${post.slug}`
    const image = absoluteAssetUrl(post.seo?.og_image || post.og_image || post.featured_image, origin)

    document.title = title
    document.documentElement.lang = 'ar'
    document.querySelector('meta[name="description"]')?.setAttribute('content', description)

    let canonicalLink = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null
    if (!canonicalLink) {
      canonicalLink = document.createElement('link')
      canonicalLink.rel = 'canonical'
      document.head.append(canonicalLink)
    }
    canonicalLink.href = canonical

    document.querySelector('meta[property="og:type"]')?.setAttribute('content', 'article')
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', title)
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', description)
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical)
    if (image) {
      document.querySelector('meta[property="og:image"]')?.setAttribute('content', image)
      document.querySelector('meta[name="twitter:image"]')?.setAttribute('content', image)
    }
    document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', title)
    document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', description)

    const schema = {
      '@context': 'https://schema.org',
      '@type': 'BlogPosting',
      headline: post.title,
      description,
      datePublished: post.published_at,
      dateModified: post.updated_at || post.published_at,
      author: post.author
        ? { '@type': 'Person', name: post.author.name }
        : { '@type': 'Organization', name: APP_NAME },
      image: image || undefined,
      mainEntityOfPage: canonical,
    }
    document.getElementById('hebr-blog-jsonld')?.remove()
    const script = document.createElement('script')
    script.id = 'hebr-blog-jsonld'
    script.type = 'application/ld+json'
    script.textContent = JSON.stringify(schema)
    document.head.append(script)

    return () => {
      document.getElementById('hebr-blog-jsonld')?.remove()
    }
  }, [post])

  return null
}

export function BlogPostPage() {
  const { slug = '' } = useParams()
  const { state, reload } = useAsyncData(() => getPublicBlogPost(slug), [slug])

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل المقال..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل المقال. ${state.message}`} onRetry={() => void reload()} />
  }

  const post = state.data
  if (!post) {
    return <CatalogEmptyState title="المقال غير متاح" description="قد يكون المقال مسودة أو مؤرشفاً." />
  }

  const image = resolveMediaUrl(post.featured_image) || post.featured_image

  return (
    <article className="mx-auto max-w-3xl space-y-6">
      <BlogPostSeo post={post} />
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'المدونة', to: '/blog' },
          ...(post.category ? [{ name: post.category.name, to: `/blog/category/${post.category.slug}` }] : []),
          { name: post.title, to: `/blog/${post.slug}` },
        ]}
      />

      <header className="space-y-3">
        {post.category ? (
          <Link to={`/blog/category/${post.category.slug}`} className="text-sm font-medium text-[#315CFF]">
            {post.category.name}
          </Link>
        ) : null}
        <h1 className="text-3xl font-semibold text-slate-900">{post.title}</h1>
        <p className="text-sm text-slate-500">
          {post.author?.name || APP_NAME}
          {post.published_at ? ` · ${new Date(post.published_at).toLocaleDateString('ar-SA')}` : ''}
        </p>
        {post.excerpt ? <p className="text-base leading-7 text-slate-600">{post.excerpt}</p> : null}
      </header>

      {image ? <img src={image} alt="" className="w-full rounded-2xl object-cover" /> : null}

      <div className="whitespace-pre-wrap text-base leading-8 text-slate-800">{post.content}</div>

      {post.tags?.length ? (
        <div className="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
          {post.tags.map((tag) => (
            <Link key={tag.id} to={`/blog?tag=${encodeURIComponent(tag.slug)}`} className="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-700">
              {tag.name}
            </Link>
          ))}
        </div>
      ) : null}

      {post.related?.length ? (
        <section className="space-y-3 border-t border-slate-200 pt-6">
          <h2 className="text-lg font-semibold text-slate-900">مقالات ذات صلة</h2>
          <ul className="grid gap-3 sm:grid-cols-3">
            {post.related.map((related) => (
              <li key={related.id} className="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                <Link to={`/blog/${related.slug}`} className="font-medium text-[#315CFF] hover:underline">
                  {related.title}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </article>
  )
}
