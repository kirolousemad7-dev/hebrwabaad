import type { SupportMessage } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { senderDisplayName } from '../../utils/supportChat'

type MessageBubbleProps = {
  message: SupportMessage
}

export function MessageBubble({ message }: MessageBubbleProps) {
  const support = message.from_support

  return (
    <article
      className={`flex max-w-[min(100%,36rem)] flex-col gap-1 ${support ? 'self-start items-start' : 'self-end items-end'}`}
    >
      <p className="text-xs text-slate-500">{senderDisplayName(message)}</p>
      <p
        className={`whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm leading-7 ${
          support ? 'bg-white text-slate-800 shadow-sm ring-1 ring-slate-200' : 'bg-slate-900 text-white'
        }`}
      >
        {message.body}
      </p>
      <time className="text-xs text-slate-500" dateTime={message.created_at ?? undefined}>
        {formatDashboardDateTime(message.created_at)}
      </time>
    </article>
  )
}
