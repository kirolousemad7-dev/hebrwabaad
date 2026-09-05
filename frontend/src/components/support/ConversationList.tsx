import { Link } from 'react-router-dom'
import type { ManagedSupportConversation, SupportConversation } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { conversationContextLabel, previewMessage } from '../../utils/supportChat'
import { ConversationStatusBadge } from './ConversationStatusBadge'

type ConversationListProps = {
  conversations: SupportConversation[]
  selectedId?: number
  itemPath: (id: number) => string
  showCustomer?: boolean
}

export function ConversationList({ conversations, selectedId, itemPath, showCustomer = false }: ConversationListProps) {
  return (
    <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
      {conversations.map((conversation) => {
        const selected = conversation.id === selectedId
        const customerName = showCustomer ? (conversation as ManagedSupportConversation).customer?.name : null

        return (
          <li key={conversation.id}>
            <Link
              to={itemPath(conversation.id)}
              aria-current={selected ? 'page' : undefined}
              className={`block min-w-0 px-4 py-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 ${
                selected ? 'bg-slate-100' : 'hover:bg-slate-50'
              }`}
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-slate-500" dir="ltr">
                  {conversation.reference}
                </p>
                <ConversationStatusBadge status={conversation.status} label={conversation.status_label} />
              </div>
              <p className="mt-1 truncate font-medium">{conversation.subject}</p>
              {customerName ? <p className="mt-0.5 text-xs text-slate-500">{customerName}</p> : null}
              {conversationContextLabel(conversation) ? (
                <p className="mt-0.5 text-xs text-slate-500">{conversationContextLabel(conversation)}</p>
              ) : null}
              {conversation.last_message?.body ? (
                <p className="mt-1 truncate text-sm text-slate-600">{previewMessage(conversation.last_message.body)}</p>
              ) : null}
              <p className="mt-1 text-xs text-slate-500">{formatDashboardDateTime(conversation.last_message_at ?? conversation.updated_at)}</p>
            </Link>
          </li>
        )
      })}
    </ul>
  )
}
