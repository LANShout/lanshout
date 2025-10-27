<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Users, Settings, Shield, MessageSquare, Activity } from 'lucide-vue-next';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

interface Statistics {
    userCount: number;
    messageCount: number;
    activeSessions: number;
}

interface Props {
    statistics: Statistics;
}

const props = defineProps<Props>();

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

const chartData = computed(() => ({
    labels: ['Users', 'Messages', 'Active Sessions'],
    datasets: [
        {
            label: 'Statistics',
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
            data: [props.statistics.userCount, props.statistics.messageCount, props.statistics.activeSessions],
        }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        title: {
            display: false,
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0
            }
        }
    }
};
</script>

<template>
    <Head title="Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-bold">Admin Panel</h1>
                <p class="text-muted-foreground mt-2">Manage your LanShout instance</p>
            </div>

            <!-- Statistics Section -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- User Count Card -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Users</p>
                            <h3 class="mt-2 text-3xl font-bold">{{ statistics.userCount }}</h3>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500">
                            <Users class="h-6 w-6" />
                        </div>
                    </div>
                </div>

                <!-- Message Count Card -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Messages</p>
                            <h3 class="mt-2 text-3xl font-bold">{{ statistics.messageCount }}</h3>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-500/10 text-green-500">
                            <MessageSquare class="h-6 w-6" />
                        </div>
                    </div>
                </div>

                <!-- Active Sessions Card -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Active Sessions</p>
                            <h3 class="mt-2 text-3xl font-bold">{{ statistics.activeSessions }}</h3>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                            <Activity class="h-6 w-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <h2 class="mb-4 text-lg font-semibold">Statistics Overview</h2>
                <div class="h-64">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
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
