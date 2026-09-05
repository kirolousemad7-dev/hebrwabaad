import { Link } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import { roleLabelFor } from '../../../utils/employeeWorkspace'
import { WorkspaceWidget } from '../WorkspaceWidget'

type OverviewWidgetProps = {
  workspaceLabel: string
}

export function OverviewWidget({ workspaceLabel }: OverviewWidgetProps) {
  const { user } = useAuth()
  const roleLabel = roleLabelFor(user?.role)

  return (
    <WorkspaceWidget title="نظرة عامة">
      <dl className="space-y-2 text-sm">
        <div>
          <dt className="text-slate-500">الموظف</dt>
          <dd className="font-medium">{user?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الدور</dt>
          <dd>{roleLabel ?? user?.role ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">مساحة العمل</dt>
          <dd>{workspaceLabel}</dd>
        </div>
      </dl>
      <p className="pt-2 text-sm text-slate-600">
        أدوات المشاريع والمهام ستظهر هنا عند تفعيلها. يمكنك العودة إلى{' '}
        <Link
          to="/"
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          الموقع
        </Link>
        .
      </p>
    </WorkspaceWidget>
  )
}
