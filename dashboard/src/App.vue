<script setup lang="ts">
import { useRouter } from 'vue-router'
import { clearToken, getToken } from './session'

const router = useRouter()

function signOut() {
  clearToken()
  router.push('/login')
}
</script>

<template>
  <header class="topbar">
    <RouterLink to="/applications" class="brand">Underwriting</RouterLink>
    <span class="muted">rules decide · AI extracts & explains</span>
    <button v-if="getToken()" class="link" @click="signOut">Sign out</button>
  </header>
  <main class="container">
    <RouterView :key="$route.fullPath" />
  </main>
</template>
