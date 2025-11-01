<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { MessageSquare } from 'lucide-vue-next'

defineOptions({
    inheritAttrs: false,
});

interface Props {
    class?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <MessageSquare :class="$props.class" />
</template>
