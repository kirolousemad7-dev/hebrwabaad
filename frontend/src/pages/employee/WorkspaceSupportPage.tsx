import { SupportInbox } from '../../components/support/SupportInbox'
import { workspaceSupportPath } from '../../utils/supportChat'

export function WorkspaceSupportPage() {
  return (
    <SupportInbox
      variant="internal"
      listPath={workspaceSupportPath()}
      itemPath={(id) => workspaceSupportPath(id)}
    />
  )
}
