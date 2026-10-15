<script setup lang="ts">
import { computed } from 'vue'
import { label } from '../format'

const props = defineProps<{ value: string }>()

// One colour vocabulary for every status in the app: good / attention / bad / neutral.
const tone = computed(() => {
  if (['approve', 'pass', 'extracted', 'ok', 'decided', 'ready'].includes(props.value)) return 'good'
  if (['refer', 'needs_review', 'processing', 'submitted', 'pending'].includes(props.value)) return 'warn'
  if (['decline', 'fail', 'failed', 'unavailable'].includes(props.value)) return 'bad'
  return 'neutral'
})
</script>

<template>
  <span class="badge" :class="`badge--${tone}`">{{ label(value) }}</span>
</template>
