import { FormEvent, useState } from 'react'
import { motion, useReducedMotion } from 'framer-motion'
import { PublicCmsPage } from '../components/cms/PublicCmsPage'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { LandingCta } from '../components/landing/LandingCta'
import { usePlatformSettings } from '../context/PlatformSettingsContext'
import { ApiRequestError } from '../services/api'
import { submitContactInquiry } from '../services/marketing'
import { fadeUp, motionOrReduced, slideLeft, slideRight, staggerContainer } from '../utils/marketingMotion'

function ContactInquiryForm() {
  const reduceMotion = useReducedMotion()
  const { settings } = usePlatformSettings()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [loading, setLoading] = useState(false)

  const phoneValue = settings.contact.phone
  const emailValue = settings.contact.email
  const whatsapp = settings.contact.whatsapp_url

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setLoading(true)

    try {
      await submitContactInquiry({ name, email, phone: phone || undefined, message })
      setDone(true)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الرسالة.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-14">
      <div className="grid items-start gap-10 lg:grid-cols-2 lg:gap-14">
        <motion.div
          className="space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
        >
          <motion.div variants={motionOrReduced(reduceMotion, slideRight)} className="space-y-4">
            <h2 className="text-[clamp(1.75rem,1.25rem+1.4vw,2.5rem)] font-bold leading-tight text-brand-ink-900">
              أرسل رسالة
            </h2>
            <p className="max-w-md leading-8 text-brand-ink-500">
              أرسل استفسارك، أو ابدأ مباشرة بإنشاء حساب للدخول إلى المنصة.
            </p>
          </motion.div>

          <motion.ul variants={motionOrReduced(reduceMotion, fadeUp)} className="space-y-3 text-sm text-brand-ink-700">
            {phoneValue ? (
              <li>
                <span className="text-brand-ink-500">الهاتف: </span>
                <a href={`tel:${phoneValue}`} className="font-medium hover:text-brand-cobalt-700">
                  {phoneValue}
                </a>
              </li>
            ) : null}
            {emailValue ? (
              <li>
                <span className="text-brand-ink-500">البريد: </span>
                <a href={`mailto:${emailValue}`} className="font-medium hover:text-brand-cobalt-700">
                  {emailValue}
                </a>
              </li>
            ) : null}
            {whatsapp ? (
              <li>
                <a href={whatsapp} target="_blank" rel="noreferrer" className="font-medium text-brand-cobalt-700 hover:underline">
                  واتساب
                </a>
              </li>
            ) : null}
          </motion.ul>

          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)} className="flex flex-wrap gap-3">
            <LandingCta to="/register" variant="navy">
              ابدأ مشروعك
            </LandingCta>
            <LandingCta to="/consultant" variant="ghost">
              المستشار الذكي
            </LandingCta>
          </motion.div>
        </motion.div>

        <motion.div
          variants={motionOrReduced(reduceMotion, slideLeft)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.15 }}
        >
          {done ? (
            <FeedbackBanner kind="success">وصلنا رسالتك. سنعود إليك عبر البريد.</FeedbackBanner>
          ) : (
            <form
              onSubmit={(event) => void handleSubmit(event)}
              className="space-y-4 rounded-[1.5rem] border border-brand-ink-100 bg-white p-6 shadow-sm sm:p-8"
            >
              {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
              <label className="block space-y-1.5 text-sm">
                <span>الاسم</span>
                <input required value={name} onChange={(event) => setName(event.target.value)} />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span>البريد الإلكتروني</span>
                <input required type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span>الهاتف (اختياري)</span>
                <input value={phone} onChange={(event) => setPhone(event.target.value)} />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span>الرسالة</span>
                <textarea required minLength={10} value={message} onChange={(event) => setMessage(event.target.value)} />
              </label>
              <button type="submit" disabled={loading} className="brand-btn-primary w-full disabled:opacity-60">
                {loading ? 'جاري الإرسال...' : 'إرسال'}
              </button>
            </form>
          )}
        </motion.div>
      </div>

      <div className="relative overflow-hidden rounded-[1.75rem] bg-brand-ink-900 px-6 py-12 text-center text-white sm:px-10">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(49,92,255,0.22),transparent_45%)]" />
        <div className="relative z-10 space-y-4">
          <p className="brand-label text-brand-cobalt-300">جاهز نبدأ؟</p>
          <h3 className="text-2xl font-bold sm:text-3xl">لنبنِ شيئًا يستحق التذكّر.</h3>
          <LandingCta to="/register" variant="light">
            إنشاء حساب
          </LandingCta>
        </div>
      </div>
    </div>
  )
}

export function ContactPage() {
  return (
    <PublicCmsPage slug="contact" softFail>
      <ContactInquiryForm />
    </PublicCmsPage>
  )
}
