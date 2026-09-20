import type { ManagedFileItem, ManagedFileListData } from '../types/api'
import { apiDownload, apiGet, apiOpenInline, apiPatch, apiPostForm } from './api'

export type FileScope = 'customer' | 'workspace'

export function managedFilesBasePath(scope: FileScope): string {
  return scope === 'customer' ? '/api/customer/files' : '/api/workspace/files'
}

export function fileClientVisibilityPath(fileId: number): string {
  return `/api/workspace/files/${fileId}/client-visibility`
}

export function fileClientVisibilityBody(isClientVisible: boolean): { is_client_visible: boolean } {
  return { is_client_visible: isClientVisible }
}

export function getManagedFiles(scope: FileScope, query = '') {
  return apiGet<ManagedFileListData>(`${managedFilesBasePath(scope)}${query}`)
}

export function getManagedFile(scope: FileScope, id: number) {
  return apiGet<ManagedFileItem>(`${managedFilesBasePath(scope)}/${id}`)
}

export function uploadManagedFile(scope: FileScope, body: FormData) {
  return apiPostForm<ManagedFileItem>(managedFilesBasePath(scope), body)
}

/**
 * Owner / managing Account Manager — publish or unpublish a file for the customer.
 * Backend remains authoritative; callers must not treat this as a frontend permission grant.
 */
export function updateFileClientVisibility(fileId: number, isClientVisible: boolean) {
  return apiPatch<ManagedFileItem>(
    fileClientVisibilityPath(fileId),
    fileClientVisibilityBody(isClientVisible),
  )
}

export function downloadManagedFile(scope: FileScope, file: ManagedFileItem) {
  return apiDownload(`${managedFilesBasePath(scope)}/${file.id}/download`, file.original_name)
}

export function previewManagedFile(scope: FileScope, file: ManagedFileItem) {
  return apiOpenInline(`${managedFilesBasePath(scope)}/${file.id}/preview`)
}
