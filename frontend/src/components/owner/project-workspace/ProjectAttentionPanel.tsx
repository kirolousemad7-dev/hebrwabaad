import type { ProjectAttention, ProjectAttentionItem } from '../../../utils/projectExecution'
import { attentionKindLabel, groupAttentionItems } from '../../../utils/projectExecution'

type Props = {
  attention: ProjectAttention | null | undefined
  onSelectItem?: (item: ProjectAttentionItem) => void
}

const KIND_ORDER = ['overdue', 'due_soon', 'waiting', 'unassigned'] as const

function kindTone(kind: string) {
  if (kind === 'overdue') return 'border-red-200 bg-red-50 text-red-900'
  if (kind === 'due_soon') return 'border-amber-200 bg-amber-50 text-amber-950'
  if (kind === 'waiting') return 'border-sky-200 bg-sky-50 text-sky-950'
  return 'border-slate-200 bg-slate-50 text-slate-800'
}

export function ProjectAttentionPanel({ attention, onSelectItem }: Props) {
  const items = attention?.items ?? []
  const counts = attention?.counts
  const groups = groupAttentionItems(items)

  if (items.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
        لا عناصر تحتاج متابعة الآن.
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {counts ? (
        <div className="flex flex-wrap gap-2 text-xs">
          {KIND_ORDER.map((kind) =>
            (counts[kind] ?? 0) > 0 ? (
              <span key={kind} className={`rounded-full border px-2.5 py-1 ${kindTone(kind)}`}>
                {attentionKindLabel(kind)}: {counts[kind].toLocaleString('ar-SA')}
              </span>
            ) : null,
          )}
        </div>
      ) : null}

      {KIND_ORDER.map((kind) => {
        const group = groups[kind]
        if (group.length === 0) return null
        return (
          <div key={kind}>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
              {attentionKindLabel(kind)}
            </h3>
            <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
              {group.map((item) => (
                <li key={`${item.kind}-${item.related_type}-${item.related_id}`}>
                  <button
                    type="button"
                    onClick={() => onSelectItem?.(item)}
                    className="flex w-full flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-start text-sm hover:bg-slate-50"
                  >
                    <span className="font-medium text-slate-900">{item.title}</span>
                    <span className="text-xs text-slate-500">
                      {item.due_date ?? '—'}
                      {item.assignee_name ? ` · ${item.assignee_name}` : ''}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )
      })}
    </div>
  )
}
