import type { ProjectActivity } from '../types/api'

const ACTION_LABELS: Record<string, string> = {
  'project.created': 'إنشاء المشروع',
  'project.updated': 'تحديث المشروع',
  'project.status_changed': 'تغيير حالة المشروع',
  'project.account_manager_assigned': 'تعيين مدير الحساب',
  'project.member_added': 'إضافة عضو للفريق',
  'project.member_removed': 'إزالة عضو من الفريق',
  'task.created': 'إنشاء مهمة',
  'task.updated': 'تحديث مهمة',
  'task.status_changed': 'تغيير حالة مهمة',
  'task.completed': 'إكمال مهمة',
  'task.assigned': 'تعيين مهمة',
  'milestone.created': 'إنشاء معلم',
  'milestone.updated': 'تحديث معلم',
  'milestone.completed': 'إكمال معلم',
  'file.uploaded': 'رفع ملف',
  'file.updated': 'تحديث ملف',
  'file.client_visibility_changed': 'تغيير ظهور الملف للعميل',
  'calendar_item.created': 'إنشاء عنصر تقويم',
  'calendar_item.updated': 'تحديث عنصر تقويم',
  'calendar_item.completed': 'إكمال عنصر تقويم',
  'customer.file_uploaded': 'رفع ملف من العميل',
  'customer.project_action': 'إجراء عميل على المشروع',
  'customer.milestone_action': 'إجراء عميل على معلم',
}

export function projectActivityLabel(action: string): string {
  return ACTION_LABELS[action] ?? action
}

export function projectActivityMatchesFilter(activity: ProjectActivity, filter: string): boolean {
  if (filter === 'all') {
    return true
  }

  const action = activity.action

  if (filter === 'tasks') {
    return action.startsWith('task.')
  }

  if (filter === 'files') {
    return action.startsWith('file.') || action === 'customer.file_uploaded'
  }

  if (filter === 'team') {
    return (
      action === 'project.member_added' ||
      action === 'project.member_removed' ||
      action === 'project.account_manager_assigned'
    )
  }

  if (filter === 'approvals') {
    return action.includes('approval')
  }

  if (filter === 'automation') {
    return action.includes('automation') || action.includes('workflow')
  }

  return true
}

export function projectActivitySecondaryLine(activity: ProjectActivity): string {
  const parts: string[] = [projectActivityLabel(activity.action)]

  if (activity.actor?.name) {
    parts.push(activity.actor.name)
  }

  if (activity.entity_type) {
    const entity =
      activity.entity_id !== null && activity.entity_id !== undefined
        ? `${activity.entity_type} #${activity.entity_id}`
        : activity.entity_type
    parts.push(entity)
  }

  return parts.join(' · ')
}
