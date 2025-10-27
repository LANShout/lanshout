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
  created_at: string
  user: User
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

const usernameColor = computed(() => {
  // Use custom color if set, otherwise hash the username
  if (props.message.user?.chat_color) {
    return props.message.user.chat_color
  }
  return hashStringToColor(props.message.user?.name ?? 'User')
})

const formattedTime = computed(() => {
  return new Date(props.message.created_at).toLocaleTimeString()
})
</script>

<template>
  <div class="flex flex-col rounded bg-black/2 p-2 dark:bg-white/5">
    <div class="text-xs text-muted-foreground">
      <span
        class="font-medium"
        :style="{ color: usernameColor }"
      >
        {{ message.user?.name ?? 'User' }}
      </span>
      <span class="ml-2">{{ formattedTime }}</span>
    </div>
    <div class="whitespace-pre-wrap break-words text-sm">{{ message.body }}</div>
  </div>
</template>
