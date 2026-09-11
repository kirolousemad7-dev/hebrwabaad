import { FormEvent, useMemo, useState } from 'react'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { PORTFOLIO_CATEGORIES, type PortfolioCategory } from '../../services/marketing'
import {
  CONTENT_STATUS_LABELS,
  createEmployeeWork,
  deleteEmployeeWork,
  getEmployeeWork,
  resubmitEmployeeWork,
  submitEmployeeWork,
  updateEmployeeWork,
  uploadContentMedia,
  type WorkSubmission,
} from '../../services/work'
import { describeApiError } from '../../utils/errors'

const CATEGORY_LABELS: Record<string, string> = {
  web: 'مواقع',
  branding: 'هوية',
  social: 'سوشيال',
  video: 'فيديو',
  marketing: 'تسويق',
  events: 'فعاليات',
  printing: 'طباعة',
}

type WorkForm = {
  id: number | null
  title: string
  description: string
  category: PortfolioCategory
  tags: string
  tools: string
  project_url: string
  video_url: string
  client_label: string
  employee_notes: string
  cover_media_id: string | null
}

const emptyForm = (): WorkForm => ({
  id: null,
  title: '',
  description: '',
  category: 'web',
  tags: '',
  tools: '',
  project_url: '',
  video_url: '',
  client_label: '',
  employee_notes: '',
  cover_media_id: null,
})

