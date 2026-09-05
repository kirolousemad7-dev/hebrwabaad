import type { ManagedFileItem } from '../types/api'

export const FILE_COPY = {
  title: 'الملفات',
  empty: 'لا توجد ملفات بعد.',
  error: 'تعذر تحميل الملفات.',
  loading: 'جاري تحميل الملفات...',
  upload: 'رفع ملف',
  uploading: 'جاري الرفع...',
  success: 'تم رفع الملف بنجاح.',
  download: 'تنزيل',
  preview: 'عرض',
  chooseFile: 'اختر ملفًا',
  contextRequired: 'اختر المشروع أو الطلب أو المهمة.',
} as const

export const FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv'

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes.toLocaleString('ar-SA')} بايت`
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} ك.ب`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} م.ب`
}

export function fileContextLabel(file: ManagedFileItem): string {
  if (file.order?.reference) {
    return file.order.reference
  }

  if (file.project?.title) {
    return file.project.title
  }

  if (file.task?.title) {
    return file.task.title
  }

  return '—'
}

export function filesPathForRole(role: string | undefined, canViewFiles = false): string | null {
  if (role === 'CUSTOMER') {
    return '/dashboard/files'
  }

  if (role === 'OWNER') {
    return '/owner/files'
  }

  return canViewFiles ? '/workspace/files' : null
}
