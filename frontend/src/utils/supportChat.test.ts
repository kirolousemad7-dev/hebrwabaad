import { describe, expect, it } from 'vitest'
import { CUSTOMER_DASHBOARD_NAV, ownerNavForRole } from './dashboardNav'
import { isLiveCustomerPath } from './customerDashboard'
import { getWorkspaceForRole } from './employeeWorkspace'
import type { SupportConversation, SupportMessage } from '../types/api'
import {
  canReplyToConversation,
  canTransitionConversation,
  composerCanSubmit,
  conversationContextLabel,
  conversationDetailView,
  conversationListView,
  conversationStatusLabel,
  customerConversationPath,
  describeSupportError,
  describeSupportLoadError,
  isClosedConversation,
  messagesInChronologicalOrder,
  newConversationIsValid,
  normalizeComposerBody,
  previewMessage,
  senderDisplayName,
  SUPPORT_COPY,
} from './supportChat'

const openConversation: SupportConversation = {
  id: 7,
  reference: 'HEBR-CS-000007',
  subject: 'استفسار عن طلبي',
  status: 'OPEN',
  status_label: 'مفتوحة',
  can_reply: true,
  created_at: '2026-09-01T08:00:00+00:00',
  updated_at: '2026-09-01T08:00:00+00:00',
  last_message_at: '2026-09-01T08:00:00+00:00',
  last_message: {
    id: 1,
    body: 'أحتاج معرفة آخر تحديث على الطلب.',
    created_at: '2026-09-01T08:00:00+00:00',
    from_support: false,
  },
  order: { id: 21, reference: 'HEBR-ORD-000021', title: 'متجر' },
  project: null,
  assignee: { id: 3, name: 'أحمد' },
  messages: [
    {
      id: 1,
      body: 'أحتاج معرفة آخر تحديث على الطلب.',
      from_support: false,
      sender: { id: 9, name: 'منى' },
      created_at: '2026-09-01T08:00:00+00:00',
    },
  ],
}

const closedConversation: SupportConversation = {
  ...openConversation,
  status: 'CLOSED',
  status_label: 'مغلقة',
  can_reply: false,
}

describe('support chat', () => {
  it('exposes customer messages navigation on a live route', () => {
    expect(CUSTOMER_DASHBOARD_NAV.some((item) => item.to === '/dashboard/messages')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/messages')).toBe(true)
    expect(isLiveCustomerPath('/dashboard/messages/7')).toBe(true)
    expect(customerConversationPath(7)).toBe('/dashboard/messages/7')
  })

  it('renders conversation list and empty states', () => {
    expect(conversationListView([])).toEqual({ kind: 'empty', title: SUPPORT_COPY.listEmpty })
    expect(conversationListView([openConversation])).toEqual({ kind: 'ready', count: 1 })
    expect(previewMessage(openConversation.last_message?.body)).toContain('أحتاج معرفة')
    expect(conversationContextLabel(openConversation)).toBe('HEBR-ORD-000021')
    expect(conversationContextLabel({ order: null, project: { id: 12, title: 'E-commerce Website' } })).toBe(
      'E-commerce Website',
    )
  })

  it('renders conversation details, messages, and sender identity', () => {
    expect(conversationDetailView(openConversation).kind).toBe('ready')
    expect(conversationDetailView({ ...openConversation, messages: [] })).toEqual({
      kind: 'empty',
      title: SUPPORT_COPY.noMessages,
    })
    expect(senderDisplayName(openConversation.messages![0])).toBe('منى')
    expect(senderDisplayName({ from_support: true, sender: null })).toBe('فريق HEBR')
    expect(messagesInChronologicalOrder([{ id: 3 } as SupportMessage, { id: 1 } as SupportMessage]).map((item) => item.id)).toEqual([
      1,
      3,
    ])
  })

  it('disables the composer while sending, empty, or closed', () => {
    expect(composerCanSubmit('أحتاج معرفة آخر تحديث على الطلب.', false, false)).toBe(true)
    expect(composerCanSubmit('   ', false, false)).toBe(false)
    expect(composerCanSubmit('نص', true, false)).toBe(false)
    expect(composerCanSubmit('نص', false, true)).toBe(false)
    expect(normalizeComposerBody('  مرحبا  ')).toBe('مرحبا')
    expect(canReplyToConversation(openConversation)).toBe(true)
    expect(canReplyToConversation(closedConversation)).toBe(false)
  })

  it('covers closed conversation state and new conversation CTA copy', () => {
    expect(isClosedConversation(closedConversation)).toBe(true)
    expect(conversationDetailView(closedConversation)).toEqual({
      kind: 'closed',
      title: SUPPORT_COPY.closed,
    })
    expect(SUPPORT_COPY.closedCta).toBe('بدء محادثة جديدة')
    expect(newConversationIsValid('استفسار', 'مرحبا', false)).toBe(true)
    expect(newConversationIsValid('', '', true)).toBe(true)
    expect(newConversationIsValid('', 'مرحبا', false)).toBe(false)
  })

  it('uses backend conversation status labels and controlled transitions', () => {
    expect(conversationStatusLabel('IN_PROGRESS')).toBe('قيد المتابعة')
    expect(conversationStatusLabel('OPEN', 'مفتوحة')).toBe('مفتوحة')
    expect(canTransitionConversation('OPEN', 'IN_PROGRESS')).toBe(true)
    expect(canTransitionConversation('CLOSED', 'OPEN')).toBe(false)
    expect(canTransitionConversation('RESOLVED', 'IN_PROGRESS')).toBe(true)
  })

  it('maps authorization and closed send errors', () => {
    expect(describeSupportError(403)).toBe(SUPPORT_COPY.forbidden)
    expect(describeSupportError(404)).toBe(SUPPORT_COPY.loadError)
    expect(describeSupportError(422)).toBe(SUPPORT_COPY.closedSend)
    expect(describeSupportLoadError('This action is unauthorized.')).toBe(SUPPORT_COPY.forbidden)
    expect(describeSupportLoadError('This conversation is closed.')).toBe(SUPPORT_COPY.closedSend)
  })

  it('keeps account manager and owner support routes live and scoped', () => {
    expect(getWorkspaceForRole('ACCOUNT_MANAGER')?.navigation.some((item) => item.to === '/workspace/support')).toBe(true)
    expect(getWorkspaceForRole('WEB_DEVELOPER')?.navigation.some((item) => item.to === '/workspace/support')).toBe(false)
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/support')).toBe(true)
    expect(ownerNavForRole('ADMIN_MANAGER').some((item) => item.to === '/owner/support')).toBe(false)
  })
})
