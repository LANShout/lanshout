<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { MessageSquare } from 'lucide-vue-next'

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <MessageSquare class="h-6 w-6" />
</template>
