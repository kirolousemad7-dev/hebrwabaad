import type { ConsultantMessage } from '../../types/api'

type ConsultantMessagesProps = {
  messages: ConsultantMessage[]
}

export function ConsultantMessages({ messages }: ConsultantMessagesProps) {
  if (messages.length === 0) {
    return (
      <p className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
        ستظهر المحادثة هنا بعد بدء الاستشارة.
      </p>
    )
  }

  return (
    <ol className="space-y-3" aria-live="polite" aria-relevant="additions">
      {messages.map((message, index) => {
        const isUser = message.role === 'user'

        return (
          <li
            key={`${message.at ?? 'msg'}-${index}`}
            className={['flex', isUser ? 'justify-start' : 'justify-end'].join(' ')}
          >
            <div
              className={[
                'max-w-[min(100%,36rem)] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-7',
                isUser ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-800',
              ].join(' ')}
            >
              <p className="mb-1 text-xs font-medium opacity-70">{isUser ? 'أنت' : 'مستشار حبر'}</p>
              {message.text}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
