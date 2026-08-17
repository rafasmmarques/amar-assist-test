<template>
  <section class="feature-grid" aria-labelledby="clients-title">
    <div class="page-header">
      <div>
        <p class="eyebrow">Clientes</p>
        <h2 id="clients-title">Cadastro e consulta</h2>
      </div>
      <button type="button" @click="loadClients">Atualizar</button>
    </div>

    <form class="toolbar" @submit.prevent="loadClients">
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
      <h3>Novo cliente</h3>
      <form class="form-grid" @submit.prevent="submitClient">
        <label>
          Nome
          <input v-model="form.name" required />
        </label>
        <label>
          Tipo
          <select v-model="form.document_type">
            <option value="cpf">CPF</option>
            <option value="cnpj">CNPJ</option>
          </select>
        </label>
        <label>
          Documento
          <input v-model="form.document" required />
        </label>
        <label>
          Endereco
          <input v-model="form.address" />
        </label>
        <label>
          Contato
          <input v-model="form.contact" />
        </label>
        <button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar cliente' }}</button>
      </form>
      <p v-if="success" class="feedback feedback-success">{{ success }}</p>
    </section>

    <section class="panel">
      <div class="section-header">
        <h3>Clientes</h3>
        <span>{{ totalLabel }}</span>
      </div>

      <p v-if="loading" class="state-message">Carregando clientes...</p>
      <p v-else-if="error" class="feedback feedback-error" role="alert">{{ error }}</p>
      <p v-else-if="clients.length === 0" class="state-message">Nenhum cliente encontrado.</p>

      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Documento</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in clients" :key="client.id">
              <td>
                <strong>{{ client.name }}</strong>
                <small>{{ client.contact || 'Sem contato' }}</small>
              </td>
              <td>{{ client.document_type.toUpperCase() }} {{ client.document }}</td>
              <td>{{ statusLabel(client.status) }}</td>
              <td>
                <button
                  type="button"
                  class="secondary-button"
                  :disabled="saving"
                  @click="toggleClient(client)"
                >
                  {{ client.status === 'active' ? 'Desativar' : 'Ativar' }}
                </button>
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
const error = ref<string | null>(null)
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
  error.value = null

  try {
    const response = await fetchClients(filters)
    clients.value = response.data
    total.value = response.meta.total
  } catch (requestError) {
    error.value = extractErrorMessage(requestError, 'Nao foi possivel carregar clientes.')
  } finally {
    loading.value = false
  }
}

async function submitClient() {
  saving.value = true
  error.value = null
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
    error.value = extractErrorMessage(requestError, 'Nao foi possivel salvar o cliente.')
  } finally {
    saving.value = false
  }
}

async function toggleClient(client: Client) {
  saving.value = true
  error.value = null
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
    error.value = extractErrorMessage(requestError, 'Nao foi possivel alterar o status do cliente.')
  } finally {
    saving.value = false
  }
}

function statusLabel(status: Client['status']) {
  return status === 'active' ? 'Ativo' : 'Inativo'
}
</script>
