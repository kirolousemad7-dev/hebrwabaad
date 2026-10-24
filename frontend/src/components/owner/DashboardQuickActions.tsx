import { Link } from 'react-router-dom'
import { ownerPrimaryButtonClass, ownerSecondaryButtonClass } from './ownerChrome'

const PRIMARY = [
  { to: '/owner/projects', label: 'إضافة مشروع' },
  { to: '/owner/work', label: 'المهام' },
  { to: '/owner/services', label: 'إضافة خدمة' },
  { to: '/owner/customers', label: 'إضافة عميل' },
] as const

const MORE = [
  { to: '/owner/employees', label: 'إدارة الموظفين' },
  { to: '/owner/orders', label: 'الطلبات' },
  { to: '/owner/quote-requests', label: 'طلبات التسعير' },
  { to: '/owner/requirements', label: 'اكتشف احتياجك' },
  { to: '/owner/payments', label: 'المدفوعات' },
  { to: '/owner/support', label: 'الدعم' },
  { to: '/owner/packages', label: 'إدارة الباقات' },
  { to: '/owner/catalog-control', label: 'مركز الكتالوج' },
  { to: '/owner/seo', label: 'إدارة SEO' },
  { to: '/owner/marketing', label: 'المحتوى التسويقي' },
  { to: '/owner/blog', label: 'المدونة' },
  { to: '/owner/work-reviews', label: 'مراجعة الأعمال' },
  { to: '/owner/suppliers', label: 'الموردين' },
  { to: '/crm', label: 'المبيعات / CRM' },
  { to: '/printing-requests', label: 'طلبات الطباعة' },
] as const

export function DashboardQuickActions() {
  return (
    <div className="space-y-3">
      <ul className="flex flex-wrap gap-2">
        {PRIMARY.map((action) => (
          <li key={action.to}>
            <Link to={action.to} className={ownerPrimaryButtonClass}>
              {action.label}
            </Link>
          </li>
        ))}
      </ul>
      <ul className="flex flex-wrap gap-2">
        {MORE.map((action) => (
          <li key={action.to}>
            <Link to={action.to} className={ownerSecondaryButtonClass}>
              {action.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}
