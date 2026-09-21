import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicSuppliers } from '../../services/suppliers'
import { supplierPath } from '../../utils/suppliers'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

export function SuppliersNetworkSection() {
  const reduceMotion = useReducedMotion()
  const { state } = useAsyncData(() => getPublicSuppliers({ featured: true }))
  const suppliers = state.status === 'ready' ? state.data.slice(0, 6) : []

  return (
    <section id="suppliers" className="marketing-section scroll-mt-24 bg-brand-paper">
      <div className="marketing-container">
        <header className="mb-12 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
          <div className="max-w-2xl space-y-3">
            <p className="brand-label text-brand-ink-500">شبكة الموردين</p>
            <h2 className="text-[clamp(1.75rem,1.3rem+1.4vw,2.75rem)] font-bold leading-tight text-brand-ink-900">
              موردونا وشركاؤنا
            </h2>
            <p className="leading-8 text-brand-ink-500">
              نعمل مع شبكة من الموردين والمتخصصين لتوفير حلول الطباعة، التغليف، التجهيزات والفعاليات حسب احتياج المشروع.
            </p>
          </div>
          <Link to="/suppliers" className="brand-btn-dark shrink-0">
            استكشف الموردين
          </Link>
        </header>

        {state.status === 'loading' ? (
          <p className="text-sm text-brand-ink-500">جاري تحميل الشركاء...</p>
        ) : null}

        {state.status === 'ready' && suppliers.length === 0 ? (
          <p className="border border-dashed border-brand-ink-100 bg-white px-4 py-10 text-center text-sm text-brand-ink-500">
            سيتم عرض الموردين المنشورين هنا بعد اعتماد المالك.
          </p>
        ) : null}

        {suppliers.length > 0 ? (
          <motion.ul
            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            variants={motionOrReduced(reduceMotion, staggerContainer)}
            initial="hidden"
            whileInView="show"
            viewport={{ once: true, amount: 0.15 }}
          >
            {suppliers.map((supplier) => (
              <motion.li
                key={supplier.slug}
                variants={motionOrReduced(reduceMotion, fadeUp)}
                className="group rounded-[1.5rem] border border-brand-ink-100 bg-white p-6 transition hover:-translate-y-1 hover:shadow-card motion-reduce:hover:translate-y-0"
              >
                <img
                  src={supplier.logo}
                  alt=""
                  className="h-14 w-14 rounded-2xl object-cover"
                  loading="lazy"
                />
                <h3 className="mt-4 text-lg font-semibold text-brand-ink-900">{supplier.name}</h3>
                <p className="text-sm text-brand-ink-500">{supplier.category ?? supplier.specialties[0]}</p>
                {supplier.short_description ? (
                  <p className="mt-2 line-clamp-3 text-sm leading-7 text-brand-ink-500">
                    {supplier.short_description}
                  </p>
                ) : null}
                <Link
                  to={supplierPath(supplier.slug)}
                  className="mt-5 inline-flex min-h-10 items-center text-sm font-medium text-brand-cobalt-700 transition group-hover:underline"
                >
                  عرض التفاصيل
                </Link>
              </motion.li>
            ))}
          </motion.ul>
        ) : null}
      </div>
    </section>
  )
}
