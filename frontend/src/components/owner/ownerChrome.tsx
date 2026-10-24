import type { ReactNode } from 'react'

export const ownerPrimaryButtonClass =
  'inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-cobalt-500 px-3.5 text-sm font-medium text-white transition hover:bg-brand-cobalt-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 disabled:opacity-50'

export const ownerSecondaryButtonClass =
  'inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-800 transition hover:bg-[#f4f6f8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 disabled:opacity-50'

export const ownerDangerButtonClass =
  'inline-flex min-h-10 items-center justify-center rounded-lg border border-red-200 bg-white px-3.5 text-sm font-medium text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-50'

export const ownerInputClass =
  'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 disabled:bg-[#f4f6f8]'

export const ownerCardClass = 'rounded-xl border border-slate-200 bg-white'

export const ownerTableWrapClass = 'overflow-x-auto rounded-xl border border-slate-200 bg-white'

export const ownerTableHeadClass =
  'border-b border-slate-200 bg-[#f4f6f8] text-start text-xs font-medium text-slate-500'

export function OwnerPageHeader({
  title,
  description,
  action,
}: {
  title: string
  description?: string
  action?: ReactNode
}) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="min-w-0 space-y-1">
        <h1 className="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{title}</h1>
        {description ? <p className="max-w-2xl text-sm leading-6 text-slate-600">{description}</p> : null}
      </div>
      {action ? <div className="flex shrink-0 flex-wrap items-center gap-2">{action}</div> : null}
    </header>
  )
}
