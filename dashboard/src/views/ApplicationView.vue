<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { AuditEvent, LoanApplication } from '../api/types'
import AuditTimeline from '../components/AuditTimeline.vue'
import DecisionPanel from '../components/DecisionPanel.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { dateTime, label, money } from '../format'
import { api } from '../session'

const props = defineProps<{ id: string }>()

const application = ref<LoanApplication | null>(null)
const audit = ref<{ events: AuditEvent[]; chainIntact: boolean } | null>(null)
const error = ref('')
let timer: ReturnType<typeof setTimeout> | undefined

// Processing happens on queue workers; poll until there's nothing left to wait for.
const stillWorking = computed(() => {
  const a = application.value
  return !!a && (['submitted', 'processing'].includes(a.status) || a.decision?.explanation.status === 'pending')
})

async function load() {
  try {
    const client = api()
    ;[application.value, audit.value] = await Promise.all([client.getApplication(props.id), client.getAudit(props.id)])
    error.value = ''
  } catch (e) {
    error.value = (e as Error).message
  }
  if (stillWorking.value) timer = setTimeout(load, 2500)
}

onMounted(load)
onUnmounted(() => clearTimeout(timer))
</script>

<template>
  <p v-if="error" class="danger">{{ error }}</p>
  <section v-if="application" class="stack">
    <div class="toolbar">
      <h2>{{ application.applicant?.first_name }} {{ application.applicant?.last_name }}</h2>
      <StatusBadge :value="application.status" />
      <span v-if="stillWorking" class="muted">updating…</span>
    </div>

    <div class="card grid-4">
      <div><span class="muted">Requested</span><strong>{{ money(application.amount_requested_cents) }}</strong></div>
      <div><span class="muted">Term</span><strong>{{ application.term_months }} months</strong></div>
      <div><span class="muted">Stated income</span><strong>{{ money(application.stated_monthly_income_cents) }}/mo</strong></div>
      <div><span class="muted">SSN</span><strong>{{ application.applicant?.ssn_masked }}</strong></div>
    </div>

    <div class="card">
      <h3>Decision</h3>
      <DecisionPanel v-if="application.decision" :decision="application.decision" />
      <p v-else class="muted">No decision yet. Documents are being processed.</p>
    </div>

    <div class="card">
      <h3>Documents</h3>
      <div v-for="doc in application.documents" :key="doc.id" class="doc">
        <div class="toolbar">
          <strong>{{ label(doc.type) }}</strong>
          <span class="muted">{{ doc.original_name }}</span>
          <StatusBadge :value="doc.status" />
        </div>
        <template v-if="doc.extraction">
          <p v-if="Object.keys(doc.extraction.redactions).length" class="muted">
            Redacted before AI: {{ Object.entries(doc.extraction.redactions).map(([k, n]) => `${n} × ${label(k)}`).join(', ') }}
            · provider {{ doc.extraction.provider ?? '—' }}
          </p>
          <ul v-if="doc.extraction.errors.length" class="danger">
            <li v-for="e in doc.extraction.errors" :key="e">{{ e }}</li>
          </ul>
          <pre v-if="doc.extraction.data" class="json">{{ JSON.stringify(doc.extraction.data, null, 2) }}</pre>
        </template>
      </div>
    </div>

    <div class="card" v-if="audit">
      <h3>Audit trail</h3>
      <AuditTimeline :events="audit.events" :chain-intact="audit.chainIntact" />
    </div>
    <p class="muted">Submitted {{ dateTime(application.submitted_at) }}</p>
  </section>
</template>
