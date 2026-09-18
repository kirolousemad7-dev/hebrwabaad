import { FormEvent, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { getPublicBlog, type BlogPost } from '../services/blog'
import { describeApiError } from '../utils/errors'
import { resolveMediaUrl } from '../utils/mediaUrl'

export function BlogPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [items, setItems] = useState<BlogPost[]>([])
  const [categories, setCategories] = useState<Array<{ name: string; slug: string; posts_count?: number }>>([])
  const [tags, setTags] = useState<Array<{ name: string; slug: string }>>([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [query, setQuery] = useState(searchParams.get('q') || '')
  const page = Number(searchParams.get('page') || '1')
  const tag = searchParams.get('tag') || undefined

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getPublicBlog({
        page,
        q: searchParams.get('q') || undefined,
        tag,
        per_page: 12,
      })
      setItems(response.data.items ?? [])
      setMeta({
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page,
        total: response.data.meta.total,
      })
      setCategories(response.data.filters?.categories ?? [])
      setTags(response.data.filters?.tags ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المدونة.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, tag, searchParams.get('q')])

  function applySearch(event: FormEvent) {
    event.preventDefault()
    const next = new URLSearchParams()
    if (query.trim()) next.set('q', query.trim())
    if (tag) next.set('tag', tag)
    setSearchParams(next)
  }

  return (
    <div className="space-y-8">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'المدونة', to: '/blog' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-3xl font-semibold text-slate-900">المدونة</h1>
        <p className="max-w-2xl text-sm leading-7 text-slate-600">مقالات ونصائح من فريق حبر وأبعاد حول الهوية، التسويق، الطباعة والفعاليات.</p>
        <form onSubmit={applySearch} className="flex max-w-xl gap-2">
          <input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="ابحث في المقالات…"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
          <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
            بحث
          </button>
        </form>
      </header>

      <div className="grid gap-8 lg:grid-cols-[1fr_16rem]">
        <div className="space-y-6">
          {loading ? <CatalogSkeleton variant="list" label="جاري تحميل المدونة..." /> : null}
          {!loading && error ? <CatalogErrorState message={error} onRetry={() => void load()} /> : null}
          {!loading && !error && items.length === 0 ? (
            <CatalogEmptyState title="لا مقالات منشورة" description="عد لاحقاً لمحتوى جديد من الفريق." />
          ) : null}
          {!loading && items.length > 0 ? (
            <div className="grid gap-5 sm:grid-cols-2">
              {items.map((post) => (
                <article key={post.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                  {post.featured_image ? (
                    <img src={resolveMediaUrl(post.featured_image) || post.featured_image} alt="" className="h-44 w-full object-cover" />
                  ) : (
                    <div className="h-44 bg-gradient-to-br from-slate-100 to-slate-200" />
                  )}
                  <div className="space-y-2 p-4">
                    {post.category ? (
                      <Link to={`/blog/category/${post.category.slug}`} className="text-xs font-medium text-[#315CFF]">
                        {post.category.name}
                      </Link>
                    ) : null}
                    <h2 className="text-lg font-semibold text-slate-900">
                      <Link to={`/blog/${post.slug}`} className="hover:underline">
                        {post.title}
                      </Link>
                    </h2>
                    <p className="line-clamp-3 text-sm leading-6 text-slate-600">{post.excerpt}</p>
                    <p className="text-xs text-slate-400">
                      {post.author?.name || 'حبر وأبعاد'}
                      {post.published_at ? ` · ${new Date(post.published_at).toLocaleDateString('ar-SA')}` : ''}
                    </p>
                  </div>
                </article>
              ))}
            </div>
          ) : null}

          {meta.last_page > 1 ? (
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
                صفحة {meta.current_page} من {meta.last_page}
              </span>
              <button
                type="button"
                disabled={page >= meta.last_page}
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

        <aside className="space-y-6">
          <div>
            <h2 className="mb-2 text-sm font-semibold text-slate-900">التصنيفات</h2>
            <ul className="space-y-1 text-sm">
              {categories.map((category) => (
                <li key={category.slug}>
                  <Link to={`/blog/category/${category.slug}`} className="text-slate-600 hover:text-[#315CFF]">
                    {category.name}
                    {category.posts_count != null ? ` (${category.posts_count})` : ''}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
          <div>
            <h2 className="mb-2 text-sm font-semibold text-slate-900">الوسوم</h2>
            <div className="flex flex-wrap gap-2">
              {tags.map((item) => (
                <Link
                  key={item.slug}
                  to={`/blog?tag=${encodeURIComponent(item.slug)}`}
                  className="rounded-full border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:border-[#315CFF] hover:text-[#315CFF]"
                >
                  {item.name}
                </Link>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </div>
  )
}
