import type { ProjectClosureReadiness } from '../../../utils/projectExecution'
import { isClosureReady } from '../../../utils/projectExecution'

type Props = {
  closure: ProjectClosureReadiness | null | undefined
}

export function ProjectClosureReadiness({ closure }: Props) {
  if (!closure) {
    return null
  }

  const ready = isClosureReady(closure)

  return (
    <div
      className={`rounded-xl border px-4 py-4 ${
        ready ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'
      }`}
    >
      <p className="text-sm font-semibold">{closure.label}</p>
      {ready ? (
        <p className="mt-1 text-xs opacity-80">لا متطلبات تشغيلية معلّقة حسب البيانات الحالية.</p>
      ) : (
        <ul className="mt-2 space-y-1 text-sm">
          {closure.issues.map((issue) => (
            <li key={issue.key}>
              {issue.label}: {issue.count.toLocaleString('ar-SA')}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
