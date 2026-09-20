import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { MediaUploader } from '../../components/files/MediaUploader'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getPrintingCatalogAdmin,
  savePrintingCategory,
  savePrintingOption,
  savePrintingProduct,
  type PrintingCatalogAdmin,
} from '../../services/platformSettings'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const emptyProduct = {
  name_ar: '',
  name_en: '',
  slug: '',
  short_description: '',
  pricing_mode: 'QUOTE',
  starting_price: '',
  is_active: true,
  is_public: true,
  is_featured: false,
  allows_design_and_print: true,
  sort_order: 0,
  category_id: '' as number | '',
  option_ids: [] as number[],
}

export function OwnerPrintingCatalogPage() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [catalog, setCatalog] = useState<PrintingCatalogAdmin | null>(null)
  const [draft, setDraft] = useState(emptyProduct)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [categoryName, setCategoryName] = useState('')
  const [optionDraft, setOptionDraft] = useState({ type: 'material', name_ar: '' })
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await getPrintingCatalogAdmin()
      setCatalog(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل كتالوج الطباعة.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  async function handleSaveProduct() {
    if (!draft.name_ar.trim()) {
      setError('اسم المنتج مطلوب.')
      return
    }
    setSaving(true)
    setError(null)
    try {
      await savePrintingProduct(
        {
          ...draft,
          category_id: draft.category_id === '' ? null : draft.category_id,
          starting_price:
            draft.pricing_mode === 'QUOTE' || draft.starting_price === ''
              ? null
              : Number(draft.starting_price),
        },
        editingId ?? undefined,
      )
      setDraft(emptyProduct)
      setEditingId(null)
      setNotice('تم حفظ منتج الطباعة.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ المنتج.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleAddCategory() {
    if (!categoryName.trim()) return
    try {
      await savePrintingCategory({ name_ar: categoryName, is_active: true })
      setCategoryName('')
      setNotice('تمت إضافة التصنيف.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة التصنيف.'))
    }
  }

  async function handleAddOption() {
    if (!optionDraft.name_ar.trim()) return
    try {
      await savePrintingOption({ ...optionDraft, is_active: true })
      setOptionDraft({ type: 'material', name_ar: '' })
      setNotice('تمت إضافة الخيار.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة الخيار.'))
    }
  }

  if (loading) return <DashboardPanelSkeleton label="جاري تحميل كتالوج الطباعة…" />
  if (error && !catalog) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!catalog) return null

  return (
    <div className="space-y-6" dir="rtl">
      <DashboardSection
        title="كتالوج الطباعة والتغليف"
        description="إدارة المنتجات والخيارات الظاهرة للعملاء. التشغيل التشغيلي للطلبات يبقى في تشغيل الطباعة."
        action={
          <Link to="/owner/settings" className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            إعدادات المنصة
          </Link>
        }
      >
        {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

        <div className="mb-6 grid gap-3 rounded-xl border border-slate-200 p-4 lg:grid-cols-2">
          <label className="block text-sm">
            اسم المنتج (عربي)
            <input
              className={`${fieldClass} mt-1`}
              value={draft.name_ar}
              onChange={(e) => setDraft((prev) => ({ ...prev, name_ar: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            الاسم الإنجليزي
            <input
              className={`${fieldClass} mt-1`}
              value={draft.name_en}
              onChange={(e) => setDraft((prev) => ({ ...prev, name_en: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            الرابط المختصر (slug)
            <input
              className={`${fieldClass} mt-1`}
              value={draft.slug}
              onChange={(e) => setDraft((prev) => ({ ...prev, slug: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            التصنيف
            <select
              className={`${fieldClass} mt-1`}
              value={draft.category_id === '' ? '' : String(draft.category_id)}
              onChange={(e) =>
                setDraft((prev) => ({
                  ...prev,
                  category_id: e.target.value ? Number(e.target.value) : '',
                }))
              }
            >
              <option value="">بدون</option>
              {catalog.categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name_ar}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm lg:col-span-2">
            وصف مختصر
            <input
              className={`${fieldClass} mt-1`}
              value={draft.short_description}
              onChange={(e) => setDraft((prev) => ({ ...prev, short_description: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            وضع التسعير
            <select
              className={`${fieldClass} mt-1`}
              value={draft.pricing_mode}
              onChange={(e) => setDraft((prev) => ({ ...prev, pricing_mode: e.target.value }))}
            >
              <option value="QUOTE">طلب عرض سعر</option>
              <option value="STARTING_FROM">يبدأ من</option>
              <option value="FIXED">سعر ثابت</option>
            </select>
          </label>
          <label className="block text-sm">
            السعر (إن وجد)
            <input
              type="number"
              className={`${fieldClass} mt-1`}
              disabled={draft.pricing_mode === 'QUOTE'}
              value={draft.starting_price}
              onChange={(e) => setDraft((prev) => ({ ...prev, starting_price: e.target.value }))}
            />
          </label>
          <div className="flex flex-wrap gap-4 text-sm lg:col-span-2">
            {(
              [
                ['is_active', 'نشط'],
                ['is_public', 'عام'],
                ['is_featured', 'مميز'],
                ['allows_design_and_print', 'تصميم + طباعة'],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={Boolean(draft[key])}
                  onChange={(e) => setDraft((prev) => ({ ...prev, [key]: e.target.checked }))}
                />
                {label}
              </label>
            ))}
          </div>
          <div className="lg:col-span-2">
            <p className="mb-2 text-sm font-medium">الخيارات المتوافقة</p>
            <div className="flex flex-wrap gap-2">
              {catalog.options.map((option) => {
                const checked = draft.option_ids.includes(option.id)
                return (
                  <label
                    key={option.id}
                    className={`rounded-full border px-3 py-1 text-xs ${
                      checked ? 'border-brand-ink-900 bg-brand-ink-900 text-white' : 'border-slate-300'
                    }`}
                  >
                    <input
                      type="checkbox"
                      className="sr-only"
                      checked={checked}
                      onChange={() =>
                        setDraft((prev) => ({
                          ...prev,
                          option_ids: checked
                            ? prev.option_ids.filter((id) => id !== option.id)
                            : [...prev.option_ids, option.id],
                        }))
                      }
                    />
                    {option.type}: {option.name_ar}
                  </label>
                )
              })}
            </div>
          </div>
          <div className="flex gap-2 lg:col-span-2">
            <button
              type="button"
              disabled={saving}
              onClick={() => void handleSaveProduct()}
              className="rounded-lg bg-brand-ink-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              {editingId ? 'تحديث المنتج' : 'إضافة منتج'}
            </button>
            {editingId ? (
              <button
                type="button"
                onClick={() => {
                  setEditingId(null)
                  setDraft(emptyProduct)
                }}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              >
                إلغاء
              </button>
            ) : null}
          </div>
          {editingId ? (
            <div className="lg:col-span-2">
              <MediaUploader
                entityType="printing_product"
                entityId={editingId}
                title="صور منتج الطباعة"
                visibility="PUBLIC"
              />
            </div>
          ) : null}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-start text-slate-500">
                <th className="px-2 py-2">المنتج</th>
                <th className="px-2 py-2">التسعير</th>
                <th className="px-2 py-2">الحالة</th>
                <th className="px-2 py-2" />
              </tr>
            </thead>
            <tbody>
              {catalog.products.map((product) => (
                <tr key={product.id} className="border-b border-slate-100">
                  <td className="px-2 py-3">
                    <p className="font-medium">{product.name_ar}</p>
                    <p className="text-xs text-slate-500">{product.slug}</p>
                  </td>
                  <td className="px-2 py-3">
                    {product.pricing_mode === 'QUOTE'
                      ? 'عرض سعر'
                      : `${product.pricing_mode} ${product.starting_price ?? ''}`}
                  </td>
                  <td className="px-2 py-3">
                    {product.is_public ? 'عام' : 'خاص'} / {product.is_active ? 'نشط' : 'موقوف'}
                  </td>
                  <td className="px-2 py-3">
                    <button
                      type="button"
                      className="text-xs underline"
                      onClick={() => {
                        setEditingId(product.id)
                        setDraft({
                          name_ar: product.name_ar,
                          name_en: product.name_en || '',
                          slug: product.slug,
                          short_description: product.short_description || '',
                          pricing_mode: product.pricing_mode,
                          starting_price:
                            product.starting_price == null ? '' : String(product.starting_price),
                          is_active: product.is_active,
                          is_public: product.is_public,
                          is_featured: product.is_featured,
                          allows_design_and_print: product.allows_design_and_print,
                          sort_order: product.sort_order,
                          category_id: product.category_id ?? '',
                          option_ids: product.option_ids ?? [],
                        })
                      }}
                    >
                      تعديل
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DashboardSection>

      <DashboardSection title="التصنيفات والخيارات" description="خيارات مرتبطة بالمنتجات عبر التوافق.">
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="rounded-xl border border-slate-200 p-4">
            <p className="mb-3 text-sm font-semibold">تصنيفات</p>
            <ul className="mb-3 space-y-1 text-sm">
              {catalog.categories.map((category) => (
                <li key={category.id}>{category.name_ar}</li>
              ))}
            </ul>
            <div className="flex gap-2">
              <input
                className={fieldClass}
                placeholder="تصنيف جديد"
                value={categoryName}
                onChange={(e) => setCategoryName(e.target.value)}
              />
              <button
                type="button"
                onClick={() => void handleAddCategory()}
                className="rounded-lg bg-brand-ink-900 px-3 py-2 text-sm text-white"
              >
                إضافة
              </button>
            </div>
          </div>
          <div className="rounded-xl border border-slate-200 p-4">
            <p className="mb-3 text-sm font-semibold">خيارات (خامة / مقاس / …)</p>
            <ul className="mb-3 max-h-40 space-y-1 overflow-y-auto text-sm">
              {catalog.options.map((option) => (
                <li key={option.id}>
                  {option.type}: {option.name_ar}
                </li>
              ))}
            </ul>
            <div className="grid gap-2 sm:grid-cols-[8rem_1fr_auto]">
              <select
                className={fieldClass}
                value={optionDraft.type}
                onChange={(e) => setOptionDraft((prev) => ({ ...prev, type: e.target.value }))}
              >
                <option value="material">الخامة</option>
                <option value="size">المقاس</option>
                <option value="finishing">التشطيب</option>
                <option value="method">طريقة الطباعة</option>
                <option value="color">الألوان</option>
                <option value="quantity">الكمية</option>
              </select>
              <input
                className={fieldClass}
                placeholder="اسم الخيار"
                value={optionDraft.name_ar}
                onChange={(e) => setOptionDraft((prev) => ({ ...prev, name_ar: e.target.value }))}
              />
              <button
                type="button"
                onClick={() => void handleAddOption()}
                className="rounded-lg bg-brand-ink-900 px-3 py-2 text-sm text-white"
              >
                إضافة
              </button>
            </div>
          </div>
        </div>
      </DashboardSection>
    </div>
  )
}
