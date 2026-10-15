<script setup lang="ts">
import type { Decision } from '../api/types'
import { dateTime } from '../format'
import RuleResultsTable from './RuleResultsTable.vue'
import StatusBadge from './StatusBadge.vue'

defineProps<{ decision: Decision }>()
</script>

<template>
  <div class="decision">
    <div class="decision__outcome">
      <StatusBadge :value="decision.outcome" />
      <span class="muted">policy {{ decision.policy_version }} · {{ dateTime(decision.decided_at) }}</span>
    </div>

    <div v-if="decision.explanation.status === 'ready'" class="grid-2" data-test="explanation">
      <div class="card card--inset">
        <h4>For the underwriter</h4>
        <p>{{ decision.explanation.underwriter_summary }}</p>
      </div>
      <div class="card card--inset">
        <h4>For the applicant</h4>
        <p>{{ decision.explanation.applicant_message }}</p>
        <ul v-if="decision.explanation.principal_reasons.length">
          <li v-for="reason in decision.explanation.principal_reasons" :key="reason">{{ reason }}</li>
        </ul>
      </div>
    </div>
    <p v-else-if="decision.explanation.status === 'pending'" class="muted" data-test="explanation-pending">
      Writing the explanation… The decision itself is final and shown below.
    </p>
    <p v-else class="muted" data-test="explanation-unavailable">
      No AI explanation for this decision. Every rule result is shown below.
    </p>

    <RuleResultsTable :results="decision.rule_results" />
  </div>
</template>
