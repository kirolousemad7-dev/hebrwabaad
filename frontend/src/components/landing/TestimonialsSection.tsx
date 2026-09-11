import { useEffect, useState } from 'react'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicTestimonials } from '../../services/marketing'

export function TestimonialsSection() {
  const { state } = useAsyncData(getPublicTestimonials)
  const items = state.status === 'ready' ? state.data : []
  const [index, setIndex] = useState(0)

  useEffect(() => {
    setIndex(0)
  }, [items.length])

  if (state.status !== 'ready' || items.length === 0) {
    return null
  }

  const current = items[index] ?? items[0]

  return (
    <section className="bg-slate-50 py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-8 space-y-3">
          <p className="text-sm font-medium text-amber-700">آراء العملاء</p>
          <h2 className="text-3xl font-semibold">ما يشاركه عملاؤنا بعد التجربة</h2>
        </header>
        <figure className="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
          <blockquote className="text-lg leading-9 text-slate-700">«{current.quote}»</blockquote>
          <figcaption className="mt-4">
            <p className="font-semibold">{current.author_name}</p>
            {current.author_role ? <p className="text-sm text-slate-500">{current.author_role}</p> : null}
          </figcaption>
        </figure>
        {items.length > 1 ? (
          <div className="mt-4 flex items-center gap-3">
            <button
              type="button"
              className="min-h-11 rounded-full border border-slate-300 px-4 text-sm"
              onClick={() => setIndex((value) => (value === 0 ? items.length - 1 : value - 1))}
            >
              السابق
            </button>
            <p className="text-sm text-slate-500">
              {index + 1} / {items.length}
            </p>
            <button
              type="button"
              className="min-h-11 rounded-full border border-slate-300 px-4 text-sm"
              onClick={() => setIndex((value) => (value === items.length - 1 ? 0 : value + 1))}
            >
              التالي
            </button>
          </div>
        ) : null}
      </div>
    </section>
  )
}
