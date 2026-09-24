<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Building2,
    Contact,
    FolderGit2,
    HardDrive,
    FileText,
    LayoutGrid,
    Megaphone,
    Package,
    Settings,
    ShieldCheck,
    Truck,
    Users,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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
import { useAuthorization } from '@/composables/useAuthorization';
import { dashboard, search as searchRoute } from '@/routes';
import { index as announcementsIndex } from '@/routes/admin/announcements';
import { index as tenantsIndex } from '@/routes/admin/tenants';
import { index as boardsIndex } from '@/routes/tenant/boards';
import { index as customersIndex } from '@/routes/tenant/customers';
import { index as inventoryIndex } from '@/routes/tenant/inventory';
import { index as jobsIndex } from '@/routes/tenant/jobs';
import { index as quotationsIndex } from '@/routes/tenant/quotations';
import { index as rolesIndex } from '@/routes/tenant/roles';
import { company as tenantSettings } from '@/routes/tenant/settings';
import { index as suppliersIndex } from '@/routes/tenant/suppliers';
import { index as tenantUsersIndex } from '@/routes/tenant/users';
import { index as workshopIndex } from '@/routes/tenant/workshop';
import type { NavItem } from '@/types';

const { isCentralAdmin, can } = useAuthorization();
const { t } = useI18n();

// Navigation adapts to the actor: central admins see SaaS admin items;
// internal tenant users see the modules they may access; external users use
// the ExternalUserLayout (no internal sidebar) so nothing internal is shown.
const mainNavItems = computed<NavItem[]>(() => {
    if (isCentralAdmin.value) {
        return [
            { title: t('nav.tenants'), href: tenantsIndex(), icon: Building2 },
            { title: t('nav.announcements'), href: announcementsIndex(), icon: Megaphone },
        ];
    }

    const items: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
    ];

    if (can('customers.view')) {
        items.push({
            title: t('nav.customers'),
            href: customersIndex(),
            icon: Contact,
        });
    }

    if (can('tasks.view')) {
        items.push({
            title: t('nav.boards'),
            href: boardsIndex(),
            icon: FolderGit2,
        });
    }

    if (can('jobs.view')) {
        items.push({ title: t('nav.jobs'), href: jobsIndex(), icon: Wrench });
    }

    if (can('workshop.view')) {
        items.push({ title: t('nav.workshop'), href: workshopIndex(), icon: HardDrive });
    }

    if (can('inventory.view')) {
        items.push({ title: t('nav.inventory'), href: inventoryIndex(), icon: Package });
        items.push({ title: t('nav.suppliers'), href: suppliersIndex(), icon: Truck });
    }

    if (can('quotations.view')) {
        items.push({ title: t('nav.quotations'), href: quotationsIndex(), icon: FileText });
    }

    if (can('users.view')) {
        items.push({ title: t('nav.team'), href: tenantUsersIndex(), icon: Users });
    }

    if (can('roles.manage')) {
        items.push({ title: t('nav.roles'), href: rolesIndex(), icon: ShieldCheck });
    }

    if (can('settings.view')) {
        items.push({
            title: t('settings.company_title'),
            href: tenantSettings(),
            icon: Settings,
        });
    }

    return items;
});

const searchTerm = ref('');
function goSearch() {
    if (!searchTerm.value.trim()) {
return;
}

    router.get(searchRoute().url, { q: searchTerm.value.trim() });
    searchTerm.value = '';
}

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
            <input
                v-if="!isCentralAdmin"
                v-model="searchTerm"
                type="search"
                :placeholder="t('nav.search') + '…'"
                class="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm group-data-[collapsible=icon]:hidden"
                @keyup.enter="goSearch"
            />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
