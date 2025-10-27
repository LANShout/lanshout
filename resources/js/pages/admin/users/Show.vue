<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail, Calendar, CheckCircle, XCircle } from 'lucide-vue-next';

interface Permission {
    id: number;
    name: string;
    display_name: string;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
    description: string;
    permissions: Permission[];
}

interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles: Role[];
}

interface Props {
    user: User;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: admin.index().url,
    },
    {
        title: 'Users',
        href: '/admin/users',
    },
    {
        title: 'User Details',
        href: '#',
    },
];

const getRoleBadgeVariant = (roleName: string) => {
    switch (roleName) {
        case 'super_admin':
            return 'destructive';
        case 'admin':
            return 'default';
        case 'moderator':
            return 'secondary';
        default:
            return 'outline';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`User: ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <Link href="/admin/users">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                        <p class="text-muted-foreground mt-1 flex items-center gap-2">
                            <Mail class="h-4 w-4" />
                            {{ user.email }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <h2 class="text-lg font-semibold mb-4">User Information</h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Email Verification</span>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="user.email_verified_at ? 'default' : 'secondary'"
                                class="flex items-center gap-1"
                            >
                                <component
                                    :is="user.email_verified_at ? CheckCircle : XCircle"
                                    class="h-3 w-3"
                                />
                                {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                            </Badge>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Member Since</span>
                        <div class="flex items-center gap-2 text-sm">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            {{ formatDate(user.created_at) }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Last Updated</span>
                        <div class="flex items-center gap-2 text-sm">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            {{ formatDate(user.updated_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Roles Card -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <h2 class="text-lg font-semibold mb-4">Roles</h2>

                <div v-if="user.roles.length > 0" class="space-y-4">
                    <div v-for="role in user.roles" :key="role.id" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Badge :variant="getRoleBadgeVariant(role.name)">
                                {{ role.display_name }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ role.description }}
                        </p>

                        <!-- Permissions for this role -->
                        <div v-if="role.permissions.length > 0" class="ml-4 mt-2">
                            <p class="text-xs font-medium text-muted-foreground mb-2">Permissions:</p>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="permission in role.permissions"
                                    :key="permission.id"
                                    variant="outline"
                                    class="text-xs"
                                >
                                    {{ permission.display_name }}
                                </Badge>
                            </div>
                        </div>

                        <Separator v-if="user.roles.indexOf(role) < user.roles.length - 1" class="mt-4" />
                    </div>
                </div>
                <div v-else class="text-center text-muted-foreground py-4">
                    No roles assigned
                </div>
            </div>
        </div>
    </AppLayout>
</template>
