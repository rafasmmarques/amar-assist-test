<template>
  <main class="dashboard-shell">
    <aside class="sidebar" aria-label="Navegação principal">
      <div class="brand-block">
        <span class="brand-mark" aria-hidden="true">A</span>
        <div>
          <p class="eyebrow">Amar Assist</p>
          <h1>Operação</h1>
        </div>
      </div>

      <nav class="nav-list">
        <button
          type="button"
          :aria-current="view === 'clients' ? 'page' : undefined"
          :class="{ active: view === 'clients' }"
          @click="$emit('change-view', 'clients')"
        >
          <span class="nav-dot" aria-hidden="true"></span>
          Clientes
        </button>
        <button
          type="button"
          :aria-current="view === 'charges' ? 'page' : undefined"
          :class="{ active: view === 'charges' }"
          @click="$emit('change-view', 'charges')"
        >
          <span class="nav-dot" aria-hidden="true"></span>
          Cobranças
        </button>
      </nav>

      <div class="user-box">
        <span class="user-label">Usuário conectado</span>
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
