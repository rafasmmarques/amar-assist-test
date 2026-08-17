<template>
  <main class="app-shell">
    <section class="auth-panel">
      <p class="eyebrow">Amar Assist</p>
      <h1>Acesso</h1>

      <form v-if="!user" class="login-form" @submit.prevent="submitLogin">
        <label>
          E-mail
          <input v-model="email" type="email" autocomplete="email" required />
        </label>

        <label>
          Senha
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>

        <p v-if="error" class="error-message">{{ error }}</p>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <div v-else class="session-summary">
        <p>{{ user.name }}</p>
        <span>{{ user.email }}</span>
        <button type="button" :disabled="loading" @click="logout">
          Sair
        </button>
      </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuth } from './composables/useAuth'

const email = ref('')
const password = ref('')
const { error, loadUser, loading, login, logout, user } = useAuth()

onMounted(() => {
  void loadUser()
})

async function submitLogin() {
  await login(email.value, password.value)
  password.value = ''
}
</script>
