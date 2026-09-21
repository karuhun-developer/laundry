<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    LayoutGrid,
    MapPinned,
    MessageSquareText,
    PackageOpen,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/cms';
import { index as branchesIndex } from '@/routes/cms/branches';
import { index as messageTemplatesIndex } from '@/routes/cms/message-templates';
import { index as ordersIndex } from '@/routes/cms/orders';
import { index as organizationsIndex } from '@/routes/cms/organizations';
import { index as servicesIndex } from '@/routes/cms/services';
import { index as usersIndex } from '@/routes/cms/users';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed(() => page.props.auth.user.roles);
const canManageTenant = computed(
    () => roles.value.includes('superadmin') || roles.value.includes('owner'),
);
const canManageOrders = computed(
    () =>
        roles.value.includes('superadmin') ||
        roles.value.includes('owner') ||
        roles.value.includes('branch_admin'),
);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (roles.value.includes('superadmin')) {
        items.push({
            title: 'Organisasi',
            href: organizationsIndex(),
            icon: Building2,
        });
    }

    if (canManageOrders.value) {
        items.push({
            title: 'Antrean pesanan',
            href: ordersIndex(),
            icon: ClipboardList,
        });
    }

    if (canManageTenant.value) {
        items.push(
            {
                title: 'Kota & cabang',
                href: branchesIndex(),
                icon: MapPinned,
            },
            {
                title: 'Layanan & paket',
                href: servicesIndex(),
                icon: PackageOpen,
            },
            {
                title: 'Pengguna & akses',
                href: usersIndex(),
                icon: UsersRound,
            },
            {
                title: 'Template WhatsApp',
                href: messageTemplatesIndex(),
                icon: MessageSquareText,
            },
        );
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r-0">
        <SidebarHeader class="border-b border-sidebar-border px-3 py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-12 hover:bg-white/8">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-1 py-5">
            <NavMain :items="mainNavItems" label="Operasional" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
