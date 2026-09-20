import type { ApiSuccess } from '../types/api'
import { API_BASE_URL, ApiRequestError, apiDelete, apiGet, apiDownload, apiOpenInline, apiPost, getStoredToken } from './api'

export type MediaVisibility = 'PUBLIC' | 'INTERNAL' | 'PRIVATE' | 'CUSTOMER' | 'SUPPLIER'

export type MediaEntityType =
  | 'customer'
  | 'company'
  | 'supplier'
  | 'product'
  | 'supplier_portfolio_item'
  | 'service'
  | 'package'
  | 'printing_product'
  | 'portfolio'
  | 'project'
  | 'task'
  | 'quotation'
  | 'invoice'
  | 'payment'
  | 'meeting'

export type MediaItem = {
  id: number
  original_name: string
  mime_type: string
  extension: string | null
  size: number
  checksum: string | null
  visibility: MediaVisibility
  can_preview: boolean
  is_image: boolean
  is_pdf: boolean
  is_video: boolean
  metadata: Record<string, unknown>
  collection: string
  sort_order: number
  is_primary: boolean
  entity_type: MediaEntityType | string
  entity_id: number
  created_at: string | null
  updated_at: string | null
  uploaded_by?: { id: number; name: string } | null
}

export type MediaListData = {
  items: MediaItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type MediaMeta = {
  entity_types: MediaEntityType[]
  visibilities: MediaVisibility[]
  max_kilobytes: number
  allowed_extensions: string[]
}

export const MEDIA_ACCEPT =
  '.pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx,.xls,.xlsx,.csv,.mp4,.webm'

export function getMediaMeta() {
  return apiGet<MediaMeta>('/api/media/meta')
}

export function listMedia(query = '') {
  return apiGet<MediaListData>(`/api/media${query}`)
}

export function listEntityMedia(entityType: MediaEntityType, entityId: number) {
  return listMedia(`?entity_type=${encodeURIComponent(entityType)}&entity_id=${entityId}&per_page=50`)
}

export function downloadMedia(item: MediaItem) {
  return apiDownload(`/api/media/${item.id}/download`, item.original_name)
}

export function previewMedia(item: MediaItem) {
  return apiOpenInline(`/api/media/${item.id}/preview`)
}

export function deleteMedia(id: number) {
  return apiDelete<null>(`/api/media/${id}`)
}

export function duplicateMedia(id: number) {
  return apiPost<MediaItem>(`/api/media/${id}/duplicate`)
}

export function setPrimaryMedia(id: number) {
  return apiPost<MediaItem>(`/api/media/${id}/primary`)
}

export function reorderEntityMedia(
  entityType: MediaEntityType,
  entityId: number,
  orderedIds: number[],
  collection = 'default',
) {
  return apiPost<{ items: MediaItem[] }>('/api/media/reorder', {
    entity_type: entityType,
    entity_id: entityId,
    ordered_ids: orderedIds,
    collection,
  })
}

export type UploadMediaOptions = {
  file: File
  entityType: MediaEntityType
  entityId: number
  visibility?: MediaVisibility
  signal?: AbortSignal
  onProgress?: (percent: number) => void
}

export function uploadMedia(options: UploadMediaOptions): Promise<ApiSuccess<MediaItem>> {
  const body = new FormData()
  body.append('file', options.file)
  body.append('entity_type', options.entityType)
  body.append('entity_id', String(options.entityId))
  if (options.visibility) {
    body.append('visibility', options.visibility)
  }

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${API_BASE_URL}/api/media`)
    xhr.responseType = 'json'
    xhr.setRequestHeader('Accept', 'application/json')
    const token = getStoredToken()
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`)
    }

    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable || !options.onProgress) return
      options.onProgress(Math.round((event.loaded / event.total) * 100))
    }

    xhr.onload = () => {
      const payload = xhr.response as ApiSuccess<MediaItem> | { success?: false; message?: string } | null
      if (xhr.status >= 200 && xhr.status < 300 && payload && 'success' in payload && payload.success) {
        resolve(payload as ApiSuccess<MediaItem>)
        return
      }
      reject(
        new ApiRequestError(
          payload && 'message' in payload && payload.message ? payload.message : 'Upload failed.',
          xhr.status,
          payload && 'success' in payload && payload.success === false
            ? { success: false, message: payload.message || 'Upload failed.' }
            : null,
        ),
      )
    }

    xhr.onerror = () => reject(new ApiRequestError('Upload failed.', 0, null))
    xhr.onabort = () => reject(new ApiRequestError('Upload cancelled.', 0, null))

    if (options.signal) {
      if (options.signal.aborted) {
        xhr.abort()
        return
      }
      options.signal.addEventListener('abort', () => xhr.abort(), { once: true })
    }

    xhr.send(body)
  })
}

export function replaceMedia(
  id: number,
  file: File,
  visibility?: MediaVisibility,
  onProgress?: (percent: number) => void,
  signal?: AbortSignal,
): Promise<ApiSuccess<MediaItem>> {
  const body = new FormData()
  body.append('file', file)
  if (visibility) {
    body.append('visibility', visibility)
  }

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${API_BASE_URL}/api/media/${id}`)
    xhr.responseType = 'json'
    xhr.setRequestHeader('Accept', 'application/json')
    const token = getStoredToken()
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`)
    }

    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable || !onProgress) return
      onProgress(Math.round((event.loaded / event.total) * 100))
    }

    xhr.onload = () => {
      const payload = xhr.response as ApiSuccess<MediaItem> | { success?: false; message?: string } | null
      if (xhr.status >= 200 && xhr.status < 300 && payload && 'success' in payload && payload.success) {
        resolve(payload as ApiSuccess<MediaItem>)
        return
      }
      reject(
        new ApiRequestError(
          payload && 'message' in payload && payload.message ? payload.message : 'Replace failed.',
          xhr.status,
          null,
        ),
      )
    }

    xhr.onerror = () => reject(new ApiRequestError('Replace failed.', 0, null))
    xhr.onabort = () => reject(new ApiRequestError('Upload cancelled.', 0, null))

    if (signal) {
      if (signal.aborted) {
        xhr.abort()
        return
      }
      signal.addEventListener('abort', () => xhr.abort(), { once: true })
    }

    xhr.send(body)
  })
}

export function formatMediaSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
