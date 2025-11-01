<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from 'vue'
import ChatMessage from './ChatMessage.vue'
import { Button } from '@/components/ui/button'

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
  hasMore?: boolean
  scrollToBottom?: boolean
}>()

const emit = defineEmits<{
  (e: 'loadMore'): void
  (e: 'scrolled'): void
}>()

const messagesContainer = ref<HTMLElement | null>(null)
const isNearTop = ref<boolean>(false)
const previousScrollHeight = ref<number>(0)

// Handle scroll events to detect when user scrolls near top
function handleScroll() {
  if (!messagesContainer.value) return

  const { scrollTop, scrollHeight } = messagesContainer.value

  // Check if near top (within 100px)
  isNearTop.value = scrollTop < 100

  // Auto-load more when scrolled near top and has more messages
  if (isNearTop.value && props.hasMore && !props.loading) {
    previousScrollHeight.value = scrollHeight
    emit('loadMore')
  }
}

// Watch for new messages loaded (prepended to top)
watch(() => props.messages.length, async (newLength, oldLength) => {
  await nextTick()

  if (!messagesContainer.value) return

  // If messages were added (history loaded)
  if (newLength > oldLength) {
    // Calculate how much content was added
    const newScrollHeight = messagesContainer.value.scrollHeight
    const addedHeight = newScrollHeight - previousScrollHeight.value

    // If we loaded history (prepended), maintain scroll position
    if (previousScrollHeight.value > 0 && addedHeight > 0) {
      messagesContainer.value.scrollTop += addedHeight
      previousScrollHeight.value = 0
    }
  }
})

// Watch for scroll to bottom flag (after sending message)
watch(() => props.scrollToBottom, async (shouldScroll) => {
  if (shouldScroll) {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
    emit('scrolled')
  }
})

onMounted(() => {
  // Start at bottom when component mounts
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
})
</script>

<template>
  <div class="flex h-[500px] flex-col rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
    <div v-if="error" class="flex items-center justify-center p-3 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
    <div
      v-else
      ref="messagesContainer"
      class="flex flex-1 flex-col gap-2 overflow-y-auto p-3"
      @scroll="handleScroll"
    >
      <!-- Show More Button at top -->
      <div v-if="messages.length > 0 && hasMore" class="flex justify-center py-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="loading"
          @click="emit('loadMore')"
        >
          {{ loading ? 'Loading...' : 'Show More' }}
        </Button>
      </div>

      <!-- Loading indicator at top when loading history -->
      <div v-if="loading && messages.length > 0" class="flex justify-center py-2">
        <span class="text-sm text-muted-foreground">Loading messages...</span>
      </div>

      <!-- Empty state -->
      <div v-if="!messages.length && !loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        No messages yet. Be the first to say hi!
      </div>

      <!-- Initial loading state -->
      <div v-if="!messages.length && loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        Loading messages...
      </div>

      <!-- Messages -->
      <ChatMessage
        v-for="m in messages"
        :key="m.id"
        :message="m"
      />
    </div>
  </div>
</template>
