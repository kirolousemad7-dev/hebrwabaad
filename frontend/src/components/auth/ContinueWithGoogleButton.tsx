import { useEffect, useState } from 'react'
import { getGoogleAuthStatus, googleAuthRedirectUrl, type GoogleAuthIntent } from '../../services/googleAuth'

type ContinueWithGoogleButtonProps = {
  intent?: GoogleAuthIntent
  next?: string | null
  className?: string
}

const GOOGLE_ERROR_MESSAGES: Record<string, string> = {
  cancelled: 'تم إلغاء تسجيل الدخول عبر Google.',
  oauth_failed: 'تعذر إكمال تسجيل الدخول عبر Google.',
  invalid_state: 'انتهت صلاحية جلسة Google. حاول مرة أخرى.',
  account_deactivated: 'هذا الحساب غير مفعّل أو محظور.',
  account_blocked: 'هذا الحساب مرتبط بدور داخلي أو بوابة أخرى. استخدم بوابة الدخول المناسبة.',
  not_configured: 'تسجيل الدخول عبر Google غير مفعّل حالياً.',
}

export function googleErrorMessage(code: string | null): string | null {
  if (!code) {
    return null
  }

  return GOOGLE_ERROR_MESSAGES[code] ?? GOOGLE_ERROR_MESSAGES.oauth_failed
}

export function ContinueWithGoogleButton({
  intent = 'login',
  next = null,
  className = '',
}: ContinueWithGoogleButtonProps) {
  const [configured, setConfigured] = useState<boolean | null>(null)
  const [starting, setStarting] = useState(false)

  useEffect(() => {
    let cancelled = false

    getGoogleAuthStatus()
      .then((response) => {
        if (!cancelled) {
          setConfigured(response.data.configured)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setConfigured(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  if (configured === false) {
    return null
  }

  function startGoogle() {
    setStarting(true)
    window.location.assign(googleAuthRedirectUrl(intent, next))
  }

  return (
    <button
      type="button"
      disabled={configured !== true || starting}
      onClick={startGoogle}
      className={
        className
        || 'flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 disabled:opacity-60'
      }
    >
      <GoogleMark />
      {starting || configured === null ? 'جاري التحضير...' : 'المتابعة باستخدام Google'}
    </button>
  )
}

function GoogleMark() {
  return (
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 48 48">
      <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.5z" />
      <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 16.1 4 9.2 8.5 6.3 14.7z" />
      <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.3 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.1 39.4 16 44 24 44z" />
      <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.1 5.6l.1.1 6.2 5.2C39.2 36.3 44 31 44 24c0-1.3-.1-2.5-.4-3.5z" />
    </svg>
  )
}
