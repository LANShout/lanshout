<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AreaChart } from '@/components/ui/chart-area';
import { Users, MessageSquare, Activity } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';

interface Statistics {
    userCount: number;
    messageCount: number;
    activeSessions: number;
}

interface Props {
    statistics: Statistics;
}

interface ChartDataPoint {
    name: string;
    value: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Chart controls
const selectedResolution = ref<'hour' | 'day' | 'week'>('day');
const selectedMetric = ref<'messages' | 'users' | 'sessions'>('messages');
const chartData = ref<ChartDataPoint[]>([]);
const loading = ref(false);

// Fetch chart data from API
async function fetchChartData() {
    loading.value = true;
    try {
        const response = await fetch(
            `/dashboard/statistics?resolution=${selectedResolution.value}&metric=${selectedMetric.value}`,
            {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (!response.ok) {
            throw new Error('Failed to fetch statistics');
        }

        const data = await response.json();
        chartData.value = data;
    } catch (error) {
        console.error('Error fetching chart data:', error);
        chartData.value = [];
    } finally {
        loading.value = false;
    }
}

// Watch for changes in resolution or metric
watch([selectedResolution, selectedMetric], () => {
    fetchChartData();
});

// Fetch initial data
onMounted(() => {
    fetchChartData();
});

// Metric labels
const metricLabels = {
    messages: 'Messages',
    users: 'User Registrations',
    sessions: 'Active Users',
};

const chartTitle = computed(() => {
    const resolutionText = selectedResolution.value === 'hour' ? 'Hourly' :
                          selectedResolution.value === 'day' ? 'Daily' : 'Weekly';
    return `${resolutionText} ${metricLabels[selectedMetric.value]}`;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <p class="text-muted-foreground mt-2">Overview of your LanShout instance</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- User Count Card -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Users</p>
                            <h3 class="mt-2 text-3xl font-bold">{{ props.statistics.userCount }}</h3>
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
                            <h3 class="mt-2 text-3xl font-bold">{{ props.statistics.messageCount }}</h3>
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
                            <h3 class="mt-2 text-3xl font-bold">{{ props.statistics.activeSessions }}</h3>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                            <Activity class="h-6 w-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold">{{ chartTitle }}</h2>

                    <div class="flex flex-wrap gap-2">
                        <!-- Metric Selector -->
                        <div class="flex gap-1 rounded-lg border border-sidebar-border/70 p-1">
                            <Button
                                size="sm"
                                :variant="selectedMetric === 'messages' ? 'default' : 'ghost'"
                                @click="selectedMetric = 'messages'"
                                class="text-xs"
                            >
                                Messages
                            </Button>
                            <Button
                                size="sm"
                                :variant="selectedMetric === 'users' ? 'default' : 'ghost'"
                                @click="selectedMetric = 'users'"
                                class="text-xs"
                            >
                                Users
                            </Button>
                            <Button
                                size="sm"
                                :variant="selectedMetric === 'sessions' ? 'default' : 'ghost'"
                                @click="selectedMetric = 'sessions'"
                                class="text-xs"
                            >
                                Sessions
                            </Button>
                        </div>

                        <!-- Resolution Selector -->
                        <div class="flex gap-1 rounded-lg border border-sidebar-border/70 p-1">
                            <Button
                                size="sm"
                                :variant="selectedResolution === 'hour' ? 'default' : 'ghost'"
                                @click="selectedResolution = 'hour'"
                                class="text-xs"
                            >
                                Hour
                            </Button>
                            <Button
                                size="sm"
                                :variant="selectedResolution === 'day' ? 'default' : 'ghost'"
                                @click="selectedResolution = 'day'"
                                class="text-xs"
                            >
                                Day
                            </Button>
                            <Button
                                size="sm"
                                :variant="selectedResolution === 'week' ? 'default' : 'ghost'"
                                @click="selectedResolution = 'week'"
                                class="text-xs"
                            >
                                Week
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="h-80">
                    <div v-if="loading" class="flex h-full items-center justify-center text-muted-foreground">
                        Loading chart data...
                    </div>
                    <AreaChart
                        v-else-if="chartData.length > 0"
                        :data="chartData"
                        index="name"
                        :categories="['value']"
                    />
                    <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                        No data available
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
