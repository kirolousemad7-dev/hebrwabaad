import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicBlogCategory } from '../services/blog'
import { resolveMediaUrl } from '../utils/mediaUrl'

export function BlogCategoryPage() {
  const { slug = '' } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') || '1')
  const [query, setQuery] = useState(searchParams.get('q') || '')

  const { state, reload } = useAsyncData(
    () => getPublicBlogCategory(slug, { page, q: searchParams.get('q') || undefined }),
    [slug, page, searchParams.get('q')],
  )

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل التصنيف..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل التصنيف. ${state.message}`} onRetry={() => void reload()} />
  }

  const data = state.data
  if (!data) {
    return <CatalogEmptyState title="التصنيف غير موجود" description="تحقق من الرابط أو عد إلى المدونة." />
  }

  return (
    <div className="space-y-8">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'المدونة', to: '/blog' },
          { name: data.category.name, to: `/blog/category/${data.category.slug}` },
        ]}
      />
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-slate-900">{data.category.name}</h1>
        {data.category.description ? <p className="text-sm text-slate-600">{data.category.description}</p> : null}
        <form
          className="flex max-w-xl gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            const next = new URLSearchParams()
            if (query.trim()) next.set('q', query.trim())
            setSearchParams(next)
          }}
        >
          <input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="بحث داخل التصنيف…"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
          <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
            بحث
          </button>
        </form>
      </header>

      {data.items.length === 0 ? (
        <CatalogEmptyState title="لا مقالات في هذا التصنيف" description="جرّب تصنيفاً آخر من صفحة المدونة." />
      ) : (
        <div className="grid gap-5 sm:grid-cols-2">
          {data.items.map((post) => (
            <article key={post.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              {post.featured_image ? (
                <img src={resolveMediaUrl(post.featured_image) || post.featured_image} alt="" className="h-40 w-full object-cover" />
              ) : (
                <div className="h-40 bg-slate-100" />
              )}
              <div className="space-y-2 p-4">
                <h2 className="text-lg font-semibold">
                  <Link to={`/blog/${post.slug}`} className="hover:underline">
                    {post.title}
                  </Link>
                </h2>
                <p className="line-clamp-3 text-sm text-slate-600">{post.excerpt}</p>
              </div>
            </article>
          ))}
        </div>
      )}

      {data.meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm">
          <button
            type="button"
            disabled={page <= 1}
            className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
            onClick={() => {
              const next = new URLSearchParams(searchParams)
              next.set('page', String(page - 1))
              setSearchParams(next)
            }}
          >
            السابق
          </button>
          <span>
            صفحة {data.meta.current_page} من {data.meta.last_page}
          </span>
          <button
            type="button"
            disabled={page >= data.meta.last_page}
            className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
            onClick={() => {
              const next = new URLSearchParams(searchParams)
              next.set('page', String(page + 1))
              setSearchParams(next)
            }}
          >
            التالي
          </button>
        </div>
      ) : null}
    </div>
  )
}
