import { FormEvent, useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { ConsultantLeadForm } from '../components/consultant/ConsultantLeadForm'
import { ConsultantMessages } from '../components/consultant/ConsultantMessages'
import { ConsultantProgress } from '../components/consultant/ConsultantProgress'
import { ConsultantPrompt } from '../components/consultant/ConsultantPrompt'
import { ConsultantRecommendation } from '../components/consultant/ConsultantRecommendation'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { ApiRequestError } from '../services/api'
import {
  answerConsultation,
  captureConsultationLead,
  clearConsultationToken,
  getConsultation,
  getStoredConsultationToken,
  messageConsultation,
  recordConsultationEvent,
  resetConsultation,
  startConsultation,
  storeConsultationToken,
} from '../services/consultations'
import type { ConsultantSession } from '../types/api'

export function ConsultantPage() {
  const [params] = useSearchParams()
  const [session, setSession] = useState<ConsultantSession | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [composer, setComposer] = useState('')
  const [showLead, setShowLead] = useState(params.get('lead') === '1')
  const threadRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    void bootstrap()
  }, [])

  useEffect(() => {
    threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' })
  }, [session?.messages.length, session?.prompt?.id])

  async function bootstrap() {
    setLoading(true)
    setError(null)

    try {
      const stored = getStoredConsultationToken()
      if (stored) {
        try {
          const existing = await getConsultation(stored)
          setSession(existing.data)
          return
        } catch {
          clearConsultationToken()
        }
      }

      const created = await startConsultation()
      storeConsultationToken(created.data.token)
      setSession(created.data)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر بدء الاستشارة.')
    } finally {
      setLoading(false)
    }
  }

  function applySession(next: ConsultantSession) {
    storeConsultationToken(next.token)
    setSession(next)
    if (next.status === 'COMPLETED' && (next.recommendations?.cta.type === 'talk_expert' || next.recommendations?.cta.type === 'book_consultation')) {
      setShowLead(true)
    }
  }

  async function handleAnswer(value: unknown) {
    if (!session?.prompt || busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const response = await answerConsultation(session.token, session.prompt.id, value)
      applySession(response.data)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر حفظ الإجابة.')
    } finally {
      setBusy(false)
    }
  }

  async function handleMessage(event: FormEvent) {
    event.preventDefault()
    if (!session || composer.trim() === '' || busy) {
      return
    }

    const text = composer.trim()
    setComposer('')
    setBusy(true)
    setError(null)

    try {
      const response = await messageConsultation(session.token, text)
      applySession(response.data)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الرسالة.')
    } finally {
      setBusy(false)
    }
  }

  async function handleReset() {
    if (!session || busy) {
      return
    }

    setBusy(true)
    setError(null)
    setShowLead(false)

    try {
      const response = await resetConsultation(session.token)
      applySession(response.data)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إعادة الاستشارة.')
    } finally {
      setBusy(false)
    }
  }

  async function handleLead(payload: {
    name: string
    email: string
    phone?: string
    business_name?: string
    contact_method: 'email' | 'phone' | 'whatsapp'
  }) {
    if (!session) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      await captureConsultationLead(session.token, payload)
      setSession({ ...session, lead_captured: true })
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال البيانات.')
    } finally {
      setBusy(false)
    }
  }

  async function handleCta(type: string, path: string) {
    if (!session) {
      return
    }

    try {
      await recordConsultationEvent(session.token, type, { path })
    } catch {
      // Analytics must not block conversion.
    }
  }

  if (loading) {
    return (
      <section className="space-y-4" aria-busy="true">
        <header className="space-y-2">
          <h1 className="text-2xl font-semibold sm:text-3xl">مستشار حبر الذكي</h1>
          <p className="text-sm text-slate-600">جاري تجهيز الاستشارة...</p>
        </header>
        <CatalogSkeleton variant="list" label="جاري تجهيز الاستشارة..." />
      </section>
    )
  }

  if (error && !session) {
    return (
      <section className="space-y-4">
        <h1 className="text-2xl font-semibold">مستشار حبر الذكي</h1>
        <FeedbackBanner kind="error">{error}</FeedbackBanner>
        <button
          type="button"
          onClick={() => void bootstrap()}
          className="min-h-11 rounded-lg bg-slate-900 px-4 text-sm text-white"
        >
          إعادة المحاولة
        </button>
      </section>
    )
  }

  if (!session) {
    return null
  }

  const completed = session.status === 'COMPLETED'

  return (
    <section className="space-y-6">
      <header className="space-y-3">
        <p className="text-sm font-medium text-amber-800">HEBR AI Consultant</p>
        <h1 className="text-3xl font-semibold">مستشار حبر الذكي</h1>
        <p className="max-w-2xl leading-7 text-slate-600">
          ليس دردشة عامة. نفهم نشاطك وأهدافك ثم نرشّح خدمة أو باقة حقيقية من المنصة مع سبب واضح.
        </p>
        <div className="flex flex-wrap gap-2">
          <Link
            to="/"
            className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            رجوع
          </Link>
          <button
            type="button"
            onClick={() => void handleReset()}
            disabled={busy}
            className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm disabled:opacity-50"
          >
            إعادة الاستشارة
          </button>
        </div>
      </header>

      <ConsultantProgress
        current={session.progress.current}
        total={session.progress.total}
        percent={session.progress.percent}
      />

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div ref={threadRef} className="max-h-[min(28rem,55vh)] space-y-4 overflow-y-auto overflow-x-hidden scroll-pt-2 p-1">
          <ConsultantMessages messages={session.messages} />
          {busy ? (
            <p className="text-sm text-slate-500" aria-live="polite">
              المستشار يفكر...
            </p>
          ) : null}
        </div>
      </div>

      {!completed && session.prompt ? (
        <ConsultantPrompt
          prompt={session.prompt}
          busy={busy}
          onAnswer={(value) => void handleAnswer(value)}
          onSkip={() => void handleAnswer('__skip__')}
        />
      ) : null}

      {completed ? (
        <>
          <ConsultantRecommendation
            session={session}
            onCta={(type, path) => void handleCta(type, path)}
            onShowLead={() => setShowLead(true)}
          />
          {showLead ? (
            <ConsultantLeadForm
              busy={busy}
              captured={session.lead_captured}
              defaultBusiness={session.state.business_name}
              onSubmit={(payload) => void handleLead(payload)}
            />
          ) : null}
        </>
      ) : null}

      <form className="sticky bottom-0 z-10 space-y-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur" onSubmit={(event) => void handleMessage(event)}>
        <label className="block text-sm text-slate-600" htmlFor="consultant-composer">
          أو اكتب رسالتك مباشرة
        </label>
        <div className="flex flex-col gap-2 sm:flex-row">
          <input
            id="consultant-composer"
            value={composer}
            onChange={(event) => setComposer(event.target.value)}
            disabled={busy}
            placeholder="مثال: عندي مطعم فراخ فرعين وأريد زيادة الطلبات"
            className="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
          <button
            type="submit"
            disabled={busy || composer.trim() === ''}
            className="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white disabled:opacity-50"
          >
            إرسال
          </button>
        </div>
      </form>
    </section>
  )
}
