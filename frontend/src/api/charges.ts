import { apiRequest, type ApiValidationErrors } from './http'
import type { PaginatedResponse } from './clients'

export type ChargeStatus = 'open' | 'paid'
export type PaymentMethod = 'boleto' | 'pix' | 'card'

export type ChargeAmounts = {
  original_amount: string
  fixed_fee_amount: string
  late_interest_amount: string
  total_amount: string
  days_late: number
  reference_date: string
}

export type PaidSnapshot = {
  paid_original_amount: string
  paid_fixed_fee_amount: string
  paid_late_interest_amount: string
  paid_total_amount: string
  paid_at: string
}

export type Charge = {
  id: number
  uuid: string
  contract_id: number
  billing_period: string
  payment_method: PaymentMethod
  original_amount: string
  fixed_fee_amount: string
  due_date: string
  status: ChargeStatus
  amounts?: ChargeAmounts
  paid_snapshot?: PaidSnapshot
}

export type ChargeFilters = {
  status?: ChargeStatus | ''
  payment_method?: PaymentMethod | ''
  contract?: string
  due_from?: string
  due_to?: string
}

export type GenerateChargePayload = {
  contract_id: number
  billing_period: string
  payment_method: PaymentMethod
  original_amount: string
  fixed_fee_amount?: string
}

type Resource<T> = {
  message?: string
  data: T
  errors?: ApiValidationErrors
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

export function fetchCharges(filters: ChargeFilters = {}): Promise<PaginatedResponse<Charge>> {
  return apiRequest<PaginatedResponse<Charge>>(`/api/charges${queryString({ ...filters, per_page: 20 })}`)
}

export async function generateCharge(payload: GenerateChargePayload): Promise<Resource<Charge>> {
  return apiRequest<Resource<Charge>>('/api/charges/generate', {
    method: 'POST',
    json: payload,
  })
}

export async function payCharge(chargeId: number): Promise<Resource<Charge>> {
  return apiRequest<Resource<Charge>>(`/api/charges/${chargeId}/pay`, {
    method: 'POST',
    json: {},
  })
}
