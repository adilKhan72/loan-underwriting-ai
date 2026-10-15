<script setup lang="ts">
import type { AuditEvent } from '../api/types'
import { dateTime, label } from '../format'

defineProps<{ events: AuditEvent[]; chainIntact: boolean }>()
</script>

<template>
  <p :class="chainIntact ? 'ok' : 'danger'" data-test="chain">
    {{ chainIntact ? 'Hash chain verified: no event has been altered, reordered or removed.' : 'Hash chain broken: this trail has been tampered with.' }}
  </p>
  <ol class="timeline">
    <li v-for="event in events" :key="event.id" :data-test="`event-${event.position}`">
      <div class="timeline__head">
        <strong>{{ label(event.event) }}</strong>
        <span class="muted">{{ event.actor.type }} · {{ dateTime(event.occurred_at) }}</span>
      </div>
      <code class="timeline__data">{{ JSON.stringify(event.data) }}</code>
      <span class="muted hash" :title="event.hash">#{{ event.position }} · {{ event.hash.slice(0, 12) }}…</span>
    </li>
  </ol>
</template>
