<template>
  <main class="login-page">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="login-brand">
        <span class="brand-mark" aria-hidden="true">A</span>
        <div>
          <p class="eyebrow">Amar Assist</p>
          <h1 id="login-title">Acesso</h1>
        </div>
      </div>
      <p class="login-copy">Entre para acompanhar clientes, contratos e cobranças com segurança.</p>

      <form class="login-form" @submit.prevent="submitLogin">
        <label>
          E-mail
          <input v-model="email" type="email" autocomplete="email" required autofocus />
        </label>

        <div class="password-field">
          <label for="password">Senha</label>
          <div class="password-control">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              required
            />
            <button type="button" class="ghost-button" @click="showPassword = !showPassword">
              {{ showPassword ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>

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
const showPassword = ref(false)

function submitLogin() {
  emit('login', email.value, password.value)
  password.value = ''
}
</script>
