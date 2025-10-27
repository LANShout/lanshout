<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{
  (e: 'submit', body: string): void
}>()

const body = ref('')
const posting = ref(false)
const error = ref<string | null>(null)

async function onSubmit() {
  if (!body.value.trim()) return
  posting.value = true
  error.value = null
  try {
    await emit('submit', body.value)
    body.value = ''
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to post'
  } finally {
    posting.value = false
  }
}
</script>

<template>
  <form @submit.prevent="onSubmit" class="flex items-start gap-2">
    <textarea v-model="body" rows="2" placeholder="Type a message…"
      class="min-h-[44px] flex-1 rounded-md border border-sidebar-border/70 p-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
    />
    <button type="submit" :disabled="posting || !body.trim()"
      class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">
      {{ posting ? 'Sending…' : 'Send' }}
    </button>
  </form>
  <p v-if="error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
</template>
