<template>
  <section class="feature-grid" aria-labelledby="charges-title">
    <div class="page-header">
      <div>
        <p class="eyebrow">Cobranças</p>
        <h2 id="charges-title">Geração e pagamento</h2>
        <span>Acompanhe vencimentos, valores e situação financeira.</span>
      </div>
      <button type="button" @click="loadCharges">Atualizar</button>
    </div>

    <form class="toolbar filter-panel" aria-label="Filtros de cobranças" @submit.prevent="loadCharges">
      <label>
        Status
        <select v-model="filters.status">
          <option value="">Todos</option>
          <option value="open">Aberta</option>
          <option value="paid">Paga</option>
        </select>
      </label>
      <label>
        Método
        <select v-model="filters.payment_method">
          <option value="">Todos</option>
          <option value="boleto">Boleto</option>
          <option value="pix">Pix</option>
          <option value="card">Cartão</option>
        </select>
      </label>
      <label>
        Contrato
        <input v-model="filters.contract" inputmode="numeric" placeholder="ID do contrato" />
      </label>
      <button type="submit" :disabled="loading">Filtrar</button>
    </form>

    <section class="panel">
      <div class="section-header">
        <div>
          <h3>Nova cobrança</h3>
          <span>Gere uma cobrança individual por contrato e competência.</span>
        </div>
      </div>
      <form class="form-grid" @submit.prevent="submitCharge">
        <label>
          Contrato
          <input v-model.number="form.contract_id" type="number" min="1" required />
        </label>
        <label>
          Competência
          <input v-model="form.billing_period" type="month" required />
        </label>
        <label>
          Método
          <select v-model="form.payment_method">
            <option value="boleto">Boleto</option>
            <option value="pix">Pix</option>
            <option value="card">Cartão</option>
          </select>
        </label>
        <label>
          Valor original
          <input
            :value="form.original_amount"
            inputmode="decimal"
            placeholder="R$ 100,00"
            required
            @input="updateMoney('original_amount', $event)"
          />
        </label>
        <label>
          Multa fixa
          <input
            :value="form.fixed_fee_amount"
            inputmode="decimal"
            placeholder="R$ 0,00"
            @input="updateMoney('fixed_fee_amount', $event)"
          />
        </label>
        <button type="submit" :disabled="saving">{{ saving ? 'Gerando...' : 'Gerar cobrança' }}</button>
      </form>
      <p v-if="formError" class="feedback feedback-error" role="alert">{{ formError }}</p>
      <p v-if="success" class="feedback feedback-success" role="status">{{ success }}</p>
    </section>

    <section class="panel">
      <div class="section-header">
        <h3>Cobranças</h3>
        <span>{{ totalLabel }}</span>
      </div>

      <p v-if="loading" class="state-message">Carregando cobranças...</p>
      <p v-else-if="loadError" class="feedback feedback-error" role="alert">{{ loadError }}</p>
      <p v-if="actionError" class="feedback feedback-error" role="alert">{{ actionError }}</p>
      <p v-if="!loading && !loadError && charges.length === 0" class="state-message">Nenhuma cobrança encontrada.</p>

      <div v-if="!loading && !loadError && charges.length > 0" class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Situação</th>
              <th>Contrato</th>
              <th>Método</th>
              <th>Vencimento</th>
              <th>Total</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="charge in charges" :key="charge.id" :class="{ 'is-overdue': isOverdue(charge) }">
              <td>
                <span class="status-badge" :class="statusClass(charge)">
                  <span class="status-icon" aria-hidden="true">{{ isOverdue(charge) ? '!' : '' }}</span>
                  {{ statusLabel(charge) }}
                </span>
                <small v-if="isOverdue(charge)" class="overdue-note">
                  Atrasada há {{ charge.amounts?.days_late }} dia{{ charge.amounts?.days_late === 1 ? '' : 's' }}
                </small>
              </td>
              <td>
                <strong>#{{ charge.contract_id }}</strong>
                <small>{{ formatBillingPeriod(charge.billing_period) }}</small>
              </td>
              <td>
                <span class="method-badge">{{ methodLabel(charge.payment_method) }}</span>
              </td>
              <td>
                <strong>{{ formatDate(charge.due_date) }}</strong>
                <small>{{ isOverdue(charge) ? 'Vencida' : 'Em acompanhamento' }}</small>
              </td>
              <td>
                <strong class="money-value">{{ formatMoney(totalAmount(charge)) }}</strong>
                <small>
                  Original {{ formatMoney(charge.original_amount) }} | Multa {{ formatMoney(charge.fixed_fee_amount) }}
                </small>
                <small v-if="charge.amounts">Juros {{ formatMoney(charge.amounts.late_interest_amount) }}</small>
              </td>
              <td>
                <button
                  v-if="charge.status === 'open'"
                  type="button"
                  class="secondary-button"
                  :disabled="saving"
                  @click="submitPayment(charge)"
                >
                  Pagar
                </button>
                <span v-else class="status-badge status-badge--neutral">Snapshot pago</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="charges.length > 0" class="pagination-note">Mostrando {{ charges.length }} de {{ total }} cobranças.</p>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  fetchCharges,
  generateCharge,
  payCharge,
  type Charge,
  type ChargeFilters,
  type PaymentMethod,
} from '../../api/charges'
import { extractErrorMessage } from '../../api/http'

