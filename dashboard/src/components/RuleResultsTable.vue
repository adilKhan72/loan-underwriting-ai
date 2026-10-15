<script setup lang="ts">
import { computed } from 'vue'
import type { RuleResult } from '../api/types'
import StatusBadge from './StatusBadge.vue'

const props = defineProps<{ results: RuleResult[] }>()

const order = { fail: 0, refer: 1, pass: 2, skip: 3 } as const
// Problems first: an underwriter reads the failing and referring rules before the passing ones.
const sorted = computed(() => [...props.results].sort((a, b) => order[a.outcome] - order[b.outcome]))
const counts = computed(() =>
  props.results.reduce<Record<string, number>>((acc, r) => ({ ...acc, [r.outcome]: (acc[r.outcome] ?? 0) + 1 }), {}),
)
</script>

<template>
  <p class="muted" data-test="counts">
    {{ counts.fail ?? 0 }} fail · {{ counts.refer ?? 0 }} refer · {{ counts.pass ?? 0 }} pass · {{ counts.skip ?? 0 }} skip
  </p>
  <table class="table">
    <thead>
      <tr><th>Rule</th><th>Result</th><th>Detail</th></tr>
    </thead>
    <tbody>
      <tr v-for="rule in sorted" :key="rule.code" :data-test="`rule-${rule.code}`">
        <td><code>{{ rule.code }}</code></td>
        <td><StatusBadge :value="rule.outcome" /></td>
        <td>{{ rule.message }}</td>
      </tr>
    </tbody>
  </table>
</template>
