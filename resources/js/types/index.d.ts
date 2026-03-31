import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface LanCoreProps {
    enabled: boolean;
    sso_url: string | null;
    base_url: string | null;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    lancore: LanCoreProps;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    chat_color: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    is_lancore_user: boolean;
}

export type BreadcrumbItemType = BreadcrumbItem;
