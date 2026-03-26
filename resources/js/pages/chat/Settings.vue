<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Head } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import { type BreadcrumbItem } from '@/types';
import chat from '@/routes/chat';
import { Plus, Pencil, Trash2, Clock, ShieldBan, ShieldCheck, CircleAlert, CheckCircle2, Loader2 } from 'lucide-vue-next';

// ─── Types ───────────────────────────────────────────────────────────────────

interface FilterChain {
    id: number;
    name: string;
    type: 'contains' | 'regex' | 'exact';
    pattern: string;
    action: 'block' | 'replace' | 'warn';
    replacement: string | null;
    is_active: boolean;
    priority: number;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
}

interface ModerationUser {
    id: number;
    name: string;
    email: string;
    is_blocked: boolean;
    block_reason: string | null;
    blocked_at: string | null;
    timed_out_until: string | null;
    timeout_reason: string | null;
    roles: Role[];
}

interface Props {
    filterChains: FilterChain[];
    slowMode: { enabled: boolean; seconds: number };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chat', href: '/chat' },
    { title: 'Settings', href: '/chat/settings' },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function apiFetch(url: string, method: string, body?: Record<string, unknown>) {
    const res = await fetch(url, {
        method: method.toUpperCase(),
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error((data as { message?: string }).message ?? 'Request failed');
    }
    return data;
}

function isTimedOut(user: ModerationUser): boolean {
    if (!user.timed_out_until) return false;
    return new Date(user.timed_out_until) > new Date();
}

function isModeratorUser(user: ModerationUser): boolean {
    return user.roles.some((r) => ['super_admin', 'admin', 'moderator'].includes(r.name));
}

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

function getRoleBadgeVariant(roleName: string): 'destructive' | 'default' | 'secondary' | 'outline' {
    switch (roleName) {
        case 'super_admin': return 'destructive';
        case 'admin': return 'default';
        case 'moderator': return 'secondary';
        default: return 'outline';
    }
}

// ─── Tab ─────────────────────────────────────────────────────────────────────

type Tab = 'users' | 'filters' | 'slowmode';
const activeTab = ref<Tab>('users');

// ─── Slow Mode ───────────────────────────────────────────────────────────────

const slowModeEnabled = ref(props.slowMode.enabled);
const slowModeSeconds = ref(props.slowMode.seconds);
const slowModeSaving = ref(false);
const slowModeMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

async function saveSlowMode() {
    slowModeSaving.value = true;
    slowModeMessage.value = null;
    try {
        await apiFetch(chat.slowMode.update.url(), 'PUT', {
            enabled: slowModeEnabled.value,
            seconds: slowModeEnabled.value ? Number(slowModeSeconds.value) : 10,
        });
        slowModeMessage.value = { type: 'success', text: 'Slow mode settings saved.' };
    } catch (e: unknown) {
        slowModeMessage.value = { type: 'error', text: (e as Error).message };
    } finally {
        slowModeSaving.value = false;
    }
}

// ─── Filters ─────────────────────────────────────────────────────────────────

const filters = ref<FilterChain[]>([...props.filterChains]);
const filtersMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

const filterDialogOpen = ref(false);
const filterDialogMode = ref<'create' | 'edit'>('create');
const filterDialogSaving = ref(false);
const editingFilter = ref<FilterChain | null>(null);

function emptyFilterForm() {
    return { name: '', type: 'contains' as const, pattern: '', action: 'block' as const, replacement: '', is_active: true, priority: 0 };
}

const filterForm = ref(emptyFilterForm());

function openCreateFilter() {
    filterDialogMode.value = 'create';
    filterForm.value = emptyFilterForm();
    editingFilter.value = null;
    filterDialogOpen.value = true;
}

function openEditFilter(filter: FilterChain) {
    filterDialogMode.value = 'edit';
    editingFilter.value = filter;
    filterForm.value = {
        name: filter.name,
        type: filter.type,
        pattern: filter.pattern,
        action: filter.action,
        replacement: filter.replacement ?? '',
        is_active: filter.is_active,
        priority: filter.priority,
    };
    filterDialogOpen.value = true;
}

async function saveFilter() {
    filterDialogSaving.value = true;
    filtersMessage.value = null;
    try {
        const payload: Record<string, unknown> = {
            name: filterForm.value.name,
            type: filterForm.value.type,
            pattern: filterForm.value.pattern,
            action: filterForm.value.action,
            replacement: filterForm.value.action === 'replace' ? filterForm.value.replacement || null : null,
            is_active: filterForm.value.is_active,
            priority: Number(filterForm.value.priority),
        };

        if (filterDialogMode.value === 'create') {
            const res = await apiFetch(chat.filters.store.url(), 'POST', payload);
            filters.value.push(res.filter);
            filtersMessage.value = { type: 'success', text: res.message };
        } else if (editingFilter.value) {
            const res = await apiFetch(chat.filters.update.url(editingFilter.value.id), 'PUT', payload);
            const idx = filters.value.findIndex((f) => f.id === editingFilter.value!.id);
            if (idx !== -1) filters.value[idx] = res.filter;
            filtersMessage.value = { type: 'success', text: res.message };
        }
        filterDialogOpen.value = false;
    } catch (e: unknown) {
        filtersMessage.value = { type: 'error', text: (e as Error).message };
    } finally {
        filterDialogSaving.value = false;
    }
}

const deleteFilterDialogOpen = ref(false);
const deletingFilter = ref<FilterChain | null>(null);
const deleteFilterSaving = ref(false);

function openDeleteFilter(filter: FilterChain) {
    deletingFilter.value = filter;
    deleteFilterDialogOpen.value = true;
}

async function confirmDeleteFilter() {
    if (!deletingFilter.value) return;
    deleteFilterSaving.value = true;
    filtersMessage.value = null;
    try {
        const res = await apiFetch(chat.filters.destroy.url(deletingFilter.value.id), 'DELETE');
        filters.value = filters.value.filter((f) => f.id !== deletingFilter.value!.id);
        filtersMessage.value = { type: 'success', text: res.message };
        deleteFilterDialogOpen.value = false;
    } catch (e: unknown) {
        filtersMessage.value = { type: 'error', text: (e as Error).message };
    } finally {
        deleteFilterSaving.value = false;
    }
}

// ─── Users ───────────────────────────────────────────────────────────────────

const users = ref<ModerationUser[]>([]);
const usersLoading = ref(false);
const usersError = ref<string | null>(null);
const usersCurrentPage = ref(1);
const usersLastPage = ref(1);
const searchQuery = ref('');
const userFilterMode = ref<'all' | 'blocked' | 'timed_out'>('all');
const usersMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function loadUsers(page = 1, append = false) {
    usersLoading.value = true;
    usersError.value = null;
    try {
        const params = new URLSearchParams({ page: String(page) });
        if (searchQuery.value) params.set('search', searchQuery.value);
        if (userFilterMode.value === 'blocked') params.set('blocked_only', '1');
        if (userFilterMode.value === 'timed_out') params.set('timed_out_only', '1');

        const res = await fetch(`${chat.moderation.users.url()}?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (append) {
            users.value = [...users.value, ...(data.data ?? [])];
        } else {
            users.value = data.data ?? [];
        }
        usersCurrentPage.value = data.current_page ?? 1;
        usersLastPage.value = data.last_page ?? 1;
    } catch (e: unknown) {
        usersError.value = (e as Error).message;
    } finally {
        usersLoading.value = false;
    }
}

watch([searchQuery, userFilterMode], () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadUsers(1), 300);
});

watch(activeTab, (tab) => {
    if (tab === 'users' && users.value.length === 0) {
        loadUsers(1);
    }
});

onMounted(() => loadUsers(1));

// Timeout dialog
const timeoutDialogOpen = ref(false);
const timeoutDialogSaving = ref(false);
const timeoutTarget = ref<ModerationUser | null>(null);
const timeoutForm = ref({ duration_minutes: 10, reason: '' });

function openTimeoutDialog(user: ModerationUser) {
    timeoutTarget.value = user;
    timeoutForm.value = { duration_minutes: 10, reason: '' };
    timeoutDialogOpen.value = true;
}

async function confirmTimeout() {
    if (!timeoutTarget.value) return;
    timeoutDialogSaving.value = true;
    usersMessage.value = null;
    try {
        const res = await apiFetch(chat.moderation.timeout.url(timeoutTarget.value.id), 'POST', {
            duration_minutes: Number(timeoutForm.value.duration_minutes),
            reason: timeoutForm.value.reason || null,
        });
        const user = users.value.find((u) => u.id === timeoutTarget.value!.id);
        if (user) {
            user.timed_out_until = res.timed_out_until;
            user.timeout_reason = timeoutForm.value.reason || null;
        }
        usersMessage.value = { type: 'success', text: res.message };
        timeoutDialogOpen.value = false;
    } catch (e: unknown) {
        usersMessage.value = { type: 'error', text: (e as Error).message };
    } finally {
        timeoutDialogSaving.value = false;
    }
}

async function relieveTimeout(user: ModerationUser) {
    usersMessage.value = null;
    try {
        const res = await apiFetch(chat.moderation.relieveTimeout.url(user.id), 'DELETE');
        user.timed_out_until = null;
        user.timeout_reason = null;
        usersMessage.value = { type: 'success', text: res.message };
    } catch (e: unknown) {
        usersMessage.value = { type: 'error', text: (e as Error).message };
    }
}

// Block dialog
const blockDialogOpen = ref(false);
const blockDialogSaving = ref(false);
const blockTarget = ref<ModerationUser | null>(null);
const blockForm = ref({ reason: '' });

function openBlockDialog(user: ModerationUser) {
    blockTarget.value = user;
    blockForm.value = { reason: '' };
    blockDialogOpen.value = true;
}

async function confirmBlock() {
    if (!blockTarget.value) return;
    blockDialogSaving.value = true;
    usersMessage.value = null;
    try {
        const res = await apiFetch(chat.moderation.block.url(blockTarget.value.id), 'POST', {
            reason: blockForm.value.reason || null,
        });
        const user = users.value.find((u) => u.id === blockTarget.value!.id);
        if (user) {
            user.is_blocked = true;
            user.block_reason = blockForm.value.reason || null;
        }
        usersMessage.value = { type: 'success', text: res.message };
        blockDialogOpen.value = false;
    } catch (e: unknown) {
        usersMessage.value = { type: 'error', text: (e as Error).message };
    } finally {
        blockDialogSaving.value = false;
    }
}

async function unblockUser(user: ModerationUser) {
    usersMessage.value = null;
    try {
        const res = await apiFetch(chat.moderation.unblock.url(user.id), 'DELETE');
        user.is_blocked = false;
        user.block_reason = null;
        user.blocked_at = null;
        usersMessage.value = { type: 'success', text: res.message };
    } catch (e: unknown) {
        usersMessage.value = { type: 'error', text: (e as Error).message };
    }
}
</script>

<template>
    <Head title="Chat Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Page header -->
            <div>
                <h1 class="text-2xl font-bold">Chat Settings</h1>
                <p class="text-muted-foreground mt-1">Manage users, content filters, and chat behaviour</p>
            </div>

            <!-- Tab navigation -->
            <div class="flex gap-1 border-b pb-0">
                <button
                    v-for="tab in [{ key: 'users', label: 'Users' }, { key: 'filters', label: 'Filter Chains' }, { key: 'slowmode', label: 'Slow Mode' }] as const"
                    :key="tab.key"
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-b-2 border-primary text-foreground'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════ USERS -->
            <div v-if="activeTab === 'users'" class="flex flex-col gap-4">
                <!-- Feedback -->
                <Alert v-if="usersMessage" :variant="usersMessage.type === 'error' ? 'destructive' : 'default'">
                    <CheckCircle2 v-if="usersMessage.type === 'success'" class="h-4 w-4" />
                    <CircleAlert v-else class="h-4 w-4" />
                    <AlertDescription>{{ usersMessage.text }}</AlertDescription>
                </Alert>

                <!-- Search + filter -->
                <div class="flex flex-wrap gap-2">
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by name or email…"
                        class="max-w-xs"
                    />
                    <div class="flex gap-1">
                        <Button
                            v-for="f in [{ key: 'all', label: 'All' }, { key: 'blocked', label: 'Blocked' }, { key: 'timed_out', label: 'Timed Out' }] as const"
                            :key="f.key"
                            size="sm"
                            :variant="userFilterMode === f.key ? 'default' : 'outline'"
                            @click="userFilterMode = f.key"
                        >
                            {{ f.label }}
                        </Button>
                    </div>
                </div>

                <!-- Users table -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="usersLoading && users.length === 0">
                                <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                    <Loader2 class="mx-auto h-5 w-5 animate-spin" />
                                </TableCell>
                            </TableRow>
                            <TableRow v-else-if="usersError">
                                <TableCell colspan="5" class="py-8 text-center text-destructive">{{ usersError }}</TableCell>
                            </TableRow>
                            <TableRow v-else-if="users.length === 0">
                                <TableCell colspan="5" class="py-8 text-center text-muted-foreground">No users found.</TableCell>
                            </TableRow>
                            <TableRow v-for="user in users" :key="user.id">
                                <TableCell class="font-medium">{{ user.name }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ user.email }}</TableCell>
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
                                        <Badge v-if="user.roles.length === 0" variant="outline" class="text-xs">No roles</Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1">
                                        <Badge v-if="user.is_blocked" variant="destructive" class="w-fit text-xs">
                                            <ShieldBan class="mr-1 h-3 w-3" />
                                            Blocked
                                        </Badge>
                                        <Badge v-if="isTimedOut(user)" variant="secondary" class="w-fit text-xs">
                                            <Clock class="mr-1 h-3 w-3" />
                                            Timeout until {{ formatDateTime(user.timed_out_until!) }}
                                        </Badge>
                                        <span v-if="!user.is_blocked && !isTimedOut(user)" class="text-xs text-muted-foreground">—</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div v-if="!isModeratorUser(user)" class="flex flex-wrap justify-end gap-1">
                                        <!-- Timeout actions -->
                                        <Button
                                            v-if="!isTimedOut(user)"
                                            variant="outline"
                                            size="sm"
                                            @click="openTimeoutDialog(user)"
                                        >
                                            <Clock class="mr-1 h-3 w-3" />
                                            Timeout
                                        </Button>
                                        <Button
                                            v-else
                                            variant="outline"
                                            size="sm"
                                            @click="relieveTimeout(user)"
                                        >
                                            <ShieldCheck class="mr-1 h-3 w-3" />
                                            Relieve
                                        </Button>
                                        <!-- Block actions -->
                                        <Button
                                            v-if="!user.is_blocked"
                                            variant="destructive"
                                            size="sm"
                                            @click="openBlockDialog(user)"
                                        >
                                            <ShieldBan class="mr-1 h-3 w-3" />
                                            Block
                                        </Button>
                                        <Button
                                            v-else
                                            variant="outline"
                                            size="sm"
                                            @click="unblockUser(user)"
                                        >
                                            <ShieldCheck class="mr-1 h-3 w-3" />
                                            Unblock
                                        </Button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">Protected</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div v-if="usersCurrentPage < usersLastPage" class="flex justify-center">
                    <Button variant="outline" size="sm" :disabled="usersLoading" @click="loadUsers(usersCurrentPage + 1, true)">
                        <Loader2 v-if="usersLoading" class="mr-1 h-3 w-3 animate-spin" />
                        Load more
                    </Button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════ FILTER CHAINS -->
            <div v-if="activeTab === 'filters'" class="flex flex-col gap-4">
                <!-- Feedback -->
                <Alert v-if="filtersMessage" :variant="filtersMessage.type === 'error' ? 'destructive' : 'default'">
                    <CheckCircle2 v-if="filtersMessage.type === 'success'" class="h-4 w-4" />
                    <CircleAlert v-else class="h-4 w-4" />
                    <AlertDescription>{{ filtersMessage.text }}</AlertDescription>
                </Alert>

                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">{{ filters.length }} filter{{ filters.length === 1 ? '' : 's' }} configured</p>
                    <Button size="sm" @click="openCreateFilter">
                        <Plus class="mr-1 h-4 w-4" />
                        Add Filter
                    </Button>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Pattern</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Active</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filters.length === 0">
                                <TableCell colspan="7" class="py-8 text-center text-muted-foreground">
                                    No filters configured. Add one to start filtering messages.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="filter in filters" :key="filter.id">
                                <TableCell class="font-medium">{{ filter.name }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="text-xs">{{ filter.type }}</Badge>
                                </TableCell>
                                <TableCell class="max-w-[200px]">
                                    <code class="truncate block text-xs bg-muted px-1 py-0.5 rounded font-mono">{{ filter.pattern }}</code>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="filter.action === 'block' ? 'destructive' : filter.action === 'replace' ? 'default' : 'secondary'"
                                        class="text-xs"
                                    >
                                        {{ filter.action }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="filter.is_active ? 'default' : 'outline'" class="text-xs">
                                        {{ filter.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ filter.priority }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" @click="openEditFilter(filter)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="openDeleteFilter(filter)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════ SLOW MODE -->
            <div v-if="activeTab === 'slowmode'" class="flex flex-col gap-4 max-w-lg">
                <!-- Feedback -->
                <Alert v-if="slowModeMessage" :variant="slowModeMessage.type === 'error' ? 'destructive' : 'default'">
                    <CheckCircle2 v-if="slowModeMessage.type === 'success'" class="h-4 w-4" />
                    <CircleAlert v-else class="h-4 w-4" />
                    <AlertDescription>{{ slowModeMessage.text }}</AlertDescription>
                </Alert>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="text-lg font-semibold">Slow Mode</h2>
                    <p class="text-muted-foreground mt-1 text-sm">Limit how frequently users can send messages.</p>

                    <Separator class="my-4" />

                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <Checkbox id="slow-mode-enabled" v-model:checked="slowModeEnabled" />
                            <Label for="slow-mode-enabled" class="cursor-pointer">Enable slow mode</Label>
                        </div>

                        <div v-if="slowModeEnabled" class="flex flex-col gap-2">
                            <Label for="slow-mode-seconds">Delay between messages (seconds)</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    id="slow-mode-seconds"
                                    v-model="slowModeSeconds"
                                    type="number"
                                    min="1"
                                    max="300"
                                    class="w-32"
                                />
                                <span class="text-sm text-muted-foreground">1 – 300 seconds</span>
                            </div>
                        </div>

                        <Button :disabled="slowModeSaving" class="w-fit" @click="saveSlowMode">
                            <Loader2 v-if="slowModeSaving" class="mr-1 h-4 w-4 animate-spin" />
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- ════════════════════════════════════════════════ TIMEOUT DIALOG -->
    <Dialog v-model:open="timeoutDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Timeout User</DialogTitle>
                <DialogDescription>
                    Set a temporary timeout for <strong>{{ timeoutTarget?.name }}</strong>. They will not be able to send messages until the timeout expires.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <Label for="timeout-duration">Duration (minutes)</Label>
                    <Input
                        id="timeout-duration"
                        v-model="timeoutForm.duration_minutes"
                        type="number"
                        min="1"
                        max="10080"
                        placeholder="e.g. 30"
                    />
                    <p class="text-xs text-muted-foreground">Max 10 080 min (7 days)</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="timeout-reason">Reason <span class="text-muted-foreground">(optional)</span></Label>
                    <Input
                        id="timeout-reason"
                        v-model="timeoutForm.reason"
                        placeholder="Reason for timeout…"
                        maxlength="500"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="timeoutDialogOpen = false">Cancel</Button>
                <Button :disabled="timeoutDialogSaving" @click="confirmTimeout">
                    <Loader2 v-if="timeoutDialogSaving" class="mr-1 h-4 w-4 animate-spin" />
                    Apply Timeout
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ════════════════════════════════════════════════ BLOCK DIALOG -->
    <Dialog v-model:open="blockDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Block User</DialogTitle>
                <DialogDescription>
                    Permanently block <strong>{{ blockTarget?.name }}</strong> from using LanShout. You can unblock them later.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <Label for="block-reason">Reason <span class="text-muted-foreground">(optional)</span></Label>
                    <Input
                        id="block-reason"
                        v-model="blockForm.reason"
                        placeholder="Reason for blocking…"
                        maxlength="500"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="blockDialogOpen = false">Cancel</Button>
                <Button variant="destructive" :disabled="blockDialogSaving" @click="confirmBlock">
                    <Loader2 v-if="blockDialogSaving" class="mr-1 h-4 w-4 animate-spin" />
                    Block User
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ══════════════════════════════════════ CREATE / EDIT FILTER DIALOG -->
    <Dialog v-model:open="filterDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ filterDialogMode === 'create' ? 'Add Filter' : 'Edit Filter' }}</DialogTitle>
                <DialogDescription>
                    {{ filterDialogMode === 'create' ? 'Configure a new content filter chain.' : 'Update the filter chain settings.' }}
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <Label for="filter-name">Name</Label>
                    <Input id="filter-name" v-model="filterForm.name" placeholder="e.g. Profanity filter" maxlength="255" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="filter-type">Match Type</Label>
                        <select
                            id="filter-type"
                            v-model="filterForm.type"
                            class="border-input dark:bg-input/30 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="contains">Contains</option>
                            <option value="exact">Exact</option>
                            <option value="regex">Regex</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="filter-action">Action</Label>
                        <select
                            id="filter-action"
                            v-model="filterForm.action"
                            class="border-input dark:bg-input/30 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="block">Block</option>
                            <option value="replace">Replace</option>
                            <option value="warn">Warn</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="filter-pattern">Pattern</Label>
                    <Textarea
                        id="filter-pattern"
                        v-model="filterForm.pattern"
                        placeholder="Enter the pattern to match…"
                        class="min-h-16 resize-none font-mono text-sm"
                    />
                </div>

                <div v-if="filterForm.action === 'replace'" class="flex flex-col gap-2">
                    <Label for="filter-replacement">Replacement text</Label>
                    <Input id="filter-replacement" v-model="filterForm.replacement" placeholder="Leave empty for ***" maxlength="500" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="filter-priority">Priority</Label>
                        <Input id="filter-priority" v-model="filterForm.priority" type="number" min="0" max="1000" />
                        <p class="text-xs text-muted-foreground">Lower = runs first</p>
                    </div>
                    <div class="flex items-center gap-3 pt-6">
                        <Checkbox id="filter-active" v-model:checked="filterForm.is_active" />
                        <Label for="filter-active" class="cursor-pointer">Active</Label>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="filterDialogOpen = false">Cancel</Button>
                <Button :disabled="filterDialogSaving" @click="saveFilter">
                    <Loader2 v-if="filterDialogSaving" class="mr-1 h-4 w-4 animate-spin" />
                    {{ filterDialogMode === 'create' ? 'Create Filter' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ══════════════════════════════════════════ DELETE FILTER DIALOG -->
    <Dialog v-model:open="deleteFilterDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete Filter</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ deletingFilter?.name }}</strong>? This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteFilterDialogOpen = false">Cancel</Button>
                <Button variant="destructive" :disabled="deleteFilterSaving" @click="confirmDeleteFilter">
                    <Loader2 v-if="deleteFilterSaving" class="mr-1 h-4 w-4 animate-spin" />
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
