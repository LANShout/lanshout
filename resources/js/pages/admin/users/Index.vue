<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';

interface Role {
    id: number;
    name: string;
    display_name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    roles: Role[];
}

interface Props {
    users: User[];
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
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Users</h1>
                    <p class="text-muted-foreground mt-1">
                        Manage user accounts and roles
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead>Verified</TableHead>
                            <TableHead>Joined</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users" :key="user.id">
                            <TableCell class="font-medium">
                                {{ user.name }}
                            </TableCell>
                            <TableCell>
                                {{ user.email }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        :variant="getRoleBadgeVariant(role.name)"
                                        class="text-xs"
                                    >
                                        {{ role.display_name }}
                                    </Badge>
                                    <Badge v-if="user.roles.length === 0" variant="outline" class="text-xs">
                                        No roles
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="user.email_verified_at ? 'default' : 'secondary'"
                                    class="text-xs"
                                >
                                    {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatDate(user.created_at) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="`/admin/users/${user.id}`">
                                        <Eye class="h-4 w-4 mr-1" />
                                        View
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.length === 0">
                            <TableCell colspan="6" class="text-center text-muted-foreground py-8">
                                No users found
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
