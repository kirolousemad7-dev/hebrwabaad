import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../catalog/CatalogStatus'
import { WorkspaceErrorState, WorkspaceSkeleton } from '../workspace/WorkspaceStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import {
  getCustomerConversation,
  getCustomerConversations,
  getSupportConversation,
  getSupportConversations,
  sendCustomerMessage,
  sendSupportMessage,
  updateSupportConversationStatus,
} from '../../services/support'
import type { ManagedSupportConversation, SupportConversation } from '../../types/api'
import { describeSupportError, describeSupportLoadError, SUPPORT_COPY } from '../../utils/supportChat'
import { ConversationList } from './ConversationList'
import { ConversationThread } from './ConversationThread'

type SupportInboxProps = {
  variant: 'customer' | 'internal'
  listPath: string
  itemPath: (id: number) => string
  newPath?: string
}

export function SupportInbox({ variant, listPath, itemPath, newPath = '/dashboard/messages/new' }: SupportInboxProps) {
  const { conversationId } = useParams()
  const numericId = conversationId ? Number(conversationId) : null
  const selectedId = Number.isInteger(numericId) && (numericId ?? 0) > 0 ? numericId : null
  const customer = variant === 'customer'

  const { state: listState, reload: reloadList } = useAsyncData(async (): Promise<{ data: SupportConversation[] }> => {
    if (customer) {
      return getCustomerConversations()
    }

    const response = await getSupportConversations()
    return { data: response.data.items }
  })
  const { state: detailState, reload: reloadDetail } = useAsyncData(async (): Promise<{ data: SupportConversation | ManagedSupportConversation | null }> => {
    if (selectedId === null) {
      return { data: null }
    }

    return customer ? getCustomerConversation(selectedId) : getSupportConversation(selectedId)
  })

  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [statusUpdating, setStatusUpdating] = useState<string | null>(null)
  const [sendError, setSendError] = useState<string | null>(null)

  useEffect(() => {
    setDraft('')
    setSendError(null)

    if (selectedId !== null) {
      void reloadDetail()
    }
  }, [selectedId, reloadDetail])

  const conversations = listState.status === 'ready' ? listState.data : []

  async function send() {
    if (selectedId === null) {
      return
    }

    setSendError(null)
    setSending(true)

    try {
      if (customer) {
        await sendCustomerMessage(selectedId, draft)
      } else {
        await sendSupportMessage(selectedId, draft)
      }

      setDraft('')
      await Promise.all([reloadDetail(), reloadList()])
    } catch (caught) {
      const status = caught instanceof ApiRequestError ? caught.status : 500
      setSendError(describeSupportError(status === 403 || status === 422 || status === 409 || status === 401 ? status : 500))
    } finally {
      setSending(false)
    }
  }

  async function changeStatus(status: string) {
    if (selectedId === null) {
      return
    }

    setSendError(null)
    setStatusUpdating(status)

    try {
      await updateSupportConversationStatus(selectedId, status)
      await Promise.all([reloadDetail(), reloadList()])
    } catch (caught) {
      const code = caught instanceof ApiRequestError ? caught.status : 500
      setSendError(describeSupportError(code === 422 || code === 409 ? 422 : code === 403 ? 403 : 500))
    } finally {
      setStatusUpdating(null)
    }
  }

  if (listState.status === 'loading' && selectedId === null) {
    return customer ? <CatalogSkeleton variant="list" label="جاري تحميل المحادثات..." /> : <WorkspaceSkeleton label="جاري تحميل المحادثات..." />
  }

  if (listState.status === 'error' && selectedId === null) {
    const ErrorState = customer ? CatalogErrorState : WorkspaceErrorState
    return <ErrorState message={describeSupportLoadError(listState.message)} onRetry={() => void reloadList()} />
  }

  const selected = selectedId !== null
  const managed = !customer && detailState.status === 'ready' && detailState.data !== null
    ? (detailState.data as ManagedSupportConversation)
    : null

  return (
    <section className="space-y-5">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold">{customer ? 'الرسائل' : 'محادثات الدعم'}</h1>
          <p className="text-sm text-slate-600">
            {customer ? 'تواصل مع فريق HEBR حول طلبك أو مشروعك أو أي استفسار عام.' : 'الرد على محادثات العملاء ضمن نطاق صلاحيتك.'}
          </p>
        </div>
        {customer ? (
          <Link
            to={newPath}
            className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {SUPPORT_COPY.listEmptyCta}
          </Link>
        ) : null}
      </header>

      <div className={`grid gap-5 ${selected ? 'lg:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)]' : ''}`}>
        <div className={selected ? 'hidden min-w-0 lg:block' : 'min-w-0'}>
          {listState.status === 'loading' ? (
            customer ? <CatalogSkeleton variant="list" label="جاري تحميل المحادثات..." /> : <WorkspaceSkeleton label="جاري تحميل المحادثات..." />
          ) : null}
          {listState.status === 'error' ? (
            customer ? (
              <CatalogErrorState message={describeSupportLoadError(listState.message)} onRetry={() => void reloadList()} />
            ) : (
              <WorkspaceErrorState message={describeSupportLoadError(listState.message)} onRetry={() => void reloadList()} />
            )
          ) : null}
          {listState.status === 'ready' && conversations.length === 0 ? (
            <CatalogEmptyState
              title={SUPPORT_COPY.listEmpty}
              description={customer ? 'ابدأ محادثة جديدة وسيتواصل معك فريق الدعم.' : 'لا توجد محادثات ضمن نطاقك حاليًا.'}
              actions={customer ? [{ to: newPath, label: SUPPORT_COPY.listEmptyCta, variant: 'primary' }] : []}
            />
          ) : null}
          {listState.status === 'ready' && conversations.length > 0 ? (
            <ConversationList
              conversations={conversations}
              selectedId={selectedId ?? undefined}
              itemPath={itemPath}
              showCustomer={!customer}
            />
          ) : null}
        </div>

        {selected ? (
          <div className="min-w-0">
            {detailState.status === 'loading' ? (
              customer ? <CatalogSkeleton variant="list" label="جاري تحميل المحادثة..." /> : <WorkspaceSkeleton label="جاري تحميل المحادثة..." />
            ) : null}
            {detailState.status === 'error' ? (
              customer ? (
                <CatalogErrorState message={describeSupportLoadError(detailState.message)} onRetry={() => void reloadDetail()} />
              ) : (
                <WorkspaceErrorState message={describeSupportLoadError(detailState.message)} onRetry={() => void reloadDetail()} />
              )
            ) : null}
            {detailState.status === 'ready' && detailState.data ? (
              <ConversationThread
                conversation={detailState.data}
                composerValue={draft}
                sending={sending}
                sendError={sendError}
                onComposerChange={setDraft}
                onSend={() => void send()}
                backTo={listPath}
                newConversationTo={newPath}
                showCustomer={!customer}
                statusActions={
                  managed && managed.allowed_transitions.length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                      {managed.allowed_transitions.map((transition) => (
                        <button
                          key={transition.status}
                          type="button"
                          disabled={statusUpdating !== null}
                          onClick={() => void changeStatus(transition.status)}
                          className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-60"
                        >
                          {statusUpdating === transition.status ? 'جاري التحديث...' : transition.label}
                        </button>
                      ))}
                    </div>
                  ) : null
                }
              />
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  )
}
