<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Users, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: admin.index().url,
    },
];

const page = usePage();
const auth = computed(() => page.props.auth);

const hasAnyRole = (roles: string[]): boolean => {
    const userRoles = auth.value?.user?.roles || [];
    return roles.some(role => userRoles.includes(role));
};

const canManageUsers = computed(() => hasAnyRole(['admin', 'super_admin']));
</script>

<template>
    <Head title="Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-bold">Admin Panel</h1>
                <p class="text-muted-foreground mt-2">Manage your LanShout instance</p>
            </div>

            <!-- Management Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- User Management Card -->
                <Link
                    v-if="canManageUsers"
                    href="/admin/users"
                    class="group relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-card p-6 transition-all hover:border-primary hover:shadow-md dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Users class="h-6 w-6" />
                        </div>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">User Management</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        View and manage user accounts and roles
                    </p>
                </Link>

                <!-- Roles & Permissions Card (Placeholder) -->
                <div
                    class="relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-card p-6 opacity-50 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <Shield class="h-6 w-6" />
                        </div>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">Roles & Permissions</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Coming soon
                    </p>
                </div>

                <!-- System Settings Card (Placeholder) -->
                <div
                    class="relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-card p-6 opacity-50 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <Settings class="h-6 w-6" />
                        </div>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">System Settings</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Coming soon
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
