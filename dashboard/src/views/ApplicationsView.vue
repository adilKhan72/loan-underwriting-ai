<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import type { ApplicationStatus, LoanApplication } from '../api/types'
import StatusBadge from '../components/StatusBadge.vue'
import { dateTime, money } from '../format'
import { api } from '../session'

const status = ref<ApplicationStatus | ''>('')
const applications = ref<LoanApplication[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    applications.value = await api().listApplications(status.value || undefined)
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(status, load)
</script>

<template>
  <section>
    <div class="toolbar">
      <h2>Applications</h2>
      <select v-model="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
        <option value="processing">Processing</option>
        <option value="decided">Decided</option>
      </select>
      <RouterLink class="button" to="/applications/new">New application</RouterLink>
    </div>

    <p v-if="error" class="danger">{{ error }}</p>
    <p v-else-if="loading" class="muted">Loading…</p>
    <p v-else-if="!applications.length" class="muted">No applications yet.</p>
    <table v-else class="table">
      <thead>
        <tr><th>Applicant</th><th>Amount</th><th>Term</th><th>Status</th><th>Created</th></tr>
      </thead>
      <tbody>
        <tr v-for="a in applications" :key="a.id">
          <td><RouterLink :to="`/applications/${a.id}`">{{ a.applicant?.first_name }} {{ a.applicant?.last_name }}</RouterLink></td>
          <td>{{ money(a.amount_requested_cents) }}</td>
          <td>{{ a.term_months }} mo</td>
          <td><StatusBadge :value="a.status" /></td>
          <td>{{ dateTime(a.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