export function EmployeeWorkPage() {
  const { state, reload } = useAsyncData(() => getEmployeeWork())
  const toast = useToast()
  const [form, setForm] = useState<WorkForm | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState<string>('all')

  const items = state.status === 'ready' ? state.data.items : []
  const counts = state.status === 'ready' ? state.data.counts : null
  const visible = useMemo(() => {
    if (filter === 'all') return items
    if (filter === 'under_review') return items.filter((item) => item.status === 'SUBMITTED' || item.status === 'UNDER_REVIEW')
    if (filter === 'needs_changes') return items.filter((item) => item.status === 'CHANGES_REQUESTED' || item.status === 'REJECTED')
    return items.filter((item) => item.status === filter)
  }, [filter, items])

  async function save(event: FormEvent) {
    event.preventDefault()
    if (!form) return
    setSaving(true)
    setError(null)
    const payload = {
      title: form.title,
      description: form.description || null,
      category: form.category,
      tags: form.tags.split(/[,،]/).map((value) => value.trim()).filter(Boolean),
      tools: form.tools.split(/[,،]/).map((value) => value.trim()).filter(Boolean),
      project_url: form.project_url || null,
      video_url: form.video_url || null,
      client_label: form.client_label || null,
      employee_notes: form.employee_notes || null,
      cover_media_id: form.cover_media_id,
    }
    try {
      if (form.id) {
        await updateEmployeeWork(form.id, payload)
        toast.success('تم حفظ العمل.')
      } else {
        await createEmployeeWork(payload)
        toast.success('تم إنشاء المسودة.')
      }
      setForm(null)
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ العمل.'))
    } finally {
      setSaving(false)
    }
  }

  async function onUpload(file: File) {
    const uploaded = await uploadContentMedia(file, 'cover')
    setForm((current) => (current ? { ...current, cover_media_id: uploaded.data.id } : current))
  }

  function edit(item: WorkSubmission) {
    setForm({
      id: item.id,
      title: item.title,
      description: item.description ?? '',
      category: item.category,
      tags: item.tags.join('، '),
      tools: item.tools.join('، '),
      project_url: item.project_url ?? '',
      video_url: item.video_url ?? '',
      client_label: item.client_label ?? '',
      employee_notes: item.employee_notes ?? '',
      cover_media_id: item.cover_media_id,
    })
  }

  return (
    <DashboardSection
      title="أعمالي"
      description="أنشئ أعمالك كمسودة ثم أرسلها للمالك. لا يمكن النشر مباشرة."
      action={
        <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setForm(emptyForm())}>
          عمل جديد
        </button>
      }
    >
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل أعمالك..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}

      {counts ? (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {(
            [
              ['all', 'الكل', counts.drafts + counts.under_review + counts.published + counts.needs_changes],
              ['DRAFT', 'مسودات', counts.drafts],
              ['under_review', 'قيد المراجعة', counts.under_review],
              ['PUBLISHED', 'منشور', counts.published],
              ['needs_changes', 'يحتاج تعديلات', counts.needs_changes],
            ] as const
          ).map(([key, label, value]) => (
            <li key={key}>
              <button
                type="button"
                onClick={() => setFilter(key)}
                className={`w-full rounded-2xl border px-4 py-3 text-right ${filter === key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white'}`}
              >
                <p className="text-sm opacity-80">{label}</p>
                <p className="text-2xl font-semibold">{value}</p>
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {form ? (
        <form onSubmit={(event) => void save(event)} className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h3 className="font-semibold">{form.id ? 'تعديل العمل' : 'مسودة جديدة'}</h3>
          <label className="block text-sm">
            العنوان
            <input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </label>
          <label className="block text-sm">
            الوصف
            <textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className="mt-1 min-h-24 w-full rounded-md border border-slate-300 px-3 py-2" />
          </label>
          <label className="block text-sm">
            الفئة
            <select value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value as PortfolioCategory })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
              {PORTFOLIO_CATEGORIES.map((category) => (
                <option key={category} value={category}>{CATEGORY_LABELS[category] ?? category}</option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            صورة الغلاف
            <input type="file" accept="image/*" className="mt-1 block w-full text-sm" onChange={(event) => {
              const file = event.target.files?.[0]
              if (file) void onUpload(file).catch((caught) => setError(describeApiError(caught, 'تعذر رفع الصورة.')))
            }} />
          </label>
          <label className="block text-sm">وسوم<input value={form.tags} onChange={(event) => setForm({ ...form, tags: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <label className="block text-sm">أدوات<input value={form.tools} onChange={(event) => setForm({ ...form, tools: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <label className="block text-sm">رابط المشروع<input value={form.project_url} onChange={(event) => setForm({ ...form, project_url: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <label className="block text-sm">رابط فيديو<input value={form.video_url} onChange={(event) => setForm({ ...form, video_url: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <label className="block text-sm">مرجع العميل (عام فقط)<input value={form.client_label} onChange={(event) => setForm({ ...form, client_label: event.target.value })} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <label className="block text-sm">ملاحظات داخلية<textarea value={form.employee_notes} onChange={(event) => setForm({ ...form, employee_notes: event.target.value })} className="mt-1 min-h-20 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
          <div className="flex flex-wrap gap-2">
            <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">{saving ? 'جاري الحفظ...' : 'حفظ المسودة'}</button>
            <button type="button" className="min-h-11 rounded-xl border border-slate-300 px-4 text-sm" onClick={() => setForm(null)}>إلغاء</button>
          </div>
        </form>
      ) : null}

      {state.status === 'ready' && visible.length === 0 ? (
        <DashboardEmptyState title="لا توجد أعمال في هذا التصنيف." description="أنشئ مسودة ثم أرسلها لمراجعة المالك." />
      ) : null}

      {visible.length > 0 ? (
        <ul className="space-y-3">
          {visible.map((item) => (
            <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{item.title}</p>
                  <p className="text-sm text-slate-500">{CATEGORY_LABELS[item.category] ?? item.category} · {CONTENT_STATUS_LABELS[item.status]}</p>
                  <p className="text-xs text-slate-400">أُنشئ {item.created_at ? new Date(item.created_at).toLocaleDateString('ar-SA') : '—'} · تحديث {item.updated_at ? new Date(item.updated_at).toLocaleDateString('ar-SA') : '—'}</p>
                  {item.review_notes ? <p className="mt-2 text-sm text-amber-800">ملاحظات المراجعة: {item.review_notes}</p> : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  {item.status === 'DRAFT' || item.status === 'CHANGES_REQUESTED' || item.status === 'REJECTED' ? (
                    <button type="button" className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm" onClick={() => edit(item)}>تعديل</button>
                  ) : (
                    <button type="button" className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm" onClick={() => edit(item)}>عرض</button>
                  )}
                  {item.status === 'DRAFT' ? (
                    <button type="button" className="min-h-10 rounded-lg bg-slate-900 px-3 text-sm text-white" onClick={() => void submitEmployeeWork(item.id).then(() => reload()).then(() => toast.success('تم الإرسال للمراجعة.'))}>إرسال</button>
                  ) : null}
                  {item.status === 'CHANGES_REQUESTED' || item.status === 'REJECTED' ? (
                    <button type="button" className="min-h-10 rounded-lg bg-slate-900 px-3 text-sm text-white" onClick={() => void resubmitEmployeeWork(item.id).then(() => reload()).then(() => toast.success('تم إعادة الإرسال.'))}>إعادة إرسال</button>
                  ) : null}
                  {item.status === 'DRAFT' ? (
                    <button type="button" className="min-h-10 rounded-lg border border-red-300 px-3 text-sm text-red-800" onClick={() => {
                      if (window.confirm('حذف هذه المسودة؟')) void deleteEmployeeWork(item.id).then(() => reload())
                    }}>حذف المسودة</button>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </DashboardSection>
  )
}
