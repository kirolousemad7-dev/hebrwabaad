import type { WorkspaceDomainStatus } from '../../types/api'
import type { EmployeeWorkspaceConfig, WorkspaceWidgetDefinition } from '../../utils/employeeWorkspace'
import { EMPLOYEE_ROLE_LABELS } from '../../utils/staff'
import { WorkspaceWidgetSlot } from './widgetRegistry'
import { WorkspaceWidgetGrid } from './WorkspaceWidget'

type EmployeeDashboardShellProps = {
  config: EmployeeWorkspaceConfig
  widgets: WorkspaceWidgetDefinition[]
  domains?: Record<string, WorkspaceDomainStatus>
}

export function EmployeeDashboardShell({ config, widgets, domains }: EmployeeDashboardShellProps) {
  return (
    <section className="space-y-6">
      <header className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p className="text-sm font-medium text-amber-800">{EMPLOYEE_ROLE_LABELS[config.role]}</p>
        <h1 className="mt-1 text-2xl font-semibold">{config.label}</h1>
        <p className="mt-1 text-sm leading-7 text-slate-600">{config.description}</p>
      </header>
      <WorkspaceWidgetGrid>
        {widgets.map((widget) => (
          <WorkspaceWidgetSlot
            key={widget.id}
            widget={widget}
            workspaceLabel={config.label}
            workspaceKey={config.key}
            domains={domains}
          />
        ))}
      </WorkspaceWidgetGrid>
    </section>
  )
}
