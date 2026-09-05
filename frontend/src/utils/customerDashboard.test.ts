import { describe, expect, it } from 'vitest'
import { homePathForRole } from './roles'
import { CUSTOMER_DASHBOARD_NAV, isDashboardNavActive } from './dashboardNav'
import {
  CUSTOMER_CONSULTANT_CTA,
  CUSTOMER_DASHBOARD_COPY,
  CUSTOMER_LIVE_ROUTES,
  customerInitials,
  customerProjectPath,
  dashboardLoadView,
  isLiveCustomerPath,
  isUnavailableDomain,
  metricCardView,
  projectsSectionView,
  unavailableSectionView,
} from './customerDashboard'
import type { CustomerProject, UnavailableDomain } from '../types/api'

const unavailableOrders: UnavailableDomain = {
  available: false,
  status: 'unavailable',
  reason: 'orders_not_implemented',
  message: 'الطلبات سيتم تفعيلها قريبًا',
}

const sampleProject: CustomerProject = {
  id: 12,
  title: 'هوية المطعم',
  description: null,
  status: 'IN_PROGRESS',
  started_at: '2026-01-01',
  deadline: '2026-03-01',
  account_manager: { id: 3, name: 'أحمد' },
  progress: {
    total: 2,
    todo: 0,
    in_progress: 1,
    review: 0,
    revision: 0,
    completed: 1,
    overdue: 0,
    percent: 50,
  },
  created_at: '2026-01-01T00:00:00+00:00',
  updated_at: '2026-01-02T00:00:00+00:00',
}

describe('customer dashboard foundation', () => {
  it('renders customer navigation from live routes only', () => {
    for (const item of CUSTOMER_DASHBOARD_NAV) {
      expect(CUSTOMER_LIVE_ROUTES).toContain(item.to)
      expect(isLiveCustomerPath(item.to)).toBe(true)
      expect(item.to.startsWith('/owner')).toBe(false)
      expect(item.to.startsWith('/workspace')).toBe(false)
    }

    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/orders')).toBe(true)
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/messages')).toBe(true)
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/files')).toBe(true)
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/notifications')).toBe(true)
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/consultant')).toBe(true)
    expect(isDashboardNavActive(CUSTOMER_DASHBOARD_NAV[0], '/dashboard')).toBe(true)
    expect(isDashboardNavActive(CUSTOMER_DASHBOARD_NAV[0], '/dashboard/projects')).toBe(false)
    expect(isDashboardNavActive(CUSTOMER_DASHBOARD_NAV[1], '/dashboard/projects/12')).toBe(true)
  })

  it('keeps customer role access on the customer dashboard', () => {
    expect(homePathForRole('CUSTOMER')).toBe('/dashboard')
    expect(homePathForRole('OWNER')).toBe('/owner')
    expect(homePathForRole('WEB_DEVELOPER')).toBe('/workspace')
  })

  it('builds project cards from real project data only', () => {
    expect(customerProjectPath(sampleProject.id)).toBe('/dashboard/projects/12')
    expect(projectsSectionView([sampleProject])).toEqual({ kind: 'ready', count: 1 })
    expect(sampleProject.progress.percent).toBe(50)
    expect(sampleProject.account_manager?.name).toBe('أحمد')
  })

  it('covers loading, error, empty, and unavailable dashboard states', () => {
    expect(dashboardLoadView('loading')).toEqual({ kind: 'loading', label: CUSTOMER_DASHBOARD_COPY.loading })
    expect(dashboardLoadView('error')).toEqual({
      kind: 'error',
      message: CUSTOMER_DASHBOARD_COPY.error,
      canRetry: true,
    })
    expect(projectsSectionView([])).toEqual({
      kind: 'empty',
      title: 'لا توجد مشاريع حاليًا',
      cta: { to: '/services', label: 'استكشف خدمات HEBR' },
    })
    expect(unavailableSectionView(unavailableOrders, 'الطلبات')).toEqual({
      kind: 'unavailable',
      title: 'الطلبات',
      message: 'الطلبات سيتم تفعيلها قريبًا',
    })
    expect(metricCardView({ available: true, value: 0 }, 'لا توجد مشاريع حاليًا')).toEqual({
      kind: 'empty',
      text: 'لا توجد مشاريع حاليًا',
    })
    expect(metricCardView({ available: false }, 'لا توجد مشاريع حاليًا')).toEqual({
      kind: 'unavailable',
      text: 'غير متاح بعد',
    })
  })

  it('keeps the AI Consultant CTA on the live consultant route', () => {
    expect(CUSTOMER_CONSULTANT_CTA.path).toBe('/consultant')
    expect(CUSTOMER_CONSULTANT_CTA.label).toBe('ابدأ الاستشارة الذكية')
    expect(isLiveCustomerPath(CUSTOMER_CONSULTANT_CTA.path)).toBe(true)
  })

  it('builds initials from the authenticated name only', () => {
    expect(customerInitials('منى')).toBe('م')
    expect(customerInitials('')).toBe('ح')
  })

  it('treats missing domains as unavailable instead of empty zero lists', () => {
    expect(isUnavailableDomain(unavailableOrders)).toBe(true)
    expect(isUnavailableDomain({ available: true, value: 0 })).toBe(false)
    expect(isLiveCustomerPath('/consultant')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/projects/12')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/orders')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/messages')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/files')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/notifications')).toBe(true)
  })
})
