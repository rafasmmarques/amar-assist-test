<template>
  <section class="feature-grid" aria-labelledby="clients-title">
    <div class="page-header">
      <div>
        <p class="eyebrow">Clientes</p>
        <h2 id="clients-title">Cadastro e consulta</h2>
        <span>Gerencie dados cadastrais e status de atendimento.</span>
      </div>
      <button type="button" @click="loadClients">Atualizar</button>
    </div>

    <form class="toolbar filter-panel" aria-label="Filtros de clientes" @submit.prevent="loadClients">
      <label>
        Nome
        <input v-model="filters.name" placeholder="Buscar por nome" />
      </label>
      <label>
        Documento
        <input v-model="filters.document" placeholder="CPF ou CNPJ" />
      </label>
      <label>
        Status
        <select v-model="filters.status">
          <option value="">Todos</option>
          <option value="active">Ativo</option>
          <option value="inactive">Inativo</option>
        </select>
      </label>
      <button type="submit" :disabled="loading">Filtrar</button>
    </form>

    <section class="panel">
      <div class="section-header">
        <div>
          <h3>Novo cliente</h3>
          <span>Cadastre uma pessoa física ou jurídica.</span>
        </div>
      </div>
      <form class="form-grid" @submit.prevent="submitClient">
        <label>
          Nome
          <input v-model="form.name" required />
        </label>
        <label>
          Tipo
          <select v-model="form.document_type" @change="form.document = maskDocument(form.document, form.document_type)">
            <option value="cpf">CPF</option>
            <option value="cnpj">CNPJ</option>
          </select>
        </label>
        <label>
          Documento
          <input
            :value="form.document"
            :placeholder="form.document_type === 'cpf' ? '000.000.000-00' : '00.000.000/0000-00'"
            required
            inputmode="numeric"
            @input="updateDocument"
          />
        </label>
        <label>
          Endereço
          <input v-model="form.address" />
        </label>
        <label>
          Contato
          <input
            :value="form.contact"
            inputmode="tel"
            placeholder="(11) 99999-9999"
            @input="updateContact"
          />
        </label>
        <button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar cliente' }}</button>
      </form>
      <p v-if="formError" class="feedback feedback-error" role="alert">{{ formError }}</p>
      <p v-if="success" class="feedback feedback-success" role="status">{{ success }}</p>
    </section>

    <section class="panel">
      <div class="section-header">
        <h3>Clientes</h3>
        <span>{{ totalLabel }}</span>
      </div>

      <p v-if="loading" class="state-message">Carregando clientes...</p>
      <p v-else-if="loadError" class="feedback feedback-error" role="alert">{{ loadError }}</p>
      <p v-if="actionError" class="feedback feedback-error" role="alert">{{ actionError }}</p>
      <p v-if="!loading && !loadError && clients.length === 0" class="state-message">Nenhum cliente encontrado.</p>

      <div v-if="!loading && !loadError && clients.length > 0" class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Documento</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in clients" :key="client.id">
              <td>
                <strong>{{ client.name }}</strong>
                <small>{{ client.contact || 'Sem contato' }}</small>
              </td>
              <td>{{ client.document_type.toUpperCase() }} {{ client.document }}</td>
              <td>
                <span class="status-badge" :class="`status-badge--${client.status}`">
                  {{ statusLabel(client.status) }}
                </span>
              </td>
              <td>
                <button
                  type="button"
                  class="secondary-button"
                  :disabled="saving"
                  @click="toggleClient(client)"
                >
                  {{ client.status === 'active' ? 'Desativar' : 'Ativar' }}
                </button>
                <small v-if="client.status === 'active'" class="action-hint">
                  Se houver contrato associado, a API bloqueia a desativação.
                </small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="clients.length > 0" class="pagination-note">Mostrando {{ clients.length }} de {{ total }} clientes.</p>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  activateClient,
  createClient,
  deactivateClient,
  fetchClients,
  type Client,
  type ClientFilters,
  type DocumentType,
} from '../../api/clients'
import { extractErrorMessage } from '../../api/http'

const clients = ref<Client[]>([])
const total = ref(0)
const loading = ref(false)
const saving = ref(false)
const loadError = ref<string | null>(null)
const formError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const success = ref<string | null>(null)
const filters = reactive<ClientFilters>({
  name: '',
  status: '',
  document: '',
})
const form = reactive({
  name: '',
  document_type: 'cpf' as DocumentType,
  document: '',
  address: '',
  contact: '',
})

const totalLabel = computed(() => `${total.value} cliente${total.value === 1 ? '' : 's'}`)

onMounted(() => {
  void loadClients()
})

async function loadClients() {
  loading.value = true
  loadError.value = null

  try {
    const response = await fetchClients(filters)
    clients.value = response.data
    total.value = response.meta.total
  } catch (requestError) {
    loadError.value = extractErrorMessage(requestError, 'Não foi possível carregar clientes.')
  } finally {
    loading.value = false
  }
}

async function submitClient() {
  saving.value = true
  formError.value = null
  actionError.value = null
  success.value = null

  try {
    await createClient({
      name: form.name,
      document_type: form.document_type,
      document: form.document,
      address: form.address || undefined,
      contact: form.contact || undefined,
    })
    Object.assign(form, { name: '', document_type: 'cpf', document: '', address: '', contact: '' })
    success.value = 'Cliente salvo.'
    await loadClients()
  } catch (requestError) {
    formError.value = extractErrorMessage(requestError, 'Não foi possível salvar o cliente.')
  } finally {
    saving.value = false
  }
}

async function toggleClient(client: Client) {
  saving.value = true
  formError.value = null
  actionError.value = null
  success.value = null

  try {
    if (client.status === 'active') {
      await deactivateClient(client.id)
      success.value = 'Cliente desativado.'
    } else {
      await activateClient(client.id)
      success.value = 'Cliente ativado.'
    }

    await loadClients()
  } catch (requestError) {
    actionError.value = extractErrorMessage(requestError, 'Não foi possível alterar o status do cliente.')
  } finally {
    saving.value = false
  }
}

function statusLabel(status: Client['status']) {
  return status === 'active' ? 'Ativo' : 'Inativo'
}

function updateDocument(event: Event) {
  const input = event.target as HTMLInputElement
  form.document = maskDocument(input.value, form.document_type)
  input.value = form.document
}

function updateContact(event: Event) {
  const input = event.target as HTMLInputElement
  form.contact = maskPhone(input.value)
  input.value = form.contact
}

function onlyDigits(value: string) {
  return value.replace(/\D/g, '')
}

function maskDocument(value: string, type: DocumentType) {
  const digits = onlyDigits(value).slice(0, type === 'cpf' ? 11 : 14)

  if (type === 'cpf') {
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4')
  }

  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4')
    .replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5')
}

function maskPhone(value: string) {
  const digits = onlyDigits(value).slice(0, 11)

  if (digits.length <= 10) {
    return digits
      .replace(/^(\d{2})(\d)/, '($1) $2')
      .replace(/^(\(\d{2}\) \d{4})(\d)/, '$1-$2')
  }

  return digits
    .replace(/^(\d{2})(\d)/, '($1) $2')
    .replace(/^(\(\d{2}\) \d{5})(\d)/, '$1-$2')
}
</script>
