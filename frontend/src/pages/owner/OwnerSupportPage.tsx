import { SupportInbox } from '../../components/support/SupportInbox'
import { ownerSupportPath } from '../../utils/supportChat'

export function OwnerSupportPage() {
  return (
    <SupportInbox
      variant="internal"
      listPath={ownerSupportPath()}
      itemPath={(id) => ownerSupportPath(id)}
    />
  )
}
