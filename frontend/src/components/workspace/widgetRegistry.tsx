import type { ComponentType } from 'react'
import type { WorkspaceDomainStatus } from '../../types/api'
import type { WorkspaceWidgetDefinition } from '../../utils/employeeWorkspace'
import { AccountManagerTasksWidget } from './widgets/AccountManagerTasksWidget'
import { DeveloperOverviewWidget } from './widgets/DeveloperOverviewWidget'
import {
  AccountManagerOverviewWidget,
  EventOverviewWidget,
  HrOverviewWidget,
  MarketingOverviewWidget,
  MediaBuyerOverviewWidget,
  PrintingOverviewWidget,
  VideoEditorOverviewWidget,
} from './widgets/expansionOverviews'
import { FilesWidget } from './widgets/FilesWidget'
import { GraphicDesignerOverviewWidget } from './widgets/GraphicDesignerOverviewWidget'
import { HrDirectoryWidget } from './widgets/HrDirectoryWidget'
import { HrEmployeeGroupWidget } from './widgets/HrEmployeeGroupWidget'
import { MyTasksWidget } from './widgets/MyTasksWidget'
import { OverviewWidget } from './widgets/OverviewWidget'
import { PrintingQueueWidget } from './widgets/PrintingQueueWidget'
import { ProjectsWidget } from './widgets/ProjectsWidget'
import { TaskDeadlinesWidget } from './widgets/TaskDeadlinesWidget'
import { TaskProgressWidget } from './widgets/TaskProgressWidget'
import { UnavailableWidget } from './widgets/UnavailableWidget'

type WorkspaceWidgetSlotProps = {
  widget: WorkspaceWidgetDefinition
  workspaceLabel: string
  workspaceKey?: string
  domains?: Record<string, WorkspaceDomainStatus>
}

const OVERVIEW_BY_WORKSPACE: Record<
  string,
  ComponentType<{ workspaceLabel: string; domains?: Record<string, WorkspaceDomainStatus> }>
> = {
  'web-developer': DeveloperOverviewWidget,
  'graphic-designer': GraphicDesignerOverviewWidget,
  marketing: MarketingOverviewWidget,
  event: EventOverviewWidget,
  printing: PrintingOverviewWidget,
  'media-buyer': MediaBuyerOverviewWidget,
  'video-editor': VideoEditorOverviewWidget,
  'account-manager': AccountManagerOverviewWidget,
  hr: HrOverviewWidget,
}

export function WorkspaceWidgetSlot({
  widget,
  workspaceLabel,
  workspaceKey,
  domains,
}: WorkspaceWidgetSlotProps) {
  if (widget.id === 'overview') {
    const Overview = workspaceKey ? OVERVIEW_BY_WORKSPACE[workspaceKey] : undefined

    if (Overview) {
      return <Overview workspaceLabel={workspaceLabel} domains={domains} />
    }

    return <OverviewWidget workspaceLabel={workspaceLabel} />
  }

  if (widget.id === 'printing-queue') {
    return <PrintingQueueWidget />
  }

  if (widget.id === 'projects') {
    return <ProjectsWidget />
  }

  if (widget.id === 'tasks' && workspaceKey === 'account-manager') {
    return <AccountManagerTasksWidget />
  }

  if (widget.id === 'tasks') {
    return <MyTasksWidget />
  }

  if (widget.id === 'task-progress') {
    return <TaskProgressWidget />
  }

  if (widget.id === 'deadlines') {
    return <TaskDeadlinesWidget managed={workspaceKey === 'account-manager'} />
  }

  if (widget.id === 'employees') {
    return <HrDirectoryWidget />
  }

  if (widget.id === 'active-employees') {
    return <HrEmployeeGroupWidget active />
  }

  if (widget.id === 'inactive-employees') {
    return <HrEmployeeGroupWidget active={false} />
  }

  if (widget.id === 'files') {
    return <FilesWidget />
  }

  return <UnavailableWidget title={widget.title} message={widget.unavailableMessage} />
}
