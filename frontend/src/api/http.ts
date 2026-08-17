const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080'

export type ApiValidationErrors = Record<string, string[]>

export type ApiError = {
  message?: string
  errors?: ApiValidationErrors
}

type RequestOptions = RequestInit & {
  json?: unknown
}

function readCookie(name: string): string | null {
  const value = document.cookie
    .split('; ')
    .find((row) => row.startsWith(`${name}=`))
    ?.split('=')[1]

  return value ? decodeURIComponent(value) : null
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers(options.headers)

  headers.set('Accept', 'application/json')

  if (options.json !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  const csrfToken = readCookie('XSRF-TOKEN')

  if (csrfToken) {
    headers.set('X-XSRF-TOKEN', csrfToken)
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    body: options.json === undefined ? options.body : JSON.stringify(options.json),
    credentials: 'include',
    headers,
  })

  if (!response.ok) {
    throw await response.json().catch(() => ({ message: 'Falha na requisicao.' }))
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

export function getCsrfCookie(): Promise<void> {
  return apiRequest<void>('/sanctum/csrf-cookie')
}

export function extractErrorMessage(error: unknown, fallback = 'Nao foi possivel concluir a operacao.'): string {
  const apiError = error as ApiError
  const firstFieldError = apiError.errors ? Object.values(apiError.errors).flat()[0] : null

  return firstFieldError ?? apiError.message ?? fallback
}
