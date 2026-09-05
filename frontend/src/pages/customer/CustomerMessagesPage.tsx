import { SupportInbox } from '../../components/support/SupportInbox'
import { customerConversationPath } from '../../utils/supportChat'

export function CustomerMessagesPage() {
  return (
    <SupportInbox
      variant="customer"
      listPath="/dashboard/messages"
      itemPath={customerConversationPath}
      newPath="/dashboard/messages/new"
    />
  )
}
