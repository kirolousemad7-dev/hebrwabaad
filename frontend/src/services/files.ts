import type { ManagedFileItem, ManagedFileListData } from '../types/api'
import { apiDownload, apiGet, apiOpenInline, apiPostForm } from './api'

export type FileScope = 'customer' | 'workspace'

function basePath(scope: FileScope): string {
  return scope === 'customer' ? '/api/customer/files' : '/api/workspace/files'
}

export function getManagedFiles(scope: FileScope, query = '') {
  return apiGet<ManagedFileListData>(`${basePath(scope)}${query}`)
}

export function getManagedFile(scope: FileScope, id: number) {
  return apiGet<ManagedFileItem>(`${basePath(scope)}/${id}`)
}

export function uploadManagedFile(scope: FileScope, body: FormData) {
  return apiPostForm<ManagedFileItem>(basePath(scope), body)
}

export function downloadManagedFile(scope: FileScope, file: ManagedFileItem) {
  return apiDownload(`${basePath(scope)}/${file.id}/download`, file.original_name)
}

export function previewManagedFile(scope: FileScope, file: ManagedFileItem) {
  return apiOpenInline(`${basePath(scope)}/${file.id}/preview`)
}
