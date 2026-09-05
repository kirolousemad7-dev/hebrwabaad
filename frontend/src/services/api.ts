import type { ApiError, ApiSuccess } from '../types/api'
import { MISSING_PRODUCTION_API_URL, normalizeApiBaseUrl } from '../utils/apiBaseUrl'

const configuredApiUrl = import.meta.env.VITE_API_URL
const API_BASE_URL = configuredApiUrl
  ? normalizeApiBaseUrl(configuredApiUrl)
  : import.meta.env.DEV
    ? 'http://127.0.0.1:8000'
    : (() => {
        throw new Error(MISSING_PRODUCTION_API_URL)
      })()
// MVP Bearer storage: localStorage is readable by any XSS on this origin.
// HttpOnly cookies would be safer, but this project uses Sanctum personal
// access tokens (Authorization: Bearer), not first-party SPA cookie auth.
const TOKEN_KEY = 'hebr_abaad_token'

export class ApiRequestError extends Error {
  status: number
  body: ApiError | null

  constructor(message: string, status: number, body: ApiError | null) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.body = body
  }
}

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function storeToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearStoredToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

function headers(includeJson = false): HeadersInit {
  const token = getStoredToken()
  const result: Record<string, string> = {
    Accept: 'application/json',
  }

  if (includeJson) {
    result['Content-Type'] = 'application/json'
  }

  if (token) {
    result.Authorization = `Bearer ${token}`
  }

  return result
}

async function parseResponse<T>(response: Response): Promise<ApiSuccess<T>> {
  if (response.status === 204) {
    return { success: true, data: null as T }
  }

  const raw = await response.text()
  const jsonStart = raw.indexOf('{')
  const jsonText = jsonStart >= 0 ? raw.slice(jsonStart) : raw

  let payload: ApiSuccess<T> | ApiError
  try {
    payload = JSON.parse(jsonText) as ApiSuccess<T> | ApiError
  } catch {
    throw new ApiRequestError('Request failed.', response.status, null)
  }

  if (!response.ok || !payload.success) {
    const message = 'success' in payload && payload.success === false
      ? payload.message
      : 'Request failed.'

    throw new ApiRequestError(message, response.status, payload.success === false ? payload : null)
  }

  return payload
}

export async function apiGet<T>(path: string): Promise<ApiSuccess<T>> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: headers(),
  })

  return parseResponse<T>(response)
}

async function apiSend<T>(
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  path: string,
  body?: unknown,
): Promise<ApiSuccess<T>> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers: headers(body !== undefined),
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  return parseResponse<T>(response)
}

export function apiPost<T>(path: string, body?: unknown): Promise<ApiSuccess<T>> {
  return apiSend<T>('POST', path, body)
}

export async function apiPostForm<T>(path: string, body: FormData): Promise<ApiSuccess<T>> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: 'POST',
    headers: headers(),
    body,
  })

  return parseResponse<T>(response)
}

export function apiPut<T>(path: string, body?: unknown): Promise<ApiSuccess<T>> {
  return apiSend<T>('PUT', path, body)
}

export function apiPatch<T>(path: string, body?: unknown): Promise<ApiSuccess<T>> {
  return apiSend<T>('PATCH', path, body)
}

export function apiDelete<T>(path: string): Promise<ApiSuccess<T>> {
  return apiSend<T>('DELETE', path)
}

function filenameFromDisposition(header: string | null, fallback: string): string {
  if (!header) {
    return fallback
  }

  const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i)
  if (utfMatch?.[1]) {
    try {
      return decodeURIComponent(utfMatch[1])
    } catch {
      return fallback
    }
  }

  const basicMatch = header.match(/filename="?([^"]+)"?/i)
  return basicMatch?.[1] ?? fallback
}

export async function apiDownload(path: string, fallbackName: string): Promise<void> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: headers(),
  })

  if (!response.ok) {
    await parseResponse<null>(response)
  }

  const blob = await response.blob()
  const filename = filenameFromDisposition(response.headers.get('content-disposition'), fallbackName)
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.rel = 'noopener'
  document.body.append(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export async function apiOpenInline(path: string): Promise<void> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: headers(),
  })

  if (!response.ok) {
    await parseResponse<null>(response)
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener')
}

export { API_BASE_URL }
