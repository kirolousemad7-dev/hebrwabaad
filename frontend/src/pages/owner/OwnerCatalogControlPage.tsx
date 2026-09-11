import { Link } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  getCatalogReadiness,
  getManagedAddons,
  getManagedRecommendationGoals,
  getManagedSectors,
} from '../../services/catalogControl'

export function OwnerCatalogControlPage() {
  const readiness = useAsyncData(getCatalogReadiness)
  const sectors = useAsyncData(getManagedSectors)
  const addons = useAsyncData(getManagedAddons)
  const goals = useAsyncData(getManagedRecommendationGoals)

  const loading =
    readiness.state.status === 'loading' ||
    sectors.state.status === 'loading' ||
    addons.state.status === 'loading' ||
    goals.state.status === 'loading'

  const failed =
    readiness.state.status === 'error'
      ? readiness.state.message
      : sectors.state.status === 'error'
        ? sectors.state.message
        : addons.state.status === 'error'
          ? addons.state.message
          : goals.state.status === 'error'
            ? goals.state.message
            : null

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل مركز الكتالوج..." />
  }

  if (failed) {
    return <DashboardErrorState message={`تعذر تحميل مركز الكتالوج. ${failed}`} onRetry={() => window.location.reload()} />
  }

  const report = readiness.state.status === 'ready' ? readiness.state.data : null
  const sectorRows = sectors.state.status === 'ready' ? sectors.state.data : []
  const addonRows = addons.state.status === 'ready' ? addons.state.data : []
  const goalRows = goals.state.status === 'ready' ? goals.state.data : []
  const incomplete = report?.items.filter((row) => row.missing.length > 0) ?? []

  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold">مركز كتالوج المنصة</h1>
        <p className="text-sm text-slate-600">
          جاهزية النشر التجاري وفق مواصفات PDF — دون أسعار مخترعة. أكمل الحقول الناقصة من صفحات الخدمات
          والباقات.
        </p>
        <div className="flex flex-wrap gap-2 text-sm">
          <Link className="text-brand-700 underline" to="/owner/services">
            إدارة الخدمات (تجاري + تشغيلي)
          </Link>
          <Link className="text-brand-700 underline" to="/owner/packages">
            إدارة الباقات
          </Link>
          <Link className="text-brand-700 underline" to="/owner/departments">
            الأقسام
          </Link>
          <Link className="text-brand-700 underline" to="/owner/marketing">
            المحتوى / دراسات الحالة
          </Link>
        </div>
      </header>

      {report ? (
        <section className="grid gap-4 sm:grid-cols-3">
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-xs text-slate-500">إجمالي الصفوف</p>
            <p className="text-2xl font-semibold">{report.summary.total}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-xs text-slate-500">قابل للشراء مباشرة</p>
            <p className="text-2xl font-semibold text-emerald-700">{report.summary.purchasable}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-xs text-slate-500">سعر غير مكتمل</p>
            <p className="text-2xl font-semibold text-amber-700">{report.summary.price_incomplete}</p>
          </div>
        </section>
      ) : null}

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">القطاعات ({sectorRows.length})</h2>
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
          {sectorRows.map((sector) => (
            <li key={sector.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <span className="font-medium">{sector.name_ar}</span>
              <span className="text-slate-500">{sector.slug}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">الإضافات ({addonRows.length})</h2>
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
          {addonRows.map((addon) => (
            <li key={addon.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <span className="font-medium">{addon.name}</span>
              <span className="text-slate-500">
                {addon.pricing_mode}
                {!addon.is_public ? ' · غير عام' : ''}
                {addon.capacity_available === false ? ' · عاجل معطّل' : ''}
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">أهداف التوصية ({goalRows.length})</h2>
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
          {goalRows.map((goal) => (
            <li key={goal.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <span className="font-medium">{goal.name_ar}</span>
              <span className="text-slate-500">{goal.slug}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">بيانات تجارية ناقصة ({incomplete.length})</h2>
        <p className="text-sm text-slate-600">
          لا تُعرض كسعر شراء مباشر حتى تُكمل السعر / النطاق / المدة / جولات التعديل حسب نوع التسعير.
        </p>
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr>
                <th className="px-3 py-2 text-start font-medium">النوع</th>
                <th className="px-3 py-2 text-start font-medium">المعرّف</th>
                <th className="px-3 py-2 text-start font-medium">الناقص</th>
                <th className="px-3 py-2 text-start font-medium">شراء</th>
              </tr>
            </thead>
            <tbody>
              {incomplete.map((row) => (
                <tr key={`${row.type}-${row.id}`} className="border-t border-slate-100">
                  <td className="px-3 py-2">{row.type}</td>
                  <td className="px-3 py-2 font-mono text-xs">{row.slug}</td>
                  <td className="px-3 py-2 text-amber-800">{row.missing.join(', ')}</td>
                  <td className="px-3 py-2">{row.is_purchasable ? 'نعم' : 'لا'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
