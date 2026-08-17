<template>
  <main class="login-page">
    <section class="login-panel" aria-labelledby="login-title">
      <p class="eyebrow">Amar Assist</p>
      <h1 id="login-title">Acesso</h1>

      <form class="stack" @submit.prevent="submitLogin">
        <label>
          E-mail
          <input v-model="email" type="email" autocomplete="email" required />
        </label>

        <label>
          Senha
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>

        <p v-if="error" class="feedback feedback-error" role="alert">{{ error }}</p>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </section>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  error: string | null
  loading: boolean
}>()

const emit = defineEmits<{
  login: [email: string, password: string]
}>()

const email = ref('')
const password = ref('')

function submitLogin() {
  emit('login', email.value, password.value)
  password.value = ''
}
</script>
