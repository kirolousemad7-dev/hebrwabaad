import { FormEvent, useState } from 'react'
import { motion, useReducedMotion } from 'framer-motion'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { ApiRequestError } from '../../services/api'
import { submitContactInquiry } from '../../services/marketing'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { fadeUp, motionOrReduced, slideLeft, slideRight, staggerContainer } from '../../utils/marketingMotion'
import { LandingCta } from './LandingCta'

export function ContactSection() {
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
  const hasContactInfo = Boolean(phoneValue || emailValue || whatsapp)

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
    <section id="contact" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
        <motion.div
          className="space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.25 }}
        >
          <motion.div variants={motionOrReduced(reduceMotion, slideRight)} className="space-y-4">
            <p className="brand-label text-brand-ink-500">تواصل معنا</p>
            <h2 className="text-[clamp(1.85rem,1.3rem+1.6vw,3rem)] font-bold leading-tight text-brand-ink-900">
              خلينا نتكلم عن مشروعك
            </h2>
            <p className="max-w-md text-base leading-9 text-brand-ink-500">
              أرسل فكرتك عبر النموذج، أو أنشئ حساباً للدخول إلى المنصة ومتابعة مشروعك مباشرة.
            </p>
          </motion.div>

          {hasContactInfo ? (
            <motion.ul variants={motionOrReduced(reduceMotion, fadeUp)} className="space-y-3 text-sm text-brand-ink-700">
              {phoneValue ? (
                <li>
                  <span className="text-brand-ink-500">الهاتف: </span>
                  <a href={`tel:${phoneValue}`} className="font-medium text-brand-ink-900 hover:text-brand-cobalt-700">
                    {phoneValue}
                  </a>
                </li>
              ) : null}
              {emailValue ? (
                <li>
                  <span className="text-brand-ink-500">البريد: </span>
                  <a href={`mailto:${emailValue}`} className="font-medium text-brand-ink-900 hover:text-brand-cobalt-700">
                    {emailValue}
                  </a>
                </li>
              ) : null}
              {whatsapp ? (
                <li>
                  <a
                    href={whatsapp}
                    target="_blank"
                    rel="noreferrer"
                    className="font-medium text-brand-cobalt-700 hover:underline"
                  >
                    واتساب
                  </a>
                </li>
              ) : null}
            </motion.ul>
          ) : (
            <motion.p
              variants={motionOrReduced(reduceMotion, fadeUp)}
              className="max-w-md border-s-2 border-brand-cobalt-500 ps-4 text-sm leading-7 text-brand-ink-500"
            >
              يمكنك الإرسال عبر النموذج أو إنشاء حساب — بيانات التواصل الرسمية تظهر هنا عند تفعيلها من إعدادات المنصة.
            </motion.p>
          )}

          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)}>
            <LandingCta to="/register" variant="navy">
              إنشاء حساب
            </LandingCta>
          </motion.div>
        </motion.div>

        <motion.div
          variants={motionOrReduced(reduceMotion, slideLeft)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
        >
          {done ? (
            <FeedbackBanner kind="success">وصلنا رسالتك. سنعود إليك عبر البريد.</FeedbackBanner>
          ) : (
            <form
              onSubmit={(event) => void handleSubmit(event)}
              className="space-y-4 rounded-[1.5rem] border border-brand-ink-100 bg-brand-paper p-6 sm:p-8"
            >
              {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
              <label className="block space-y-1.5 text-sm">
                <span className="text-brand-ink-700">الاسم</span>
                <input required value={name} onChange={(event) => setName(event.target.value)} className="w-full" />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span className="text-brand-ink-700">البريد الإلكتروني</span>
                <input
                  required
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  className="w-full"
                />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span className="text-brand-ink-700">الهاتف (اختياري)</span>
                <input value={phone} onChange={(event) => setPhone(event.target.value)} className="w-full" />
              </label>
              <label className="block space-y-1.5 text-sm">
                <span className="text-brand-ink-700">الرسالة</span>
                <textarea
                  required
                  minLength={10}
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  className="w-full"
                />
              </label>
              <button type="submit" disabled={loading} className="brand-btn-primary w-full disabled:opacity-60">
                {loading ? 'جاري الإرسال...' : 'إرسال'}
              </button>
            </form>
          )}
        </motion.div>
      </div>
    </section>
  )
}
