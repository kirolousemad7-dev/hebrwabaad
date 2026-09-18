import { Link } from 'react-router-dom'

const ACTIONS = [
  { to: '/owner/employees', label: 'إدارة الموظفين' },
  { to: '/owner/orders', label: 'الطلبات' },
  { to: '/owner/quote-requests', label: 'طلبات التسعير' },
  { to: '/owner/requirements', label: 'اكتشف احتياجك' },
  { to: '/owner/payments', label: 'المدفوعات' },
  { to: '/owner/support', label: 'الدعم' },
  { to: '/owner/services', label: 'إدارة الخدمات' },
  { to: '/owner/packages', label: 'إدارة الباقات' },
  { to: '/owner/catalog-control', label: 'مركز الكتالوج' },
  { to: '/owner/seo', label: 'إدارة SEO' },
  { to: '/owner/marketing', label: 'المحتوى التسويقي' },
  { to: '/owner/work-reviews', label: 'مراجعة الأعمال' },
  { to: '/owner/suppliers', label: 'الموردين' },
  { to: '/crm', label: 'المبيعات / CRM' },
  { to: '/printing-requests', label: 'طلبات الطباعة' },
] as const

export function DashboardQuickActions() {
  return (
    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {ACTIONS.map((action) => (
        <li key={action.to}>
          <Link
            to={action.to}
            className="flex min-h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium shadow-sm hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {action.label}
          </Link>
        </li>
      ))}
    </ul>
  )
}
