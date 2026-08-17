import { apiRequest } from './http'

export type ClientStatus = 'active' | 'inactive'
export type DocumentType = 'cpf' | 'cnpj'

export type Client = {
  id: number
  name: string
  document_type: DocumentType
  document: string
  address: string | null
  contact: string | null
  status: ClientStatus
}

export type PaginatedResponse<T> = {
  data: T[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

type Resource<T> = {
  data: T
}

export type ClientFilters = {
  name?: string
  status?: ClientStatus | ''
  document?: string
}

export type CreateClientPayload = {
  name: string
  document_type: DocumentType
  document: string
  address?: string
  contact?: string
}

function queryString(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      search.set(key, String(value))
    }
  })

  const query = search.toString()

  return query ? `?${query}` : ''
}

export function fetchClients(filters: ClientFilters = {}): Promise<PaginatedResponse<Client>> {
  return apiRequest<PaginatedResponse<Client>>(`/api/clients${queryString({ ...filters, per_page: 20 })}`)
}

export async function createClient(payload: CreateClientPayload): Promise<Client> {
  const response = await apiRequest<Resource<Client>>('/api/clients', {
    method: 'POST',
    json: payload,
  })

  return response.data
}

export async function activateClient(clientId: number): Promise<Client> {
  const response = await apiRequest<Resource<Client>>(`/api/clients/${clientId}/activate`, {
    method: 'PATCH',
  })

  return response.data
}

export async function deactivateClient(clientId: number): Promise<Client> {
  const response = await apiRequest<Resource<Client>>(`/api/clients/${clientId}/deactivate`, {
    method: 'PATCH',
  })

  return response.data
}
