<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '../api/client'
import type { DocumentType } from '../api/types'
import { api } from '../session'

const router = useRouter()
const form = reactive({
  first_name: '', last_name: '', email: '', date_of_birth: '', ssn: '',
  amount: 1500, term_months: 12, stated_income: 4500, purpose: '',
})
const files = reactive<Record<DocumentType, File | null>>({ paystub: null, bank_statement: null })
const errors = ref<Record<string, string[]>>({})
const failure = ref('')
const busy = ref(false)

function pick(type: DocumentType, event: Event) {
  files[type] = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function submit() {
  busy.value = true
  errors.value = {}
  failure.value = ''
  try {
    const client = api()
    const application = await client.createApplication({
      applicant: {
        first_name: form.first_name, last_name: form.last_name, email: form.email,
        date_of_birth: form.date_of_birth, ssn: form.ssn,
      },
      amount_requested_cents: Math.round(form.amount * 100),
      term_months: form.term_months,
      purpose: form.purpose || undefined,
      stated_monthly_income_cents: Math.round(form.stated_income * 100),
    })
    for (const type of ['paystub', 'bank_statement'] as const) {
      const file = files[type]
      if (file) await client.uploadDocument(application.id, type, file)
    }
    await client.submit(application.id)
    router.push(`/applications/${application.id}`)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.fieldErrors
    failure.value = (e as Error).message
  } finally {
    busy.value = false
  }
}

const err = (field: string) => errors.value[field]?.[0]
</script>

<template>
  <section class="card">
    <h2>New application</h2>
    <form class="form-grid" @submit.prevent="submit">
      <fieldset>
        <legend>Applicant</legend>
        <label>First name <input v-model="form.first_name" required /></label>
        <label>Last name <input v-model="form.last_name" required /></label>
        <label>Email <input v-model="form.email" type="email" required /></label>
        <label>Date of birth <input v-model="form.date_of_birth" type="date" required /></label>
        <small class="danger">{{ err('applicant.date_of_birth') }}</small>
        <label>SSN <input v-model="form.ssn" placeholder="900-00-0000" required /></label>
        <small class="danger">{{ err('applicant.ssn') }}</small>
      </fieldset>
      <fieldset>
        <legend>Loan</legend>
        <label>Amount (USD) <input v-model.number="form.amount" type="number" min="100" max="50000" step="50" required /></label>
        <label>Term (months) <input v-model.number="form.term_months" type="number" min="3" max="60" required /></label>
        <label>Stated monthly income (USD) <input v-model.number="form.stated_income" type="number" min="0" required /></label>
        <label>Purpose <input v-model="form.purpose" /></label>
      </fieldset>
      <fieldset>
        <legend>Documents (PDF)</legend>
        <label>Paystub (required) <input type="file" accept="application/pdf" required @change="pick('paystub', $event)" /></label>
        <label>Bank statement <input type="file" accept="application/pdf" @change="pick('bank_statement', $event)" /></label>
      </fieldset>
      <p v-if="failure" class="danger">{{ failure }}</p>
      <button type="submit" :disabled="busy">{{ busy ? 'Submitting…' : 'Create and submit' }}</button>
    </form>
  </section>
</template>
