import { FormEvent, useEffect, useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createAdminBlogAuthor,
  createAdminBlogCategory,
  createAdminBlogPost,
  createAdminBlogTag,
  deleteAdminBlogAuthor,
  deleteAdminBlogCategory,
  deleteAdminBlogPost,
  deleteAdminBlogTag,
  getAdminBlogAuthors,
  getAdminBlogCategories,
  getAdminBlogPosts,
  getAdminBlogTags,
  updateAdminBlogAuthor,
  updateAdminBlogCategory,
  updateAdminBlogPost,
  updateAdminBlogTag,
  type BlogAuthor,
  type BlogCategory,
  type BlogPost,
  type BlogTag,
  type UpsertBlogPostPayload,
} from '../../services/blog'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const STATUS_OPTIONS = [
  { value: 'DRAFT', label: 'مسودة' },
  { value: 'SCHEDULED', label: 'مجدول' },
  { value: 'PUBLISHED', label: 'منشور' },
  { value: 'ARCHIVED', label: 'مؤرشف' },
]

type Tab = 'posts' | 'categories' | 'tags' | 'authors'

type PostForm = {
  id: number | null
  title: string
  slug: string
  excerpt: string
  content: string
  featured_image: string
  author_id: string
  category_id: string
  tag_ids: number[]
  status: string
  published_at: string
  seo_title: string
  meta_description: string
  og_image: string
  canonical_url: string
}

const emptyPost = (): PostForm => ({
  id: null,
  title: '',
  slug: '',
  excerpt: '',
  content: '',
  featured_image: '',
  author_id: '',
  category_id: '',
  tag_ids: [],
  status: 'DRAFT',
  published_at: '',
  seo_title: '',
  meta_description: '',
  og_image: '',
  canonical_url: '',
})

