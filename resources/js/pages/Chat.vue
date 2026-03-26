<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Settings } from 'lucide-vue-next';
import ChatWall from '../components/chat/ChatWall.vue';
import ChatInput from '../components/chat/ChatInput.vue';
import chat from '@/routes/chat';

const { t } = useI18n();

interface User { id: number; name: string; chat_color?: string | null }
interface Message { id: number; body: string; type?: string | null; priority?: string | null; created_at: string; user: User | null }
interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const props = defineProps<{
  lastReadAt?: string | null;
  unreadCount?: number;
  isModerator?: boolean;
}>()

// Capture lastReadAt at page-load time; stays fixed for this session so the
// divider position doesn't jump once mark-read fires.
const initialLastReadAt = ref<string | null>(props.lastReadAt ?? null)

const messages = ref<Message[]>([]);
const loading = ref<boolean>(false);
const error = ref<string | null>(null);
const currentPage = ref<number>(0);
const hasMore = ref<boolean>(true);
const scrollToBottomFlag = ref<boolean>(false);
const scrollToUnreadFlag = ref<boolean>(false);

// True when the oldest loaded message is still newer than lastReadAt,
// meaning there is more unread history above what we have loaded.
const moreUnreadAbove = computed(() => {
  if (!initialLastReadAt.value || !messages.value.length) return false
  const threshold = new Date(initialLastReadAt.value).getTime()
  return new Date(messages.value[0].created_at).getTime() > threshold
})

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
      // API returns newest first (DESC), reverse to oldest-first, then prepend
      const reversed = [...items].reverse();
      messages.value = [...reversed, ...messages.value];
      currentPage.value = nextPage;

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

async function markRead() {
  const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
  await fetch('/chat/mark-read', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token ?? '', Accept: 'application/json' },
  }).catch(() => { /* non-critical */ })
}

onMounted(async () => {
  await loadMoreMessages();

  if (initialLastReadAt.value) {
    // Scroll to the unread divider if it's within the loaded batch,
    // otherwise ChatWall falls back to scrolling to the bottom.
    scrollToUnreadFlag.value = true;
  }

  markRead();
});

// Listen for new messages from other users via WebSocket
useEchoPublic('chat', 'MessageSent', (e: Message) => {
  if (!messages.value.some((m) => m.id === e.id)) {
    messages.value = [...messages.value, e];
    scrollToBottomFlag.value = true;
  }
});
</script>

<template>
  <Head :title="$t('chat.title')" />
  <AppLayout>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-3 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ $t('chat.title') }}</h1>
        <Button v-if="props.isModerator" variant="outline" size="sm" as-child>
          <Link :href="chat.settings.url()">
            <Settings class="mr-1 h-4 w-4" />
            Settings
          </Link>
        </Button>
      </div>
      <ChatWall
        :messages="messages"
        :loading="loading"
        :error="error"
        :has-more="hasMore"
        :scroll-to-bottom="scrollToBottomFlag"
        :scroll-to-unread="scrollToUnreadFlag"
        :last-read-at="initialLastReadAt"
        :more-unread-above="moreUnreadAbove"
        :unread-count="unreadCount"
        @load-more="loadMoreMessages"
        @scrolled="scrollToBottomFlag = false"
        @unread-scrolled="scrollToUnreadFlag = false"
      />
      <ChatInput @submit="submitMessage" />
    </div>
  </AppLayout>
</template>
