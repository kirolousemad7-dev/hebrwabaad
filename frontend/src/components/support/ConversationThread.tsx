import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { ManagedSupportConversation, SupportConversation } from '../../types/api'
import { conversationContextLabel, conversationDetailView, messagesInChronologicalOrder, SUPPORT_COPY } from '../../utils/supportChat'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { ConversationStatusBadge } from './ConversationStatusBadge'
import { MessageBubble } from './MessageBubble'
import { MessageComposer } from './MessageComposer'

type ConversationThreadProps = {
  conversation: SupportConversation | ManagedSupportConversation
  composerValue: string
  sending: boolean
  sendError: string | null
  onComposerChange: (value: string) => void
  onSend: () => void
  backTo: string
  newConversationTo: string
  showCustomer?: boolean
  statusActions?: ReactNode
}

export function ConversationThread({
  conversation,
  composerValue,
  sending,
  sendError,
  onComposerChange,
  onSend,
  backTo,
  newConversationTo,
  showCustomer = false,
  statusActions,
}: ConversationThreadProps) {
  const view = conversationDetailView(conversation)
  const messages = messagesInChronologicalOrder(conversation.messages ?? [])
  const customerName = showCustomer && 'customer' in conversation ? conversation.customer?.name : null
  const context = conversationContextLabel(conversation)
  const closed = !conversation.can_reply

  return (
    <section className="flex min-h-[28rem] min-w-0 flex-1 flex-col gap-4">
      <p>
        <Link to={backTo} className="text-sm underline">
          كل المحادثات
        </Link>
      </p>

      <header className="space-y-2">
        <p className="text-xs text-slate-500" dir="ltr">
          {conversation.reference}
        </p>
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-xl font-semibold">{conversation.subject}</h1>
          <ConversationStatusBadge status={conversation.status} label={conversation.status_label} />
        </div>
        <div className="flex flex-wrap gap-2 text-sm text-slate-600">
          {customerName ? <span>{customerName}</span> : null}
          {conversation.order ? <span className="rounded-full bg-slate-100 px-3 py-1 text-xs">{conversation.order.reference}</span> : null}
          {conversation.project ? <span className="rounded-full bg-slate-100 px-3 py-1 text-xs">{conversation.project.title}</span> : null}
          {context && !conversation.order && !conversation.project ? <span>{context}</span> : null}
        </div>
      </header>

      {statusActions}

      <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-4">
        {messages.length === 0 ? (
          <p className="text-sm text-slate-600" role="status">
            {view.title || SUPPORT_COPY.noMessages}
          </p>
        ) : (
          messages.map((message) => <MessageBubble key={message.id} message={message} />)
        )}
      </div>

      {sendError ? <FeedbackBanner kind="error">{sendError}</FeedbackBanner> : null}

      <MessageComposer
        value={composerValue}
        sending={sending}
        closed={closed}
        onChange={onComposerChange}
        onSubmit={onSend}
      />

      {closed ? (
        <Link to={newConversationTo} className="inline-flex text-sm underline">
          {SUPPORT_COPY.closedCta}
        </Link>
      ) : null}
    </section>
  )
}
