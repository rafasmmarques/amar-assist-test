import { readonly, ref } from 'vue'
import {
  fetchAuthenticatedUser,
  login as loginRequest,
  logout as logoutRequest,
  type AuthenticatedUser,
} from '../api/auth'

const user = ref<AuthenticatedUser | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

export function useAuth() {
  async function loadUser(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      user.value = await fetchAuthenticatedUser()
    } catch {
      user.value = null
    } finally {
      loading.value = false
    }
  }

  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      user.value = await loginRequest(email, password)
    } catch {
      error.value = 'Nao foi possivel entrar com essas credenciais.'
      user.value = null
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      await logoutRequest()
      user.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    error: readonly(error),
    loading: readonly(loading),
    loadUser,
    login,
    logout,
    user: readonly(user),
  }
}
