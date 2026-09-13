<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutGrid, MapPinned, MessageSquareText, UsersRound } from '@lucide/vue';
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
import { dashboard } from '@/routes';
import { index as branchesIndex } from '@/routes/cms/branches';
import { index as messageTemplatesIndex } from '@/routes/cms/message-templates';
import { index as organizationsIndex } from '@/routes/cms/organizations';
import { index as usersIndex } from '@/routes/cms/users';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed(() => page.props.auth.user.roles);
const canManageTenant = computed(
    () => roles.value.includes('superadmin') || roles.value.includes('owner'),
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

    if (canManageTenant.value) {
        items.push(
            {
                title: 'Kota & cabang',
                href: branchesIndex(),
                icon: MapPinned,
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
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Operasional" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
