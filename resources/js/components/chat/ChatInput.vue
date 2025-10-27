<script setup lang="ts">
import { toTypedSchema } from "@vee-validate/zod"
import { useForm } from "vee-validate"
import { ref } from "vue"
import * as z from "zod"

import { Button } from "@/components/ui/button"
import {
  FormControl,
  FormField,
  FormItem,
  FormMessage,
} from "@/components/ui/form"
import { Textarea } from "@/components/ui/textarea"

const emit = defineEmits<{
  (e: 'submit', body: string): void
}>()

const posting = ref(false)
const error = ref<string | null>(null)

const formSchema = toTypedSchema(z.object({
  message: z.string().min(1, {
    message: "Message cannot be empty.",
  }),
}))

const { handleSubmit, resetForm } = useForm({
  validationSchema: formSchema,
})

const onSubmit = handleSubmit(async (values) => {
  if (!values.message.trim()) return
  posting.value = true
  error.value = null
  try {
    await emit('submit', values.message)
    resetForm()
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to post'
  } finally {
    posting.value = false
  }
})

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    onSubmit()
  }
}
</script>

<template>
  <form class="w-full space-y-2" @submit="onSubmit">
    <div class="flex items-start gap-2">
      <FormField v-slot="{ componentField }" name="message">
        <FormItem class="flex-1">
          <FormControl>
            <Textarea
              placeholder="Type a message…"
              rows="2"
              class="resize-none"
              v-bind="componentField"
              @keydown="handleKeydown"
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <Button
        type="submit"
        :disabled="posting"
        class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
      >
        {{ posting ? 'Sending…' : 'Send' }}
      </Button>
    </div>
    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
  </form>
</template>