export function OwnerBlogPage() {
  const [tab, setTab] = useState<Tab>('posts')
  const [posts, setPosts] = useState<BlogPost[]>([])
  const [categories, setCategories] = useState<BlogCategory[]>([])
  const [tags, setTags] = useState<BlogTag[]>([])
  const [authors, setAuthors] = useState<BlogAuthor[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState('')
  const [query, setQuery] = useState('')
  const [postForm, setPostForm] = useState<PostForm>(emptyPost())
  const [categoryForm, setCategoryForm] = useState({ id: null as number | null, name: '', slug: '', description: '', is_active: true })
  const [tagForm, setTagForm] = useState({ id: null as number | null, name: '', slug: '' })
  const [authorForm, setAuthorForm] = useState({
    id: null as number | null,
    name: '',
    slug: '',
    bio: '',
    avatar_url: '',
    email: '',
    is_active: true,
  })
  const [saving, setSaving] = useState(false)

  async function loadAll() {
    setLoading(true)
    setError(null)
    try {
      const [postsRes, categoriesRes, tagsRes, authorsRes] = await Promise.all([
        getAdminBlogPosts({ status: statusFilter || undefined, q: query.trim() || undefined }),
        getAdminBlogCategories(),
        getAdminBlogTags(),
        getAdminBlogAuthors(),
      ])
      setPosts(postsRes.data.items ?? [])
      setCategories(categoriesRes.data ?? [])
      setTags(tagsRes.data ?? [])
      setAuthors(authorsRes.data ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المدونة.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadAll()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter])

  const tabs = useMemo(
    () =>
      [
        { id: 'posts' as const, label: `المقالات (${posts.length})` },
        { id: 'categories' as const, label: `التصنيفات (${categories.length})` },
        { id: 'tags' as const, label: `الوسوم (${tags.length})` },
        { id: 'authors' as const, label: `الكتّاب (${authors.length})` },
      ] as const,
    [authors.length, categories.length, posts.length, tags.length],
  )

  function editPost(post: BlogPost) {
    setPostForm({
      id: post.id,
      title: post.title,
      slug: post.slug,
      excerpt: post.excerpt || '',
      content: post.content || '',
      featured_image: post.featured_image || '',
      author_id: post.author_id ? String(post.author_id) : post.author ? String(post.author.id) : '',
      category_id: post.category_id ? String(post.category_id) : post.category ? String(post.category.id) : '',
      tag_ids: post.tag_ids ?? post.tags?.map((tag) => tag.id) ?? [],
      status: post.status,
      published_at: post.published_at ? post.published_at.slice(0, 16) : '',
      seo_title: post.seo_title || post.seo?.title || '',
      meta_description: post.meta_description || post.seo?.meta_description || '',
      og_image: post.og_image || post.seo?.og_image || '',
      canonical_url: post.canonical_url || post.seo?.canonical_url || '',
    })
    setTab('posts')
    setMessage(null)
  }

  async function savePost(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setMessage(null)
    const payload: UpsertBlogPostPayload = {
      title: postForm.title.trim(),
      slug: postForm.slug.trim() || null,
      excerpt: postForm.excerpt.trim() || null,
      content: postForm.content,
      featured_image: postForm.featured_image.trim() || null,
      author_id: postForm.author_id ? Number(postForm.author_id) : null,
      category_id: postForm.category_id ? Number(postForm.category_id) : null,
      tag_ids: postForm.tag_ids,
      status: postForm.status,
      published_at: postForm.published_at ? new Date(postForm.published_at).toISOString() : null,
      seo_title: postForm.seo_title.trim() || null,
      meta_description: postForm.meta_description.trim() || null,
      og_image: postForm.og_image.trim() || null,
      canonical_url: postForm.canonical_url.trim() || null,
    }
    try {
      if (postForm.id) {
        await updateAdminBlogPost(postForm.id, payload)
        setMessage('تم تحديث المقال.')
      } else {
        await createAdminBlogPost(payload)
        setMessage('تم إنشاء المقال.')
      }
      setPostForm(emptyPost())
      await loadAll()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ المقال.'))
    } finally {
      setSaving(false)
    }
  }

  async function removePost(id: number) {
    if (!window.confirm('حذف هذا المقال؟')) return
    try {
      await deleteAdminBlogPost(id)
      if (postForm.id === id) setPostForm(emptyPost())
      setMessage('تم حذف المقال.')
      await loadAll()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف المقال.'))
    }
  }

  async function saveCategory(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = {
        name: categoryForm.name.trim(),
        slug: categoryForm.slug.trim() || undefined,
        description: categoryForm.description.trim() || null,
        is_active: categoryForm.is_active,
      }
      if (categoryForm.id) {
        await updateAdminBlogCategory(categoryForm.id, payload)
      } else {
        await createAdminBlogCategory(payload)
      }
      setCategoryForm({ id: null, name: '', slug: '', description: '', is_active: true })
      setMessage('تم حفظ التصنيف.')
      await loadAll()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ التصنيف.'))
    } finally {
      setSaving(false)
    }
  }

  async function saveTag(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = { name: tagForm.name.trim(), slug: tagForm.slug.trim() || undefined }
      if (tagForm.id) await updateAdminBlogTag(tagForm.id, payload)
      else await createAdminBlogTag(payload)
      setTagForm({ id: null, name: '', slug: '' })
      setMessage('تم حفظ الوسم.')
      await loadAll()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الوسم.'))
    } finally {
      setSaving(false)
    }
  }

  async function saveAuthor(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = {
        name: authorForm.name.trim(),
        slug: authorForm.slug.trim() || undefined,
        bio: authorForm.bio.trim() || null,
        avatar_url: authorForm.avatar_url.trim() || null,
        email: authorForm.email.trim() || null,
        is_active: authorForm.is_active,
      }
      if (authorForm.id) await updateAdminBlogAuthor(authorForm.id, payload)
      else await createAdminBlogAuthor(payload)
      setAuthorForm({ id: null, name: '', slug: '', bio: '', avatar_url: '', email: '', is_active: true })
      setMessage('تم حفظ الكاتب.')
      await loadAll()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الكاتب.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">إدارة المدونة</h1>
        <p className="mt-1 text-sm text-slate-600">مقالات، تصنيفات، وسوم، كتّاب، مسودات وجدولة ونشر مع حقول SEO.</p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}

      <div className="flex flex-wrap gap-2">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={`rounded-lg px-3 py-2 text-sm font-medium ${
              tab === item.id ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل المدونة" /> : null}

      {!loading && tab === 'posts' ? (
        <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <DashboardSection title="المقالات">
            <form
              className="mb-4 flex flex-wrap gap-2"
              onSubmit={(event) => {
                event.preventDefault()
                void loadAll()
              }}
            >
              <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="بحث…" className={`${fieldClass} max-w-xs`} />
              <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className={`${fieldClass} max-w-[10rem]`}>
                <option value="">كل الحالات</option>
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <button type="submit" className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white">
                تصفية
              </button>
            </form>
            {posts.length === 0 ? (
              <DashboardEmptyState title="لا مقالات بعد" description="أنشئ أول مقال من النموذج المجاور." />
            ) : (
              <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                {posts.map((post) => (
                  <li key={post.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                      <p className="font-medium text-slate-900">{post.title}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {post.status_label} · {post.category?.name || 'بدون تصنيف'} · /blog/{post.slug}
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <button type="button" className="text-[#315CFF] underline" onClick={() => editPost(post)}>
                        تعديل
                      </button>
                      <button type="button" className="text-rose-600 underline" onClick={() => void removePost(post.id)}>
                        حذف
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>

          <DashboardSection title={postForm.id ? 'تعديل مقال' : 'مقال جديد'}>
            <form className="space-y-3" onSubmit={savePost}>
              <input required value={postForm.title} onChange={(e) => setPostForm((p) => ({ ...p, title: e.target.value }))} placeholder="العنوان" className={fieldClass} />
              <input value={postForm.slug} onChange={(e) => setPostForm((p) => ({ ...p, slug: e.target.value }))} placeholder="المسار (اختياري)" className={fieldClass} dir="ltr" />
              <textarea value={postForm.excerpt} onChange={(e) => setPostForm((p) => ({ ...p, excerpt: e.target.value }))} placeholder="المقتطف" rows={2} className={fieldClass} />
              <textarea
                required
                value={postForm.content}
                onChange={(e) => setPostForm((p) => ({ ...p, content: e.target.value }))}
                placeholder="المحتوى (محرر نصي — لا يوجد محرر غني مثبت في المشروع)"
                rows={10}
                className={fieldClass}
              />
              <input value={postForm.featured_image} onChange={(e) => setPostForm((p) => ({ ...p, featured_image: e.target.value }))} placeholder="صورة بارزة (URL)" className={fieldClass} dir="ltr" />
              <div className="grid gap-3 sm:grid-cols-2">
                <select value={postForm.author_id} onChange={(e) => setPostForm((p) => ({ ...p, author_id: e.target.value }))} className={fieldClass}>
                  <option value="">الكاتب</option>
                  {authors.map((author) => (
                    <option key={author.id} value={author.id}>
                      {author.name}
                    </option>
                  ))}
                </select>
                <select value={postForm.category_id} onChange={(e) => setPostForm((p) => ({ ...p, category_id: e.target.value }))} className={fieldClass}>
                  <option value="">التصنيف</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex flex-wrap gap-2">
                {tags.map((tag) => {
                  const checked = postForm.tag_ids.includes(tag.id)
                  return (
                    <label key={tag.id} className="inline-flex items-center gap-1 rounded-full border border-slate-300 px-2 py-1 text-xs">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() =>
                          setPostForm((prev) => ({
                            ...prev,
                            tag_ids: checked ? prev.tag_ids.filter((id) => id !== tag.id) : [...prev.tag_ids, tag.id],
                          }))
                        }
                      />
                      {tag.name}
                    </label>
                  )
                })}
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <select value={postForm.status} onChange={(e) => setPostForm((p) => ({ ...p, status: e.target.value }))} className={fieldClass}>
                  {STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                <input
                  type="datetime-local"
                  value={postForm.published_at}
                  onChange={(e) => setPostForm((p) => ({ ...p, published_at: e.target.value }))}
                  className={fieldClass}
                />
              </div>
              <input value={postForm.seo_title} onChange={(e) => setPostForm((p) => ({ ...p, seo_title: e.target.value }))} placeholder="عنوان تحسين البحث" className={fieldClass} />
              <textarea value={postForm.meta_description} onChange={(e) => setPostForm((p) => ({ ...p, meta_description: e.target.value }))} placeholder="وصف تحسين البحث" rows={2} className={fieldClass} />
              <input value={postForm.og_image} onChange={(e) => setPostForm((p) => ({ ...p, og_image: e.target.value }))} placeholder="رابط صورة المشاركة" className={fieldClass} dir="ltr" />
              <input value={postForm.canonical_url} onChange={(e) => setPostForm((p) => ({ ...p, canonical_url: e.target.value }))} placeholder="الرابط الأساسي" className={fieldClass} dir="ltr" />
              <div className="flex gap-2">
                <button type="submit" disabled={saving} className="rounded-lg bg-[#315CFF] px-4 py-2 text-sm font-medium text-white disabled:opacity-60">
                  {saving ? 'جارٍ الحفظ…' : 'حفظ المقال'}
                </button>
                {postForm.id ? (
                  <button type="button" onClick={() => setPostForm(emptyPost())} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">
                    إلغاء
                  </button>
                ) : null}
              </div>
            </form>
          </DashboardSection>
        </div>
      ) : null}

      {!loading && tab === 'categories' ? (
        <div className="grid gap-6 lg:grid-cols-2">
          <DashboardSection title="التصنيفات">
            <ul className="space-y-2 text-sm">
              {categories.map((category) => (
                <li key={category.id} className="flex justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <span>
                    {category.name} <span className="text-slate-400">({category.posts_count ?? 0})</span>
                  </span>
                  <span className="flex gap-2">
                    <button
                      type="button"
                      className="text-[#315CFF] underline"
                      onClick={() =>
                        setCategoryForm({
                          id: category.id,
                          name: category.name,
                          slug: category.slug,
                          description: category.description || '',
                          is_active: category.is_active !== false,
                        })
                      }
                    >
                      تعديل
                    </button>
                    <button
                      type="button"
                      className="text-rose-600 underline"
                      onClick={() => void deleteAdminBlogCategory(category.id).then(loadAll)}
                    >
                      حذف
                    </button>
                  </span>
                </li>
              ))}
            </ul>
          </DashboardSection>
          <DashboardSection title={categoryForm.id ? 'تعديل تصنيف' : 'تصنيف جديد'}>
            <form className="space-y-3" onSubmit={saveCategory}>
              <input required value={categoryForm.name} onChange={(e) => setCategoryForm((p) => ({ ...p, name: e.target.value }))} placeholder="الاسم" className={fieldClass} />
              <input value={categoryForm.slug} onChange={(e) => setCategoryForm((p) => ({ ...p, slug: e.target.value }))} placeholder="المسار" className={fieldClass} dir="ltr" />
              <textarea value={categoryForm.description} onChange={(e) => setCategoryForm((p) => ({ ...p, description: e.target.value }))} placeholder="الوصف" rows={3} className={fieldClass} />
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={categoryForm.is_active} onChange={(e) => setCategoryForm((p) => ({ ...p, is_active: e.target.checked }))} />
                نشط
              </label>
              <button type="submit" disabled={saving} className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
                حفظ
              </button>
            </form>
          </DashboardSection>
        </div>
      ) : null}

      {!loading && tab === 'tags' ? (
        <div className="grid gap-6 lg:grid-cols-2">
          <DashboardSection title="الوسوم">
            <ul className="space-y-2 text-sm">
              {tags.map((tag) => (
                <li key={tag.id} className="flex justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <span>
                    {tag.name} <span className="text-slate-400">({tag.posts_count ?? 0})</span>
                  </span>
                  <span className="flex gap-2">
                    <button type="button" className="text-[#315CFF] underline" onClick={() => setTagForm({ id: tag.id, name: tag.name, slug: tag.slug })}>
                      تعديل
                    </button>
                    <button type="button" className="text-rose-600 underline" onClick={() => void deleteAdminBlogTag(tag.id).then(loadAll)}>
                      حذف
                    </button>
                  </span>
                </li>
              ))}
            </ul>
          </DashboardSection>
          <DashboardSection title={tagForm.id ? 'تعديل وسم' : 'وسم جديد'}>
            <form className="space-y-3" onSubmit={saveTag}>
              <input required value={tagForm.name} onChange={(e) => setTagForm((p) => ({ ...p, name: e.target.value }))} placeholder="الاسم" className={fieldClass} />
              <input value={tagForm.slug} onChange={(e) => setTagForm((p) => ({ ...p, slug: e.target.value }))} placeholder="المسار" className={fieldClass} dir="ltr" />
              <button type="submit" disabled={saving} className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
                حفظ
              </button>
            </form>
          </DashboardSection>
        </div>
      ) : null}

      {!loading && tab === 'authors' ? (
        <div className="grid gap-6 lg:grid-cols-2">
          <DashboardSection title="الكتّاب">
            <ul className="space-y-2 text-sm">
              {authors.map((author) => (
                <li key={author.id} className="flex justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <span>
                    {author.name} <span className="text-slate-400">({author.posts_count ?? 0})</span>
                  </span>
                  <span className="flex gap-2">
                    <button
                      type="button"
                      className="text-[#315CFF] underline"
                      onClick={() =>
                        setAuthorForm({
                          id: author.id,
                          name: author.name,
                          slug: author.slug,
                          bio: author.bio || '',
                          avatar_url: author.avatar_url || '',
                          email: author.email || '',
                          is_active: author.is_active,
                        })
                      }
                    >
                      تعديل
                    </button>
                    <button type="button" className="text-rose-600 underline" onClick={() => void deleteAdminBlogAuthor(author.id).then(loadAll)}>
                      حذف
                    </button>
                  </span>
                </li>
              ))}
            </ul>
          </DashboardSection>
          <DashboardSection title={authorForm.id ? 'تعديل كاتب' : 'كاتب جديد'}>
            <form className="space-y-3" onSubmit={saveAuthor}>
              <input required value={authorForm.name} onChange={(e) => setAuthorForm((p) => ({ ...p, name: e.target.value }))} placeholder="الاسم" className={fieldClass} />
              <input value={authorForm.slug} onChange={(e) => setAuthorForm((p) => ({ ...p, slug: e.target.value }))} placeholder="المسار" className={fieldClass} dir="ltr" />
              <textarea value={authorForm.bio} onChange={(e) => setAuthorForm((p) => ({ ...p, bio: e.target.value }))} placeholder="نبذة" rows={3} className={fieldClass} />
              <input value={authorForm.avatar_url} onChange={(e) => setAuthorForm((p) => ({ ...p, avatar_url: e.target.value }))} placeholder="رابط الصورة" className={fieldClass} dir="ltr" />
              <input value={authorForm.email} onChange={(e) => setAuthorForm((p) => ({ ...p, email: e.target.value }))} placeholder="البريد الإلكتروني" className={fieldClass} dir="ltr" />
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={authorForm.is_active} onChange={(e) => setAuthorForm((p) => ({ ...p, is_active: e.target.checked }))} />
                نشط
              </label>
              <button type="submit" disabled={saving} className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
                حفظ
              </button>
            </form>
          </DashboardSection>
        </div>
      ) : null}

      {!loading && error && posts.length === 0 && tab === 'posts' ? (
        <DashboardErrorState message={error} onRetry={() => void loadAll()} />
      ) : null}
    </section>
  )
}
