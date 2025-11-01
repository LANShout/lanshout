<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { login, register } from '@/routes'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { Users, Zap } from 'lucide-vue-next'

withDefaults(defineProps<{ canRegister?: boolean }>(), { canRegister: true })
</script>

<template>
  <Head title="Welcome to LanShout" />
  <div class="min-h-screen bg-background">
    <!-- Header -->
    <header class="border-b border-border/40">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
        <div class="flex items-center gap-2">
          <AppLogoIcon class="h-6 w-6 text-primary" />
          <span class="text-xl font-bold">LanShout</span>
        </div>
        <div class="flex gap-3">
          <Link :href="login()" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
            Log in
          </Link>
          <Link v-if="canRegister" :href="register()" class="rounded-md border border-input px-4 py-2 text-sm font-medium hover:bg-accent">
            Register
          </Link>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <main class="mx-auto max-w-7xl px-4 py-16 sm:py-24">
      <div class="text-center">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl">
          Welcome to <span class="text-primary">LanShout</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
          A simple, real-time shoutbox designed for LAN parties and events.
          Let everyone share messages, coordinate games, and stay connected during your event.
        </p>

        <!-- CTA Buttons -->
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <Link
            :href="login()"
            class="rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground hover:opacity-90 transition-opacity"
          >
            Get Started
          </Link>
          <Link
            v-if="canRegister"
            :href="register()"
            class="rounded-md border border-input px-8 py-3 text-base font-medium hover:bg-accent transition-colors"
          >
            Create Account
          </Link>
        </div>
      </div>

      <!-- Features Section -->
      <div class="mt-24 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <AppLogoIcon class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">Simple Messaging</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            Post quick messages and updates that everyone at your LAN can see instantly.
          </p>
        </div>

        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <Zap class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">Real-Time Updates</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            Messages appear instantly for all users with real-time synchronization.
          </p>
        </div>

        <div class="flex flex-col items-center text-center p-6 rounded-lg border border-border/40 bg-card">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
            <Users class="h-6 w-6 text-primary" />
          </div>
          <h3 class="mt-4 text-lg font-semibold">Built for Events</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            Perfect for LAN parties, gaming events, and community gatherings.
          </p>
        </div>
      </div>

      <!-- Additional Info -->
      <div class="mt-24 text-center">
        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
          Ready to connect with your community?
        </h2>
        <p class="mx-auto mt-4 max-w-2xl text-muted-foreground">
          Log in to start chatting or create an account to join the conversation.
        </p>
        <div class="mt-8">
          <Link
            :href="login()"
            class="rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground hover:opacity-90 transition-opacity"
          >
            Log in to Chat
          </Link>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border/40 mt-24">
      <div class="mx-auto max-w-7xl px-4 py-8 text-center text-sm text-muted-foreground">
        <p>LanShout - Simple real-time messaging for your events</p>
      </div>
    </footer>
  </div>
</template>
