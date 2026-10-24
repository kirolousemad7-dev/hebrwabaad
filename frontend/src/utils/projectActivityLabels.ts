import type { ProjectActivity } from '../types/api'
import { PROJECT_STATUS_LABELS, TASK_STATUS_LABELS } from './workspaceProjects'

const ACTION_LABELS: Record<string, string> = {
  'project.created': 'تم إنشاء المشروع',
  'project.updated': 'تم تحديث المشروع',
  'project.status_changed': 'تم تغيير حالة المشروع',
  'project.account_manager_assigned': 'تم تعيين مدير الحساب',
  'project.member_added': 'تمت إضافة عضو للفريق',
  'project.member_removed': 'تمت إزالة عضو من الفريق',
  'task.created': 'تم إنشاء مهمة',
  'task.updated': 'تم تحديث المهمة',
  'task.status_changed': 'تم تغيير حالة المهمة',
  'task.completed': 'تم إكمال المهمة',
  'task.assigned': 'تم تعيين مهمة',
  'milestone.created': 'تم إنشاء معلم',
  'milestone.updated': 'تم تحديث المعلم',
  'milestone.completed': 'تم إكمال المعلم',
  'note.created': 'تمت إضافة ملاحظة',
  'reference.linked': 'تم ربط مرجع بالمهمة',
  'file.uploaded': 'تم رفع ملف',
  'file.updated': 'تم تحديث الملف',
  'file.client_visibility_changed': 'تم تغيير ظهور الملف للعميل',
  'calendar_item.created': 'تم إنشاء عنصر تقويم',
  'calendar_item.updated': 'تم تحديث عنصر التقويم',
  'calendar_item.completed': 'تم إكمال عنصر التقويم',
  'customer.file_uploaded': 'رفع العميل ملفًا',
  'customer.project_action': 'إجراء من العميل على المشروع',
  'customer.milestone_action': 'إجراء من العميل على معلم',
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
    return action.startsWith('task.') || action === 'note.created' || action === 'reference.linked'
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

function metaText(metadata: ProjectActivity['metadata'], key: string): string | null {
  const value = metadata?.[key]
  if (typeof value === 'string' && value.trim() !== '') return value.trim()
  return null
}

function quoted(value: string): string {
  return `"${value}"`
}

function taskTitle(activity: ProjectActivity, titles?: Record<number, string>): string | null {
  const fromMetadata = metaText(activity.metadata, 'title')
  if (fromMetadata) return fromMetadata

  if (activity.entity_type === 'task' && activity.entity_id != null) {
    return titles?.[activity.entity_id] ?? null
  }

  const taskId = activity.metadata?.task_id
  if (typeof taskId === 'number') return titles?.[taskId] ?? null

  return null
}

export function projectActivityHeadline(activity: ProjectActivity, titles?: Record<number, string>): string {
  const title = taskTitle(activity, titles)
  const actor = activity.actor?.name?.trim() || null
  const taskStatus = metaText(activity.metadata, 'new_status')
  const taskStatusLabel = taskStatus ? TASK_STATUS_LABELS[taskStatus] : null
  const projectStatusLabel = taskStatus ? PROJECT_STATUS_LABELS[taskStatus] : null
  const fileName = metaText(activity.metadata, 'file_name')
  const assignee = metaText(activity.metadata, 'assignee_name')
  const manager = metaText(activity.metadata, 'account_manager_name')

  switch (activity.action) {
    case 'project.created':
      return title ? `تم إنشاء المشروع ${quoted(title)}` : 'تم إنشاء المشروع'
    case 'project.status_changed':
      return projectStatusLabel ? `تم تغيير حالة المشروع إلى ${projectStatusLabel}` : 'تم تغيير حالة المشروع'
    case 'project.account_manager_assigned':
      return manager ? `تم تعيين ${manager} مديرًا للحساب` : 'تم تعيين مدير الحساب'
    case 'task.created':
      return title ? `تم إنشاء مهمة ${quoted(title)}` : 'تم إنشاء مهمة'
    case 'task.updated':
      return title ? `تم تحديث المهمة ${quoted(title)}` : 'تم تحديث المهمة'
    case 'task.status_changed':
      if (title && taskStatusLabel) return `تم تغيير حالة مهمة ${quoted(title)} إلى ${taskStatusLabel}`
      if (title) return `تم تغيير حالة المهمة ${quoted(title)}`
      return 'تم تغيير حالة المهمة'
    case 'task.completed':
      return title ? `تم إكمال مهمة ${quoted(title)}` : 'تم إكمال المهمة'
    case 'task.assigned':
      if (title && assignee) return `تم تعيين المهمة ${quoted(title)} إلى ${assignee}`
      if (title) return `تم تعيين مهمة ${quoted(title)}`
      return 'تم تعيين مهمة'
    case 'note.created':
      if (actor && title) return `أضاف ${actor} ملاحظة على المهمة ${quoted(title)}`
      if (actor) return `أضاف ${actor} ملاحظة`
      return 'تمت إضافة ملاحظة'
    case 'reference.linked':
      return title ? `تم ربط مرجع بالمهمة ${quoted(title)}` : 'تم ربط مرجع بالمهمة'
    case 'file.uploaded':
    case 'customer.file_uploaded':
      return fileName ? `تم رفع ملف ${quoted(fileName)} إلى المشروع` : 'تم رفع ملف إلى المشروع'
    case 'milestone.created':
      return title ? `تم إنشاء معلم ${quoted(title)}` : 'تم إنشاء معلم'
    case 'milestone.completed':
      return title ? `تم إكمال المعلم ${quoted(title)}` : 'تم إكمال المعلم'
    default:
      return projectActivityLabel(activity.action)
  }
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
