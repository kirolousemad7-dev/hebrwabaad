import type { WorkspaceDomainStatus } from '../../../types/api'
import { RoleOverviewWidget } from './RoleOverviewWidget'

type Props = {
  workspaceLabel: string
  domains?: Record<string, WorkspaceDomainStatus>
}

export function MediaBuyerOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="متابعة المهام والحملات الإعلانية عند تفعيل إدارة الإعلانات، دون أرقام تجريبية."
      domainOrder={['tasks', 'projects', 'campaigns', 'budgets', 'performance', 'deadlines', 'reports']}
      domainLabels={{
        tasks: 'المهام',
        projects: 'المشاريع',
        campaigns: 'الحملات',
        budgets: 'الميزانيات',
        performance: 'الأداء',
        deadlines: 'المواعيد النهائية',
        reports: 'التقارير',
      }}
      action={{ to: '/workspace/tasks', label: 'مهامي' }}
    />
  )
}

export function VideoEditorOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="متابعة مهام المونتاج والمشاريع المعيّنة. الملفات والتعديلات تظهر هنا عند تفعيلها."
      domainOrder={['tasks', 'projects', 'files', 'revisions', 'deadlines', 'messages']}
      domainLabels={{
        tasks: 'المهام',
        projects: 'المشاريع',
        files: 'الملفات',
        revisions: 'التعديلات',
        deadlines: 'المواعيد النهائية',
        messages: 'الرسائل',
      }}
      action={{ to: '/workspace/tasks', label: 'مهامي' }}
    />
  )
}

export function AccountManagerOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="إدارة المشاريع وإنشاء المهام وتعيينها للموظفين ومتابعة التقدم. إدارة حسابات الموظفين تبقى لدى المالك."
      domainOrder={['projects', 'tasks', 'task-progress', 'deadlines', 'files', 'clients', 'client-requests']}
      domainLabels={{
        tasks: 'المهام',
        'task-progress': 'تقدم المهام',
        deadlines: 'المواعيد النهائية',
        files: 'الملفات',
        clients: 'العملاء',
        'client-requests': 'طلبات العملاء',
        projects: 'المشاريع',
      }}
      action={{ to: '/workspace/tasks', label: 'إدارة المهام' }}
    />
  )
}

export function HrOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="دليل الموظفين للقراءة فقط. إنشاء الحسابات وتغيير الأدوار وتفعيلها تبقى صلاحيات المالك."
      domainOrder={['employees', 'active-employees', 'inactive-employees', 'tasks', 'employee-requests', 'attendance']}
      domainLabels={{
        employees: 'دليل الموظفين',
        'active-employees': 'الموظفون النشطون',
        'inactive-employees': 'الموظفون غير النشطين',
        tasks: 'المهام',
        'employee-requests': 'طلبات الموظفين',
        attendance: 'الحضور والانصراف',
      }}
      action={{ to: '/workspace/directory', label: 'دليل الموظفين' }}
    />
  )
}

export function MarketingOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="متابعة المهام التسويقية. الحملات والمحتوى والتقارير تظهر هنا عند تفعيلها، دون أرقام تجريبية."
      domainOrder={['campaigns', 'tasks', 'projects', 'content', 'deadlines', 'client-requests', 'reports']}
      domainLabels={{
        campaigns: 'الحملات',
        tasks: 'المهام',
        projects: 'المشاريع',
        content: 'المحتوى',
        deadlines: 'المواعيد النهائية',
        'client-requests': 'الطلبات',
        reports: 'التقارير',
      }}
      action={{ to: '/workspace/tasks', label: 'مهامي' }}
    />
  )
}

export function EventOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="متابعة مهام الفعاليات. إدارة الفعاليات والملفات تظهر عند تفعيلها، دون بيانات تجريبية."
      domainOrder={['tasks', 'projects', 'events', 'client-requests', 'deadlines', 'files']}
      domainLabels={{
        tasks: 'المهام',
        projects: 'المشاريع',
        events: 'الفعاليات',
        'client-requests': 'الطلبات',
        deadlines: 'المواعيد النهائية',
        files: 'الملفات',
      }}
      action={{ to: '/workspace/tasks', label: 'مهامي' }}
    />
  )
}

export function PrintingOverviewWidget({ workspaceLabel, domains }: Props) {
  return (
    <RoleOverviewWidget
      workspaceLabel={workspaceLabel}
      domains={domains}
      intro="طابور طلبات الطباعة حي من النظام. الحالات الأخرى تظهر فقط إذا كانت موجودة فعلياً."
      domainOrder={['printing-queue', 'tasks', 'projects', 'deadlines', 'files', 'messages']}
      domainLabels={{
        'printing-queue': 'طابور الطباعة',
        tasks: 'المهام',
        projects: 'المشاريع',
        deadlines: 'المواعيد النهائية',
        files: 'الملفات',
        messages: 'الرسائل',
      }}
      action={{ to: '/printing-requests', label: 'طلبات الطباعة' }}
    />
  )
}
