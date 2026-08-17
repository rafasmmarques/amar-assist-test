<template>
  <section class="feature-grid" aria-labelledby="charges-title">
    <div class="page-header">
      <div>
        <p class="eyebrow">Cobrancas</p>
        <h2 id="charges-title">Geracao e pagamento</h2>
      </div>
      <button type="button" @click="loadCharges">Atualizar</button>
    </div>

    <form class="toolbar" @submit.prevent="loadCharges">
      <label>
        Status
        <select v-model="filters.status">
          <option value="">Todos</option>
          <option value="open">Aberta</option>
          <option value="paid">Paga</option>
        </select>
      </label>
      <label>
        Metodo
        <select v-model="filters.payment_method">
          <option value="">Todos</option>
          <option value="boleto">Boleto</option>
          <option value="pix">Pix</option>
          <option value="card">Cartao</option>
        </select>
      </label>
      <label>
        Contrato
        <input v-model="filters.contract" inputmode="numeric" placeholder="ID do contrato" />
      </label>
      <button type="submit" :disabled="loading">Filtrar</button>
    </form>

    <section class="panel">
      <h3>Nova cobranca</h3>
      <form class="form-grid" @submit.prevent="submitCharge">
        <label>
          Contrato
          <input v-model.number="form.contract_id" type="number" min="1" required />
        </label>
        <label>
          Competencia
          <input v-model="form.billing_period" type="month" required />
        </label>
        <label>
          Metodo
          <select v-model="form.payment_method">
            <option value="boleto">Boleto</option>
            <option value="pix">Pix</option>
            <option value="card">Cartao</option>
          </select>
        </label>
        <label>
          Valor original
          <input v-model="form.original_amount" inputmode="decimal" placeholder="100.00" required />
        </label>
        <label>
          Multa fixa
          <input v-model="form.fixed_fee_amount" inputmode="decimal" placeholder="0.00" />
        </label>
        <button type="submit" :disabled="saving">{{ saving ? 'Gerando...' : 'Gerar cobranca' }}</button>
      </form>
      <p v-if="success" class="feedback feedback-success">{{ success }}</p>
    </section>

    <section class="panel">
      <div class="section-header">
        <h3>Cobrancas</h3>
        <span>{{ totalLabel }}</span>
      </div>

      <p v-if="loading" class="state-message">Carregando cobrancas...</p>
      <p v-else-if="error" class="feedback feedback-error" role="alert">{{ error }}</p>
      <p v-else-if="charges.length === 0" class="state-message">Nenhuma cobranca encontrada.</p>

      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Contrato</th>
              <th>Metodo</th>
              <th>Vencimento</th>
              <th>Status</th>
              <th>Total</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="charge in charges" :key="charge.id">
              <td>
                <strong>#{{ charge.contract_id }}</strong>
                <small>{{ charge.billing_period }}</small>
              </td>
              <td>{{ methodLabel(charge.payment_method) }}</td>
              <td>{{ charge.due_date }}</td>
              <td>{{ charge.status === 'open' ? 'Aberta' : 'Paga' }}</td>
              <td>{{ totalAmount(charge) }}</td>
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
                <span v-else>Snapshot</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
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
const error = ref<string | null>(null)
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

const totalLabel = computed(() => `${total.value} cobranca${total.value === 1 ? '' : 's'}`)

onMounted(() => {
  void loadCharges()
})

async function loadCharges() {
  loading.value = true
  error.value = null

  try {
    const response = await fetchCharges(filters)
    charges.value = response.data
    total.value = response.meta.total
  } catch (requestError) {
    error.value = extractErrorMessage(requestError, 'Nao foi possivel carregar cobrancas.')
  } finally {
    loading.value = false
  }
}

async function submitCharge() {
  saving.value = true
  error.value = null
  success.value = null

  try {
    const response = await generateCharge({
      contract_id: Number(form.contract_id),
      billing_period: form.billing_period,
      payment_method: form.payment_method,
      original_amount: form.original_amount,
      fixed_fee_amount: form.fixed_fee_amount || undefined,
    })
    success.value = response.message ?? 'Cobranca registrada.'
    form.original_amount = ''
    form.fixed_fee_amount = ''
    await loadCharges()
  } catch (requestError) {
    error.value = extractErrorMessage(requestError, 'Nao foi possivel gerar a cobranca.')
  } finally {
    saving.value = false
  }
}

async function submitPayment(charge: Charge) {
  saving.value = true
  error.value = null
  success.value = null

  try {
    const response = await payCharge(charge.id)
    success.value = response.message ?? 'Cobranca paga.'
    await loadCharges()
  } catch (requestError) {
    error.value = extractErrorMessage(requestError, 'Nao foi possivel pagar a cobranca.')
  } finally {
    saving.value = false
  }
}

function methodLabel(method: PaymentMethod) {
  const labels: Record<PaymentMethod, string> = {
    boleto: 'Boleto',
    pix: 'Pix',
    card: 'Cartao',
  }

  return labels[method]
}

function totalAmount(charge: Charge) {
  return charge.paid_snapshot?.paid_total_amount ?? charge.amounts?.total_amount ?? charge.original_amount
}
</script>
