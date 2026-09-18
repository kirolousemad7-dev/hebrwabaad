import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { ApiRequestError } from '../../services/api'
import {
  getNeedsDiscoverySteps,
  submitNeedsDiscovery,
  type NeedsDiscoveryAnswer,
  type NeedsDiscoveryQuickReply,
  type NeedsDiscoveryStep,
  type NeedsDiscoverySubmitResult,
} from '../../services/needsDiscovery'

type ChatBubble = {
  id: string
  role: 'bot' | 'user'
  text: string
}

type ContactForm = {
  name: string
  phone: string
  email: string
  company: string
}

const INITIAL_CONTACT: ContactForm = {
  name: '',
  phone: '',
  email: '',
  company: '',
}

export function NeedsDiscoveryWidget() {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [title, setTitle] = useState('اكتشف احتياجك')
  const [steps, setSteps] = useState<NeedsDiscoveryStep[]>([])
  const [stepIndex, setStepIndex] = useState(0)
  const [answers, setAnswers] = useState<Record<string, NeedsDiscoveryAnswer>>({})
  const [messages, setMessages] = useState<ChatBubble[]>([])
  const [textDraft, setTextDraft] = useState('')
  const [contact, setContact] = useState<ContactForm>(INITIAL_CONTACT)
  const [files, setFiles] = useState<File[]>([])
  const [awaitingFiles, setAwaitingFiles] = useState(false)
  const [done, setDone] = useState<NeedsDiscoverySubmitResult | null>(null)
  const threadRef = useRef<HTMLDivElement>(null)
  const bootstrapped = useRef(false)

  const currentStep = steps[stepIndex] ?? null

  const progressLabel = useMemo(() => {
    if (!steps.length || done) {
      return null
    }
    return `${Math.min(stepIndex + 1, steps.length)} / ${steps.length}`
  }, [done, stepIndex, steps.length])

  useEffect(() => {
    if (!open || bootstrapped.current) {
      return
    }
    void bootstrap()
  }, [open])

  useEffect(() => {
    threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' })
  }, [messages, currentStep?.id, awaitingFiles, done])

  async function bootstrap() {
    setLoading(true)
    setError(null)
    try {
      const response = await getNeedsDiscoverySteps()
      setTitle(response.data.title || 'اكتشف احتياجك')
      const nextSteps = response.data.steps || []
      setSteps(nextSteps)
      const intro: ChatBubble[] = [
        {
          id: 'welcome',
          role: 'bot',
          text: 'مرحباً! نجيب على بضعة أسئلة سريعة لنحدد احتياجك بدقة.',
        },
      ]
      if (nextSteps[0]) {
        intro.push({ id: 'step-0', role: 'bot', text: nextSteps[0].prompt })
      }
      setMessages(intro)
      bootstrapped.current = true
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تحميل الأسئلة.')
    } finally {
      setLoading(false)
    }
  }

  function pushBot(text: string) {
    setMessages((prev) => [...prev, { id: `bot-${prev.length}-${Date.now()}`, role: 'bot', text }])
  }

  function pushUser(text: string) {
    setMessages((prev) => [...prev, { id: `user-${prev.length}-${Date.now()}`, role: 'user', text }])
  }

  function advanceAfterAnswer(nextAnswers: Record<string, NeedsDiscoveryAnswer>, fromIndex: number) {
    const nextIndex = fromIndex + 1
    if (nextIndex >= steps.length) {
      void finalize(nextAnswers, files)
      return
    }
    setStepIndex(nextIndex)
    pushBot(steps[nextIndex].prompt)
  }

  function handleQuickReply(reply: NeedsDiscoveryQuickReply) {
    if (!currentStep || busy || done) {
      return
    }

    pushUser(reply.label)
    const nextAnswers = {
      ...answers,
      [currentStep.id]: {
        id: reply.id,
        label: reply.label,
        value: reply.value,
        service_id: reply.service_id,
        category: reply.category,
      },
    }
    setAnswers(nextAnswers)

    if (currentStep.id === 'has_files' && reply.value === 'yes') {
      setAwaitingFiles(true)
      pushBot('يمكنك رفع الملفات الآن، ثم اضغط متابعة.')
      return
    }

    advanceAfterAnswer(nextAnswers, stepIndex)
  }

  function handleSkipFiles() {
    setAwaitingFiles(false)
    advanceAfterAnswer(answers, stepIndex)
  }

  function handleContinueWithFiles() {
    setAwaitingFiles(false)
    if (files.length) {
      pushUser(`تم اختيار ${files.length} ملف`)
    }
    advanceAfterAnswer(answers, stepIndex)
  }

  function handleTextSend(event?: FormEvent) {
    event?.preventDefault()
    if (!currentStep || !textDraft.trim() || busy || done) {
      return
    }
    const value = textDraft.trim()
    pushUser(value)
    const nextAnswers = {
      ...answers,
      [currentStep.id]: { label: value, value },
    }
    setAnswers(nextAnswers)
    setTextDraft('')
    advanceAfterAnswer(nextAnswers, stepIndex)
  }

  async function handleContactSubmit(event: FormEvent) {
    event.preventDefault()
    if (!currentStep || busy || done) {
      return
    }
    if (!contact.name.trim()) {
      setError('الاسم مطلوب.')
      return
    }
    if (!contact.phone.trim() && !contact.email.trim()) {
      setError('أدخل جوالاً أو بريداً إلكترونياً.')
      return
    }

    pushUser(contact.name.trim())
    const nextAnswers = {
      ...answers,
      contact: {
        name: contact.name.trim(),
        phone: contact.phone.trim() || undefined,
        email: contact.email.trim() || undefined,
        company: contact.company.trim() || undefined,
        label: contact.name.trim(),
        value: contact.name.trim(),
      },
    }
    setAnswers(nextAnswers)
    await finalize(nextAnswers, files)
  }

  async function finalize(finalAnswers: Record<string, NeedsDiscoveryAnswer>, uploadFiles: File[]) {
    setBusy(true)
    setError(null)
    try {
      const form = new FormData()
      const name = contact.name.trim() || String(finalAnswers.contact?.name ?? '')
      const phone = contact.phone.trim() || String(finalAnswers.contact?.phone ?? '')
      const email = contact.email.trim() || String(finalAnswers.contact?.email ?? '')
      const company = contact.company.trim() || String(finalAnswers.contact?.company ?? '')

      form.append('name', name)
      if (phone) form.append('phone', phone)
      if (email) form.append('email', email)
      if (company) form.append('company', company)
      form.append('answers', JSON.stringify(finalAnswers))
      uploadFiles.forEach((file) => form.append('attachments[]', file))

      const response = await submitNeedsDiscovery(form)
      setDone(response.data)
      pushBot(
        `تم استلام احتياجك. المرجع: ${response.data.reference}. سيتواصل معك فريق حبر وأبعاد قريباً.`,
      )
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الطلب.')
    } finally {
      setBusy(false)
    }
  }

  function resetChat() {
    bootstrapped.current = false
    setStepIndex(0)
    setAnswers({})
    setMessages([])
    setTextDraft('')
    setContact(INITIAL_CONTACT)
    setFiles([])
    setAwaitingFiles(false)
    setDone(null)
    setError(null)
    void bootstrap()
  }

  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex justify-start p-4 sm:p-6">
      <div className="pointer-events-auto flex max-w-full flex-col items-start gap-3">
        {open ? (
          <section
            className="flex h-[min(34rem,78vh)] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
            aria-label={title}
          >
            <header className="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-900 px-4 py-3 text-white">
              <div>
                <p className="text-sm font-semibold">{title}</p>
                {progressLabel ? <p className="text-xs text-slate-300">خطوة {progressLabel}</p> : null}
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-md px-2 py-1 text-sm text-slate-200 hover:bg-white/10"
                aria-label="إغلاق"
              >
                إغلاق
              </button>
            </header>

            <div ref={threadRef} className="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-3 py-3">
              {loading ? <p className="text-sm text-slate-500">جاري التحميل…</p> : null}
              {messages.map((message) => (
                <div
                  key={message.id}
                  className={`flex ${message.role === 'user' ? 'justify-start' : 'justify-end'}`}
                >
                  <div
                    className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-6 ${
                      message.role === 'user'
                        ? 'rounded-br-md bg-[#315CFF] text-white'
                        : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'
                    }`}
                  >
                    {message.text}
                  </div>
                </div>
              ))}

              {done?.recommended_services?.length ? (
                <div className="rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                  <p className="font-medium text-slate-900">خدمات مقترحة</p>
                  <ul className="mt-2 list-disc space-y-1 pr-4">
                    {done.recommended_services.map((service) => (
                      <li key={service.id}>{service.name}</li>
                    ))}
                  </ul>
                </div>
              ) : null}

              {error ? (
                <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                  {error}
                </p>
              ) : null}
            </div>

            <footer className="border-t border-slate-200 bg-white p-3">
              {done ? (
                <button
                  type="button"
                  onClick={resetChat}
                  className="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                  بدء محادثة جديدة
                </button>
              ) : awaitingFiles ? (
                <div className="space-y-2">
                  <input
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv"
                    onChange={(event) => setFiles(Array.from(event.target.files ?? []).slice(0, 5))}
                    className="block w-full text-xs text-slate-600"
                  />
                  {files.length ? (
                    <p className="text-xs text-slate-500">{files.length} ملف جاهز للرفع</p>
                  ) : null}
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={handleContinueWithFiles}
                      disabled={busy}
                      className="flex-1 rounded-lg bg-[#315CFF] px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                    >
                      متابعة
                    </button>
                    <button
                      type="button"
                      onClick={handleSkipFiles}
                      disabled={busy}
                      className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700"
                    >
                      تخطي
                    </button>
                  </div>
                </div>
              ) : currentStep?.type === 'contact' ? (
                <form className="space-y-2" onSubmit={handleContactSubmit}>
                  <input
                    required
                    value={contact.name}
                    onChange={(event) => setContact((prev) => ({ ...prev, name: event.target.value }))}
                    placeholder="الاسم"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  />
                  <input
                    value={contact.phone}
                    onChange={(event) => setContact((prev) => ({ ...prev, phone: event.target.value }))}
                    placeholder="الجوال"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  />
                  <input
                    type="email"
                    value={contact.email}
                    onChange={(event) => setContact((prev) => ({ ...prev, email: event.target.value }))}
                    placeholder="البريد الإلكتروني"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  />
                  <input
                    value={contact.company}
                    onChange={(event) => setContact((prev) => ({ ...prev, company: event.target.value }))}
                    placeholder="الشركة (اختياري)"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  />
                  <button
                    type="submit"
                    disabled={busy}
                    className="w-full rounded-lg bg-[#315CFF] px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                  >
                    {busy ? 'جارٍ الإرسال…' : 'إرسال الاحتياج'}
                  </button>
                </form>
              ) : (
                <div className="space-y-2">
                  {currentStep?.quick_replies?.length ? (
                    <div className="flex flex-wrap gap-2">
                      {currentStep.quick_replies.map((reply) => (
                        <button
                          key={reply.id}
                          type="button"
                          disabled={busy}
                          onClick={() => handleQuickReply(reply)}
                          className="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:border-[#315CFF] hover:text-[#315CFF] disabled:opacity-60"
                        >
                          {reply.label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                  <form className="flex gap-2" onSubmit={handleTextSend}>
                    <input
                      value={textDraft}
                      onChange={(event) => setTextDraft(event.target.value)}
                      placeholder="أو اكتب إجابتك…"
                      className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                      disabled={busy || !currentStep}
                    />
                    <button
                      type="submit"
                      disabled={busy || !textDraft.trim()}
                      className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                      إرسال
                    </button>
                  </form>
                </div>
              )}
            </footer>
          </section>
        ) : null}

        <button
          type="button"
          onClick={() => setOpen((prev) => !prev)}
          className="inline-flex min-h-12 items-center gap-2 rounded-full bg-[#315CFF] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 hover:bg-[#274be0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-full bg-emerald-300" />
          اكتشف احتياجك
        </button>
      </div>
    </div>
  )
}
