<template>
  <LoginView v-if="!user" :error="error" :loading="loading" @login="submitLogin" />

  <AppLayout v-else :loading="loading" :user="user" :view="view" @change-view="setView" @logout="logout">
    <ClientsView v-if="view === 'clients'" />
    <ChargesView v-else />
  </AppLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuth } from './composables/useAuth'
import LoginView from './features/auth/LoginView.vue'
import ChargesView from './features/charges/ChargesView.vue'
import ClientsView from './features/clients/ClientsView.vue'
import AppLayout from './features/layout/AppLayout.vue'

const { error, loadUser, loading, login, logout, user } = useAuth()
const view = ref<'clients' | 'charges'>(window.location.hash === '#charges' ? 'charges' : 'clients')

onMounted(() => {
  void loadUser()
})

async function submitLogin(email: string, password: string) {
  await login(email, password)
}

function setView(nextView: 'clients' | 'charges') {
  view.value = nextView
  window.location.hash = nextView
}
</script>
