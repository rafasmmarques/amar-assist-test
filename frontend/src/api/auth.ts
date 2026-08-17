import { apiRequest, getCsrfCookie } from './http'

export type AuthenticatedUser = {
  id: number
  name: string
  email: string
}

type Resource<T> = {
  data: T
}

export async function fetchAuthenticatedUser(): Promise<AuthenticatedUser> {
  const response = await apiRequest<Resource<AuthenticatedUser>>('/api/user')

  return response.data
}

export async function login(email: string, password: string): Promise<AuthenticatedUser> {
  await getCsrfCookie()

  const response = await apiRequest<Resource<AuthenticatedUser>>('/api/login', {
    method: 'POST',
    json: { email, password },
  })

  return response.data
}

export async function logout(): Promise<void> {
  await apiRequest<void>('/api/logout', {
    method: 'POST',
  })
}
