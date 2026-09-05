import type { FormEvent } from 'react'
import { composerCanSubmit, MESSAGE_MAX_LENGTH, SUPPORT_COPY } from '../../utils/supportChat'

type MessageComposerProps = {
  value: string
  sending: boolean
  closed: boolean
  onChange: (value: string) => void
  onSubmit: () => void
}

export function MessageComposer({ value, sending, closed, onChange, onSubmit }: MessageComposerProps) {
  const disabled = !composerCanSubmit(value, sending, closed)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (disabled) {
      return
    }

    onSubmit()
  }

  if (closed) {
    return (
      <p className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
        {SUPPORT_COPY.closed}
      </p>
    )
  }

  return (
    <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={handleSubmit}>
      <label className="min-w-0 flex-1 space-y-2">
        <span className="text-sm font-medium">{SUPPORT_COPY.composerLabel}</span>
        <textarea
          name="message"
          rows={3}
          dir="auto"
          maxLength={MESSAGE_MAX_LENGTH}
          value={value}
          disabled={sending}
          onChange={(event) => onChange(event.target.value)}
          className="w-full resize-y rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm leading-7 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:bg-slate-100"
        />
      </label>
      <button
        type="submit"
        disabled={disabled}
        className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:cursor-not-allowed disabled:bg-slate-400"
      >
        {sending ? 'جاري الإرسال...' : SUPPORT_COPY.send}
      </button>
    </form>
  )
}
