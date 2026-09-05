import { StatusBadge } from '../../ui/StatusBadge'
import { WorkspaceWidget } from '../WorkspaceWidget'

type UnavailableWidgetProps = {
  title: string
  message?: string
}

export function UnavailableWidget({ title, message }: UnavailableWidgetProps) {
  return (
    <WorkspaceWidget title={title} compact>
      <StatusBadge status="UNAVAILABLE" label="غير متاح بعد" />
      <p className="text-sm text-slate-600">{message ?? 'لا توجد بيانات فعلية لهذا القسم في النظام حتى الآن.'}</p>
    </WorkspaceWidget>
  )
}