const charges = ref<Charge[]>([])
const total = ref(0)
const loading = ref(false)
const saving = ref(false)
const loadError = ref<string | null>(null)
const formError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const success = ref<string | null>(null)
const filters = reactive<ChargeFilters>({
  status: '',
  payment_method: '',
  contract: '',
})
const form = reactive({
  contract_id: 1,
  billing_period: new Date().toISOString().slice(0, 7),
  payment_method: 'boleto' as PaymentMethod,
  original_amount: '',
  fixed_fee_amount: '',
})

const totalLabel = computed(() => `${total.value} cobrança${total.value === 1 ? '' : 's'}`)

onMounted(() => {
  void loadCharges()
})

async function loadCharges() {
  loading.value = true
  loadError.value = null

  try {
    const response = await fetchCharges(filters)
    charges.value = response.data
    total.value = response.meta.total
  } catch (requestError) {
    loadError.value = extractErrorMessage(requestError, 'Não foi possível carregar cobranças.')
  } finally {
    loading.value = false
  }
}

async function submitCharge() {
  saving.value = true
  formError.value = null
  actionError.value = null
  success.value = null

  try {
    const response = await generateCharge({
      contract_id: Number(form.contract_id),
      billing_period: form.billing_period,
      payment_method: form.payment_method,
      original_amount: normalizeMoney(form.original_amount),
      fixed_fee_amount: form.fixed_fee_amount ? normalizeMoney(form.fixed_fee_amount) : undefined,
    })
    success.value = response.data.status === 'open' ? 'Cobrança disponível.' : 'Cobrança registrada.'
    form.original_amount = ''
    form.fixed_fee_amount = ''
    await loadCharges()
  } catch (requestError) {
    formError.value = extractErrorMessage(requestError, 'Não foi possível gerar a cobrança.')
  } finally {
    saving.value = false
  }
}

async function submitPayment(charge: Charge) {
  saving.value = true
  formError.value = null
  actionError.value = null
  success.value = null

  try {
    await payCharge(charge.id)
    success.value = 'Cobrança paga.'
    await loadCharges()
  } catch (requestError) {
    actionError.value = extractErrorMessage(requestError, 'Não foi possível pagar a cobrança.')
  } finally {
    saving.value = false
  }
}

function methodLabel(method: PaymentMethod) {
  const labels: Record<PaymentMethod, string> = {
    boleto: 'Boleto',
    pix: 'Pix',
    card: 'Cartão',
  }

  return labels[method]
}

function totalAmount(charge: Charge) {
  return charge.paid_snapshot?.paid_total_amount ?? charge.amounts?.total_amount ?? charge.original_amount
}

function isOverdue(charge: Charge) {
  return charge.status === 'open' && (charge.amounts?.days_late ?? 0) > 0
}

function statusLabel(charge: Charge) {
  if (isOverdue(charge)) {
    return 'Vencida'
  }

  return charge.status === 'open' ? 'Aberta' : 'Paga'
}

function statusClass(charge: Charge) {
  if (isOverdue(charge)) {
    return 'status-badge--overdue'
  }

  return charge.status === 'open' ? 'status-badge--open' : 'status-badge--paid'
}

function formatMoney(value: string) {
  const [rawReais, rawCentavos = ''] = value.replace(',', '.').split('.')
  const reais = rawReais.replace(/\D/g, '') || '0'
  const centavos = rawCentavos.replace(/\D/g, '').padEnd(2, '0').slice(0, 2)
  const reaisFormatados = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  return `R$ ${reaisFormatados},${centavos}`
}

function formatBillingPeriod(value: string) {
  const [year, month] = value.split('-')

  return year && month ? `${month}/${year}` : value
}

function formatDate(value: string) {
  const [year, month, day] = value.split('-')

  return year && month && day ? `${day}/${month}/${year}` : value
}

function updateMoney(field: 'original_amount' | 'fixed_fee_amount', event: Event) {
  const input = event.target as HTMLInputElement
  form[field] = maskMoney(input.value)
  input.value = form[field]
}

function maskMoney(value: string) {
  const digits = value.replace(/\D/g, '')

  if (!digits) {
    return ''
  }

  const padded = digits.padStart(3, '0')
  const reais = padded.slice(0, -2).replace(/^0+(?=\d)/, '')
  const centavos = padded.slice(-2)
  const reaisFormatados = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  return `R$ ${reaisFormatados},${centavos}`
}

function normalizeMoney(value: string) {
  const digits = value.replace(/\D/g, '').padStart(3, '0')
  const reais = digits.slice(0, -2).replace(/^0+(?=\d)/, '') || '0'
  const centavos = digits.slice(-2)

  return `${reais}.${centavos}`
}
</script>
