<script setup lang="ts">
import { computed } from 'vue'

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
  message: Message
}>()

// Hash function to generate a color from a username
function hashStringToColor(str: string): string {
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash)
  }

  // Convert hash to HSL color (varied hue, consistent saturation and lightness)
  const hue = Math.abs(hash % 360)
  const saturation = 65 + (Math.abs(hash) % 20) // 65-85%
  const lightness = 45 + (Math.abs(hash >> 8) % 15) // 45-60%

  return `hsl(${hue}, ${saturation}%, ${lightness}%)`
}

const isSystem = computed(() => !props.message.user)
const isAnnouncement = computed(() => props.message.type === 'announcement')
const priority = computed(() => props.message.priority ?? 'normal')

const usernameColor = computed(() => {
  if (isSystem.value) {
    return undefined
  }
  // Use custom color if set, otherwise hash the username
  if (props.message.user?.chat_color) {
    return props.message.user.chat_color
  }
  return hashStringToColor(props.message.user?.name ?? 'User')
})

const formattedTime = computed(() => {
  return new Date(props.message.created_at).toLocaleTimeString()
})

const announcementClasses = computed(() => {
  if (!isAnnouncement.value) return ''
  if (priority.value === 'emergency') {
    return 'border-l-4 border-red-500 bg-red-50 dark:bg-red-950/30'
  }
  if (priority.value === 'silent') {
    return 'bg-muted/40 dark:bg-muted/20 opacity-75'
  }
  // normal
  return 'border-l-4 border-blue-400 bg-blue-50 dark:bg-blue-950/30'
})

const labelText = computed(() => {
  if (!isAnnouncement.value) {
    return isSystem.value ? '[System]' : (props.message.user?.name ?? 'User')
  }
  if (priority.value === 'emergency') return '🚨 Announcement'
  if (priority.value === 'silent') return 'Announcement'
  return '📢 Announcement'
})

const labelClasses = computed(() => {
  if (isAnnouncement.value) {
    if (priority.value === 'emergency') return 'font-semibold text-red-600 dark:text-red-400'
    if (priority.value === 'silent') return 'font-medium text-muted-foreground'
    return 'font-semibold text-blue-600 dark:text-blue-400'
  }
  return isSystem.value ? 'font-medium italic text-amber-600 dark:text-amber-400' : 'font-medium'
})
</script>

<template>
  <div :class="[
    'flex flex-col rounded p-2',
    isAnnouncement ? announcementClasses : (isSystem ? 'bg-amber-50 dark:bg-amber-950/30' : 'bg-black/2 dark:bg-white/5')
  ]">
    <div class="text-xs text-muted-foreground">
      <span
        :class="labelClasses"
        :style="(!isSystem && !isAnnouncement) ? { color: usernameColor } : undefined"
      >
        {{ labelText }}
      </span>
      <span class="ml-2">{{ formattedTime }}</span>
    </div>
    <div :class="[
      'whitespace-pre-wrap break-words text-sm',
      isAnnouncement && priority === 'emergency' ? 'font-medium' : '',
      (isSystem && !isAnnouncement) ? 'italic text-muted-foreground' : ''
    ]">{{ message.body }}</div>
  </div>
</template>
