<script setup lang="ts">
import { computed } from 'vue'

type User = { id: number; name: string }

type Message = {
  id: number
  body: string
  created_at: string
  user: User
}

const props = defineProps<{
  messages: Message[]
  loading?: boolean
  error?: string | null
}>()

const ordered = computed(() => props.messages)
</script>

<template>
  <div class="flex min-h-40 flex-col gap-2 rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border">
    <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>
    <div v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</div>
    <div v-else class="flex flex-col gap-2">
      <div v-if="!ordered.length" class="text-sm text-muted-foreground">No messages yet. Be the first to say hi!</div>
      <div v-for="m in ordered" :key="m.id" class="flex flex-col rounded bg-black/2 p-2 dark:bg-white/5">
        <div class="text-xs text-muted-foreground">
          <span class="font-medium text-foreground">{{ m.user?.name ?? 'User' }}</span>
          <span class="ml-2">{{ new Date(m.created_at).toLocaleTimeString() }}</span>
        </div>
        <div class="whitespace-pre-wrap break-words text-sm">{{ m.body }}</div>
      </div>
    </div>
  </div>
</template>
