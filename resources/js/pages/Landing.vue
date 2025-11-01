<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { login, register } from '@/routes'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { Users, Zap } from 'lucide-vue-next'

withDefaults(defineProps<{ canRegister?: boolean }>(), { canRegister: true })
</script>

<template>
  <Head :title="$t('landing.title')" />
  <div class="min-h-screen bg-background">
    <!-- Header -->
    <header class="border-b border-border/40">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
        <div class="flex items-center gap-2">
          <AppLogoIcon class="h-6 w-6 text-primary" />
          <span class="text-xl font-bold">{{ $t('common.appName') }}</span>
        </div>
        <div class="flex gap-3">
          <Link :href="login()" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
            {{ $t('landing.logIn') }}
          </Link>
          <Link v-if="canRegister" :href="register()" class="rounded-md border border-input px-4 py-2 text-sm font-medium hover:bg-accent">
            {{ $t('landing.register') }}
          </Link>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <main class="mx-auto max-w-7xl px-4 py-16 sm:py-24">
      <div class="text-center">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl">
          {{ $t('landing.title').replace('LanShout', '') }}<span class="text-primary">{{ $t('common.appName') }}</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
          {{ $t('landing.subtitle') }}
        </p>

        <!-- CTA Buttons -->
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <Link
            :href="login()"
            class="rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground hover:opacity-90 transition-opacity"
          >
            {{ $t('landing.getStarted') }}
          </Link>
          <Link
            v-if="canRegister"
            :href="register()"
            class="rounded-md border border-input px-8 py-3 text-base font-medium hover:bg-accent transition-colors"
          >
            {{ $t('landing.createAccount') }}
          </Link>
        </div>
      </div>

      <!-- Features Section -->
      <div class="mt-24 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <AppLogoIcon class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">{{ $t('landing.features.simpleMessaging.title') }}</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            {{ $t('landing.features.simpleMessaging.description') }}
          </p>
        </div>

        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <Zap class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">{{ $t('landing.features.realTimeUpdates.title') }}</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            {{ $t('landing.features.realTimeUpdates.description') }}
          </p>
        </div>

        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <Users class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">{{ $t('landing.features.builtForEvents.title') }}</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            {{ $t('landing.features.builtForEvents.description') }}
          </p>
        </div>
      </div>

      <!-- Additional Info -->
      <div class="mt-24 text-center">
        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
          {{ $t('landing.cta.title') }}
        </h2>
        <p class="mx-auto mt-4 max-w-2xl text-muted-foreground">
          {{ $t('landing.cta.description') }}
        </p>
        <div class="mt-8">
          <Link
            :href="login()"
            class="rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground hover:opacity-90 transition-opacity"
          >
            {{ $t('landing.cta.button') }}
          </Link>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border/40 mt-24">
      <div class="mx-auto max-w-7xl px-4 py-8 text-center text-sm text-muted-foreground">
        <p>{{ $t('landing.footer') }}</p>
      </div>
    </footer>
  </div>
</template>
