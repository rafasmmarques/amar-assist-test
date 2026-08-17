<template>
  <main class="dashboard-shell">
    <aside class="sidebar" aria-label="Navegacao principal">
      <div>
        <p class="eyebrow">Amar Assist</p>
        <h1>Operacao</h1>
      </div>

      <nav class="nav-list">
        <button type="button" :class="{ active: view === 'clients' }" @click="$emit('change-view', 'clients')">
          Clientes
        </button>
        <button type="button" :class="{ active: view === 'charges' }" @click="$emit('change-view', 'charges')">
          Cobrancas
        </button>
      </nav>

      <div class="user-box">
        <strong>{{ user.name }}</strong>
        <span>{{ user.email }}</span>
        <button type="button" class="secondary-button" :disabled="loading" @click="$emit('logout')">
          Sair
        </button>
      </div>
    </aside>

    <section class="content-area">
      <slot />
    </section>
  </main>
</template>

<script setup lang="ts">
import type { AuthenticatedUser } from '../../api/auth'

defineProps<{
  loading: boolean
  user: AuthenticatedUser
  view: 'clients' | 'charges'
}>()

defineEmits<{
  'change-view': [view: 'clients' | 'charges']
  logout: []
}>()
</script>
