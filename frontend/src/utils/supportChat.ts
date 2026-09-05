import type { ConversationStatus, SupportConversation, SupportMessage } from '../types/api'
import { CONVERSATION_STATUSES } from '../types/api'

export const MESSAGE_MAX_LENGTH = 5000

export const CONVERSATION_STATUS_LABELS: Record<ConversationStatus, string> = {
  OPEN: 'مفتوحة',
  IN_PROGRESS: 'قيد المتابعة',
  RESOLVED: 'تم الحل',
  CLOSED: 'مغلقة',
}

export const ALLOWED_CONVERSATION_TRANSITIONS: Record<ConversationStatus, ConversationStatus[]> = {
  OPEN: ['IN_PROGRESS', 'RESOLVED', 'CLOSED'],
  IN_PROGRESS: ['RESOLVED', 'CLOSED'],
  RESOLVED: ['IN_PROGRESS', 'CLOSED'],
  CLOSED: [],
}

export const SUPPORT_COPY = {
  listEmpty: 'لا توجد محادثات حتى الآن.',
  listEmptyCta: 'ابدأ محادثة جديدة',
  dashboardEmpty: 'لا توجد محادثات بعد.',
  dashboardCta: 'ابدأ محادثة',
  noMessages: 'ابدأ المحادثة بإرسال أول رسالة.',
  closed: 'هذه المحادثة مغلقة.',
  closedCta: 'بدء محادثة جديدة',
  loadError: 'تعذر تحميل المحادثة.',
  forbidden: 'لا يمكنك الوصول إلى هذه المحادثة.',
  closedSend: 'لا يمكن إرسال رسالة في محادثة مغلقة.',
  composerLabel: 'اكتب رسالتك',
  send: 'إرسال',
} as const

export function isConversationStatus(value: string): value is ConversationStatus {
  return CONVERSATION_STATUSES.includes(value as ConversationStatus)
}

export function conversationStatusLabel(status: string, fallback?: string): string {
  if (fallback) {
    return fallback
  }

  return isConversationStatus(status) ? CONVERSATION_STATUS_LABELS[status] : status
}

export function canTransitionConversation(from: string, to: string): boolean {
  if (!isConversationStatus(from) || !isConversationStatus(to)) {
    return false
  }

  return ALLOWED_CONVERSATION_TRANSITIONS[from].includes(to)
}

export function canReplyToConversation(conversation: Pick<SupportConversation, 'can_reply' | 'status'>): boolean {
  return conversation.can_reply && conversation.status !== 'CLOSED'
}

export function isClosedConversation(conversation: Pick<SupportConversation, 'status' | 'can_reply'>): boolean {
  return conversation.status === 'CLOSED' || conversation.can_reply === false
}

export function previewMessage(body: string | null | undefined, limit = 90): string {
  const text = body?.trim() ?? ''
  if (text === '') {
    return ''
  }

  return text.length > limit ? `${text.slice(0, limit)}…` : text
}

export function normalizeComposerBody(value: string): string {
  return value.trim()
}

export function composerCanSubmit(value: string, sending: boolean, closed: boolean): boolean {
  if (sending || closed) {
    return false
  }

  const body = normalizeComposerBody(value)
  return body.length > 0 && body.length <= MESSAGE_MAX_LENGTH
}

export function conversationListView(
  conversations: SupportConversation[],
): { kind: 'empty' | 'ready'; title?: string; count?: number } {
  if (conversations.length === 0) {
    return { kind: 'empty', title: SUPPORT_COPY.listEmpty }
  }

  return { kind: 'ready', count: conversations.length }
}

export function conversationDetailView(
  conversation: Pick<SupportConversation, 'status' | 'can_reply' | 'messages'>,
): { kind: 'closed' | 'empty' | 'ready'; title: string } {
  if (isClosedConversation(conversation) && (conversation.messages?.length ?? 0) === 0) {
    return { kind: 'closed', title: SUPPORT_COPY.closed }
  }

  if ((conversation.messages?.length ?? 0) === 0) {
    return { kind: 'empty', title: SUPPORT_COPY.noMessages }
  }

  if (isClosedConversation(conversation)) {
    return { kind: 'closed', title: SUPPORT_COPY.closed }
  }

  return { kind: 'ready', title: '' }
}

export function conversationContextLabel(conversation: Pick<SupportConversation, 'order' | 'project'>): string | null {
  if (conversation.order) {
    return conversation.order.reference
  }

  if (conversation.project) {
    return conversation.project.title
  }

  return null
}

export function senderDisplayName(message: Pick<SupportMessage, 'from_support' | 'sender'>, fallback = 'فريق HEBR'): string {
  if (message.sender?.name) {
    return message.sender.name
  }

  return message.from_support ? fallback : 'العميل'
}

export function messagesInChronologicalOrder(messages: SupportMessage[]): SupportMessage[] {
  return [...messages].sort((left, right) => left.id - right.id)
}

export function customerConversationPath(id: number): string {
  return `/dashboard/messages/${id}`
}

export function workspaceSupportPath(id?: number): string {
  return id ? `/workspace/support/${id}` : '/workspace/support'
}

export function ownerSupportPath(id?: number): string {
  return id ? `/owner/support/${id}` : '/owner/support'
}

export function describeSupportError(status: number): string {
  if (status === 401) {
    return 'يلزم تسجيل الدخول لعرض المحادثات.'
  }

  if (status === 403) {
    return SUPPORT_COPY.forbidden
  }

  if (status === 404) {
    return SUPPORT_COPY.loadError
  }

  if (status === 422 || status === 409) {
    return SUPPORT_COPY.closedSend
  }

  return 'حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى.'
}

export function describeSupportLoadError(message: string): string {
  const lower = message.toLowerCase()

  if (lower.includes('unauthorized') || lower.includes('forbidden') || message.includes('لا يمكنك')) {
    return describeSupportError(403)
  }

  if (lower.includes('closed') || message.includes('مغلقة')) {
    return describeSupportError(422)
  }

  if (lower.includes('not found') || message.includes('تعذر تحميل')) {
    return describeSupportError(404)
  }

  return describeSupportError(500)
}

export function newConversationIsValid(subject: string, message: string, hasContext: boolean): boolean {
  const body = normalizeComposerBody(message)
  const title = subject.trim()

  if (body.length > MESSAGE_MAX_LENGTH) {
    return false
  }

  if (hasContext) {
    return true
  }

  return title.length > 0 && body.length > 0
}
