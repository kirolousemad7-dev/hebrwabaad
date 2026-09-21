import { useEffect, useMemo, useState } from 'react'
import {
  listOwnerMarketingMedia,
  type MarketingMedia,
} from '../../../services/marketingCms'
import { describeApiError } from '../../../utils/errors'
import { formatBytes } from '../../../utils/marketingCmsLabels'

type Props = {
  open: boolean
  onClose: () => void
  onSelect: (media: MarketingMedia) => void
  selectedId?: number | null
}

export function MarketingMediaPickerModal({ open, onClose, onSelect, selectedId }: Props) {
  const [items, setItems] = useState<MarketingMedia[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [query, setQuery] = useState('')

  useEffect(() => {
    if (!open) {
      return
    }

    let cancelled = false
    setLoading(true)
    setError(null)

    void listOwnerMarketingMedia({ per_page: 50 })
      .then((response) => {
        if (!cancelled) {
          setItems(response.data.items)
        }
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(describeApiError(caught, 'تعذر تحميل مكتبة الصور.'))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [open])

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q) {
      return items
    }
    return items.filter((item) => {
      const hay = `${item.title} ${item.original_name} ${item.alt_text} ${item.source_key ?? ''}`.toLowerCase()
      return hay.includes(q)
    })
  }, [items, query])

  if (!open) {
    return null
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-3 sm:items-center" role="presentation">
      <button type="button" className="absolute inset-0 cursor-default" aria-label="إغلاق" onClick={onClose} />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="media-picker-title"
        className="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
      >
        <header className="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 id="media-picker-title" className="text-lg font-semibold text-slate-900">
              اختر صورة
            </h2>
            <p className="mt-1 text-sm text-slate-600">من مكتبة صور الموقع التسويقي.</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          >
            إغلاق
          </button>
        </header>

        <div className="border-b border-slate-100 px-5 py-3">
          <label className="block text-sm font-medium text-slate-700" htmlFor="media-picker-search">
            بحث
          </label>
          <input
            id="media-picker-search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            placeholder="اسم الملف أو العنوان..."
          />
        </div>

        <div className="flex-1 overflow-y-auto p-5">
          {loading ? <p className="text-sm text-slate-600">جاري التحميل...</p> : null}
          {error ? <p className="text-sm text-rose-700">{error}</p> : null}
          {!loading && !error && filtered.length === 0 ? (
            <p className="text-sm text-slate-600">لا توجد صور مطابقة.</p>
          ) : null}

          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {filtered.map((item) => {
              const selected = selectedId === item.id
              return (
                <li key={item.id}>
                  <button
                    type="button"
                    onClick={() => onSelect(item)}
                    className={`w-full overflow-hidden rounded-xl border text-start transition ${
                      selected
                        ? 'border-slate-900 ring-2 ring-slate-900/20'
                        : 'border-slate-200 hover:border-slate-400'
                    }`}
                  >
                    <div className="aspect-[4/3] bg-slate-100">
                      {item.url ? (
                        <img src={item.url} alt={item.alt_text || item.title} className="h-full w-full object-cover" />
                      ) : (
                        <div className="flex h-full items-center justify-center text-xs text-slate-500">بدون معاينة</div>
                      )}
                    </div>
                    <div className="space-y-1 p-3">
                      <p className="truncate text-sm font-medium text-slate-900">{item.title || item.original_name}</p>
                      <p className="text-xs text-slate-500">
                        {formatBytes(item.size)}
                        {item.width && item.height ? ` · ${item.width}×${item.height}` : ''}
                      </p>
                      {item.is_registry_only ? (
                        <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-800">
                          صورة افتراضية
                        </span>
                      ) : null}
                    </div>
                  </button>
                </li>
              )
            })}
          </ul>
        </div>
      </div>
    </div>
  )
}
