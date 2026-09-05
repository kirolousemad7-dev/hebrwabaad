import { StatusBadge } from '../ui/StatusBadge'
import { conversationStatusLabel } from '../../utils/supportChat'

type ConversationStatusBadgeProps = {
  status: string
  label?: string
}

export function ConversationStatusBadge({ status, label }: ConversationStatusBadgeProps) {
  return <StatusBadge status={status} label={conversationStatusLabel(status, label)} />
}
