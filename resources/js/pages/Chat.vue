<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import ChatWall from '../components/chat/ChatWall.vue';
import ChatInput from '../components/chat/ChatInput.vue';

interface User { id: number; name: string }
interface Message { id: number; body: string; created_at: string; user: User }

const messages = ref<Message[]>([]);
const loading = ref<boolean>(false);
const error = ref<string | null>(null);

async function loadMessages() {
  loading.value = true;
  error.value = null;
  try {
    const res = await fetch('/messages?per_page=50', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    const items: Message[] = json?.data ?? json;
    // API returns newest first; display oldest first so wall grows downward
    messages.value = Array.isArray(items) ? items.slice().reverse() : [];
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to load messages';
  } finally {
    loading.value = false;
  }
}

async function submitMessage(body: string) {
  const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
  const res = await fetch('/messages', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': token ?? '',
    },
    body: JSON.stringify({ body }),
  });
  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data?.message || 'Failed to post message');
  }
  const data = await res.json();
  const msg: Message = data?.data ?? data;
  messages.value = [...messages.value, msg];
}

onMounted(loadMessages);
</script>

<template>
  <Head title="Chat" />
  <AppLayout>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-3 p-4">
      <h1 class="text-xl font-semibold">Chat</h1>
      <ChatWall :messages="messages" :loading="loading" :error="error" />
      <ChatInput @submit="submitMessage" />
    </div>
  </AppLayout>
</template>
