<script setup lang="ts">
import { ref, watch, nextTick, onMounted, computed } from 'vue'
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
  type?: string | null
  priority?: string | null
  created_at: string
  user: User | null
}

const props = defineProps<{
  messages: Message[]
  loading?: boolean
  error?: string | null
  hasMore?: boolean
  scrollToBottom?: boolean
  scrollToUnread?: boolean
  lastReadAt?: string | null
  moreUnreadAbove?: boolean
  unreadCount?: number
}>()

const emit = defineEmits<{
  (e: 'loadMore'): void
  (e: 'scrolled'): void
  (e: 'unreadScrolled'): void
}>()

const messagesContainer = ref<HTMLElement | null>(null)
const unreadDividerRef = ref<HTMLElement | null>(null)
const isNearTop = ref<boolean>(false)
const isNearBottom = ref<boolean>(true)
const previousScrollHeight = ref<number>(0)
const liveUnreadCount = ref<number>(0)

// Index of the first unread message in the (oldest-to-newest) messages array
const firstUnreadIndex = computed(() => {
  if (!props.lastReadAt) return -1
  const threshold = new Date(props.lastReadAt).getTime()
  return props.messages.findIndex(m => new Date(m.created_at).getTime() > threshold)
})

function handleScroll() {
  if (!messagesContainer.value) return

  const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value

  isNearTop.value = scrollTop < 100
  isNearBottom.value = scrollHeight - scrollTop - clientHeight < 80

  if (isNearBottom.value) {
    liveUnreadCount.value = 0
  }

  if (isNearTop.value && props.hasMore && !props.loading) {
    previousScrollHeight.value = scrollHeight
    emit('loadMore')
  }
}

function jumpToBottom() {
  if (!messagesContainer.value) return
  messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  liveUnreadCount.value = 0
}

function scrollToUnreadDivider() {
  if (unreadDividerRef.value) {
    unreadDividerRef.value.scrollIntoView({ block: 'center' })
  } else {
    jumpToBottom()
  }
  emit('unreadScrolled')
}

// Watch for new messages loaded (prepended to top as history)
watch(() => props.messages.length, async (newLength, oldLength) => {
  await nextTick()

  if (!messagesContainer.value) return

  if (newLength > oldLength) {
    const newScrollHeight = messagesContainer.value.scrollHeight
    const addedHeight = newScrollHeight - previousScrollHeight.value

    if (previousScrollHeight.value > 0 && addedHeight > 0) {
      messagesContainer.value.scrollTop += addedHeight
      previousScrollHeight.value = 0
    } else if (!isNearBottom.value) {
      // New real-time message arrived while scrolled up — increment badge
      liveUnreadCount.value += newLength - oldLength
    }
  }
})

// Scroll to bottom when user sends a message
watch(() => props.scrollToBottom, async (shouldScroll) => {
  if (shouldScroll) {
    await nextTick()
    jumpToBottom()
    emit('scrolled')
  }
})

// Scroll to unread divider when triggered
watch(() => props.scrollToUnread, async (shouldScroll) => {
  if (shouldScroll) {
    await nextTick()
    scrollToUnreadDivider()
  }
})

onMounted(() => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
})
</script>

<template>
  <div class="relative flex h-[500px] flex-col rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
    <div v-if="error" class="flex items-center justify-center p-3 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
    <div
      v-else
      ref="messagesContainer"
      class="flex flex-1 flex-col gap-2 overflow-y-auto p-3"
      @scroll="handleScroll"
    >
      <!-- Load older messages button -->
      <div v-if="messages.length > 0 && hasMore" class="flex justify-center py-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="loading"
          @click="emit('loadMore')"
        >
          {{ loading ? $t('chat.loadingMessages') : $t('chat.loadMore') }}
        </Button>
      </div>

      <!-- Loading indicator when fetching older page -->
      <div v-if="loading && messages.length > 0" class="flex justify-center py-2">
        <span class="text-sm text-muted-foreground">{{ $t('chat.loadingMessages') }}</span>
      </div>

      <!-- "More unread above" banner shown when all loaded messages are unread -->
      <div
        v-if="moreUnreadAbove && !loading"
        class="flex items-center justify-center gap-2 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
      >
        <span>↑ {{ $t('chat.moreUnreadAbove', { count: unreadCount }) }}</span>
        <button class="underline underline-offset-2 hover:no-underline" @click="emit('loadMore')">
          {{ $t('chat.loadMore') }}
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!messages.length && !loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        {{ $t('chat.noMessages') }}
      </div>

      <!-- Initial loading state -->
      <div v-if="!messages.length && loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        {{ $t('chat.loadingMessages') }}
      </div>

      <!-- Messages with unread divider injected at boundary -->
      <template v-for="(m, index) in messages" :key="m.id">
        <div
          v-if="index === firstUnreadIndex && firstUnreadIndex > 0"
          ref="unreadDividerRef"
          class="flex items-center gap-2 py-1"
        >
          <div class="h-px flex-1 bg-blue-400 dark:bg-blue-600" />
          <span class="shrink-0 text-xs font-medium text-blue-600 dark:text-blue-400">{{ $t('chat.newMessages') }}</span>
          <div class="h-px flex-1 bg-blue-400 dark:bg-blue-600" />
        </div>
        <ChatMessage :message="m" />
      </template>
    </div>

    <!-- Jump to bottom / new messages badge -->
    <Transition name="fade">
      <button
        v-if="!isNearBottom"
        class="absolute bottom-3 right-3 flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-md transition-opacity hover:opacity-90"
        @click="jumpToBottom"
      >
        <template v-if="liveUnreadCount > 0">{{ liveUnreadCount }} ↓</template>
        <template v-else>↓</template>
      </button>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
