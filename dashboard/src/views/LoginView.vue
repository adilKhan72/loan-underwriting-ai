<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiClient, ApiError } from '../api/client'
import { apiUrl, setToken } from '../session'

const router = useRouter()
const token = ref('')
const error = ref('')

async function signIn() {
  error.value = ''
  try {
    await new ApiClient(apiUrl, token.value.trim()).listApplications()
    setToken(token.value.trim())
    router.push('/applications')
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 401 ? 'That token was rejected.' : `Could not reach the API at ${apiUrl}.`
  }
}
</script>

<template>
  <section class="card narrow">
    <h2>Sign in</h2>
    <p class="muted">Paste an API token. Run <code>make demo</code> to create a demo user and print one.</p>
    <form @submit.prevent="signIn">
      <label>API token <input v-model="token" type="password" autocomplete="off" required /></label>
      <p v-if="error" class="danger">{{ error }}</p>
      <button type="submit">Sign in</button>
    </form>
  </section>
</template>
