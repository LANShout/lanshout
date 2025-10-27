<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import ChatMessage from './ChatMessage.vue'

type User = {
  id: number
  name: string
  chat_color?: string | null
}

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
const messagesContainer = ref<HTMLElement | null>(null)

// Auto-scroll to bottom when new messages arrive
watch(() => props.messages.length, async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
})
</script>

<template>
  <div class="flex h-[500px] flex-col rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
    <div v-if="loading" class="flex items-center justify-center p-3 text-sm text-muted-foreground">Loading…</div>
    <div v-else-if="error" class="flex items-center justify-center p-3 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
    <div
      v-else
      ref="messagesContainer"
      class="flex flex-1 flex-col gap-2 overflow-y-auto p-3"
    >
      <div v-if="!ordered.length" class="flex items-center justify-center text-sm text-muted-foreground">
        No messages yet. Be the first to say hi!
      </div>
      <ChatMessage
        v-for="m in ordered"
        :key="m.id"
        :message="m"
      />
    </div>
  </div>
</template>
