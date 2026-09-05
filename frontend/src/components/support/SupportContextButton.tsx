import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../services/api'
import { createCustomerConversation } from '../../services/support'
import { customerConversationPath, describeSupportError } from '../../utils/supportChat'

type SupportContextButtonProps = {
  orderId?: number
  projectId?: number
}

export function SupportContextButton({ orderId, projectId }: SupportContextButtonProps) {
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function openSupport() {
    setBusy(true)
    setError(null)

    try {
      const response = await createCustomerConversation({
        order_id: orderId,
        project_id: projectId,
      })
      void navigate(customerConversationPath(response.data.id))
    } catch (caught) {
      const status = caught instanceof ApiRequestError ? caught.status : 500
      setError(describeSupportError(status === 403 ? 403 : status === 422 ? 422 : 500))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-2">
      <button
        type="button"
        disabled={busy}
        onClick={() => void openSupport()}
        className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:bg-slate-400"
      >
        {busy ? 'جاري فتح المحادثة...' : 'تواصل مع الدعم'}
      </button>
      {error ? (
        <p className="text-sm text-red-800" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  )
}
