import { describe, expect, it } from 'vitest'
import { CUSTOMER_DASHBOARD_NAV, ownerNavForRole } from './dashboardNav'
import { isLiveCustomerPath } from './customerDashboard'
import {
  canTransitionOrder,
  describeOrderError,
  describeOrderLoadError,
  formatOrderProgress,
  ORDER_STATUS_LABELS,
  orderProgress,
  ordersSectionView,
  timelineForStatus,
} from './orderTracking'

describe('order tracking', () => {
  it('exposes customer orders navigation on a live route', () => {
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/orders')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/orders')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/orders/12')).toBe(true)
  })

  it('derives progress from lifecycle status', () => {
    expect(orderProgress('RECEIVED')).toBe(0)
    expect(orderProgress('CONFIRMED')).toBe(16)
    expect(orderProgress('IN_PROGRESS')).toBe(33)
    expect(orderProgress('REVIEW')).toBe(50)
    expect(orderProgress('REVISION')).toBe(66)
    expect(orderProgress('COMPLETED')).toBe(83)
    expect(orderProgress('DELIVERED')).toBe(100)
    expect(formatOrderProgress(33)).toContain('٪')
  })

  it('builds a timeline with completed, current, and pending stages', () => {
    const steps = timelineForStatus('IN_PROGRESS')
    expect(steps).toHaveLength(7)
    expect(steps[0]).toMatchObject({ status: 'RECEIVED', state: 'completed', label: ORDER_STATUS_LABELS.RECEIVED })
    expect(steps[2]).toMatchObject({ status: 'IN_PROGRESS', state: 'current' })
    expect(steps[6]).toMatchObject({ status: 'DELIVERED', state: 'pending' })
  })

  it('allows only controlled status transitions', () => {
    expect(canTransitionOrder('RECEIVED', 'CONFIRMED')).toBe(true)
    expect(canTransitionOrder('REVIEW', 'COMPLETED')).toBe(true)
    expect(canTransitionOrder('REVISION', 'IN_PROGRESS')).toBe(true)
    expect(canTransitionOrder('RECEIVED', 'DELIVERED')).toBe(false)
    expect(canTransitionOrder('DELIVERED', 'RECEIVED')).toBe(false)
  })

  it('covers empty, unauthorized, and invalid transition copy', () => {
    expect(ordersSectionView([])).toEqual({ kind: 'empty', title: 'لا توجد طلبات حتى الآن.' })
    expect(ordersSectionView([{ id: 1 } as never]).kind).toBe('ready')
    expect(describeOrderError(403)).toBe('لا يمكنك الوصول إلى هذا الطلب.')
    expect(describeOrderError(422)).toBe('لا يمكن الانتقال إلى هذه المرحلة حاليًا.')
    expect(describeOrderLoadError('This action is unauthorized.')).toBe('لا يمكنك الوصول إلى هذا الطلب.')
  })

  it('keeps owner order management on a live route and hides it from catalog managers', () => {
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/orders')).toBe(true)
    expect(ownerNavForRole('ADMIN_MANAGER').some((item) => item.to === '/owner/orders')).toBe(false)
  })
})
