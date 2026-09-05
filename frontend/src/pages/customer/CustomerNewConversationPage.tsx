import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { CatalogErrorState } from '../../components/catalog/CatalogStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getCustomerProjects } from '../../services/customerDashboard'
import { getCustomerOrders } from '../../services/orders'
import { createCustomerConversation } from '../../services/support'
import { customerConversationPath, MESSAGE_MAX_LENGTH, newConversationIsValid } from '../../utils/supportChat'

export function CustomerNewConversationPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const orders = useAsyncData(getCustomerOrders)
  const projects = useAsyncData(getCustomerProjects)
  const [subject, setSubject] = useState('')
  const [message, setMessage] = useState('')
  const [orderId, setOrderId] = useState(params.get('order') ?? '')
  const [projectId, setProjectId] = useState(params.get('project') ?? '')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const hasContext = orderId !== '' || projectId !== ''
  const canSubmit = newConversationIsValid(subject, message, hasContext) && !sending

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canSubmit) {
      return
    }

    setSending(true)
    setError(null)

    try {
      const response = await createCustomerConversation({
        subject: subject.trim() || undefined,
        message: message.trim() || undefined,
        order_id: orderId ? Number(orderId) : undefined,
        project_id: projectId ? Number(projectId) : undefined,
      })
      void navigate(customerConversationPath(response.data.id), { replace: true })
    } catch (caught) {
      const status = caught instanceof ApiRequestError ? caught.status : 500
      setError(status === 422 ? 'تحقق من الحقول ثم أعد المحاولة.' : 'تعذر بدء المحادثة. حاول مرة أخرى.')
    } finally {
      setSending(false)
    }
  }

  return (
    <section className="mx-auto max-w-2xl space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">محادثة جديدة</h1>
        <p className="text-sm text-slate-600">صف استفسارك. يمكنك ربط المحادثة بطلب أو مشروع تملكه.</p>
      </header>

      {error ? (
        <CatalogErrorState message={error} onRetry={() => setError(null)} />
      ) : null}

      <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" onSubmit={submit}>
        <label className="block space-y-2">
          <span className="text-sm font-medium">الموضوع</span>
          <input
            name="subject"
            value={subject}
            onChange={(event) => setSubject(event.target.value)}
            maxLength={255}
            placeholder="استفسار عن طلبي"
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>

        <label className="block space-y-2">
          <span className="text-sm font-medium">الرسالة</span>
          <textarea
            name="message"
            rows={5}
            dir="auto"
            value={message}
            maxLength={MESSAGE_MAX_LENGTH}
            onChange={(event) => setMessage(event.target.value)}
            placeholder="أحتاج معرفة آخر تحديث على الطلب."
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-7 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>

        <label className="block space-y-2">
          <span className="text-sm font-medium">طلب مرتبط (اختياري)</span>
          <select
            name="order_id"
            value={orderId}
            onChange={(event) => setOrderId(event.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            <option value="">بدون طلب</option>
            {orders.state.status === 'ready'
              ? orders.state.data.map((order) => (
                  <option key={order.id} value={order.id}>
                    {order.reference} — {order.title}
                  </option>
                ))
              : null}
          </select>
        </label>

        <label className="block space-y-2">
          <span className="text-sm font-medium">مشروع مرتبط (اختياري)</span>
          <select
            name="project_id"
            value={projectId}
            onChange={(event) => setProjectId(event.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            <option value="">بدون مشروع</option>
            {projects.state.status === 'ready'
              ? projects.state.data.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.title}
                  </option>
                ))
              : null}
          </select>
        </label>

        <div className="flex flex-wrap gap-3">
          <button
            type="submit"
            disabled={!canSubmit}
            className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {sending ? 'جاري الإنشاء...' : 'بدء المحادثة'}
          </button>
          <Link to="/dashboard/messages" className="inline-flex min-h-11 items-center text-sm underline">
            إلغاء
          </Link>
        </div>
      </form>
    </section>
  )
}
