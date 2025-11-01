<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import ChatWall from '../components/chat/ChatWall.vue';
import ChatInput from '../components/chat/ChatInput.vue';

const { t } = useI18n();

interface User { id: number; name: string; chat_color?: string | null }
interface Message { id: number; body: string; created_at: string; user: User }
interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const messages = ref<Message[]>([]);
const loading = ref<boolean>(false);
const error = ref<string | null>(null);
const currentPage = ref<number>(0);
const hasMore = ref<boolean>(true);
const scrollToBottomFlag = ref<boolean>(false);

async function loadMoreMessages() {
  if (loading.value || !hasMore.value) return;

  loading.value = true;
  error.value = null;

  try {
    const nextPage = currentPage.value + 1;
    const res = await fetch(`/messages?page=${nextPage}&per_page=20`, {
      headers: { Accept: 'application/json' }
    });
    const json = await res.json();

    const items: Message[] = json?.data ?? [];
    const meta: PaginationMeta | undefined = json?.meta;

    if (Array.isArray(items) && items.length > 0) {
      // API returns newest first (DESC), reverse to get oldest first, then prepend
      const reversed = [...items].reverse();
      messages.value = [...reversed, ...messages.value];
      currentPage.value = nextPage;

      // Check if there are more pages
      if (meta) {
        hasMore.value = meta.current_page < meta.last_page;
      } else {
        hasMore.value = items.length === 20;
      }
    } else {
      hasMore.value = false;
    }
  } catch (e: any) {
    error.value = e?.message ?? t('chat.errorLoading');
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
    throw new Error(data?.message || t('chat.errorSending'));
  }
  const data = await res.json();
  const msg: Message = data?.data ?? data;
  messages.value = [...messages.value, msg];
  scrollToBottomFlag.value = true;
}

// Load initial messages when component mounts
onMounted(() => {
  loadMoreMessages();
});
</script>

<template>
  <Head :title="$t('chat.title')" />
  <AppLayout>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-3 p-4">
      <h1 class="text-xl font-semibold">{{ $t('chat.title') }}</h1>
      <ChatWall
        :messages="messages"
        :loading="loading"
        :error="error"
        :has-more="hasMore"
        :scroll-to-bottom="scrollToBottomFlag"
        @load-more="loadMoreMessages"
        @scrolled="scrollToBottomFlag = false"
      />
      <ChatInput @submit="submitMessage" />
    </div>
  </AppLayout>
</template>
