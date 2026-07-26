import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Tenant-local authorization helpers for UI presentation only. These mirror the
 * permissions the server shares on every Inertia response. All real
 * authorization is enforced server-side through Laravel policies and gates.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => page.props.auth.permissions ?? [],
    );
    const roles = computed<string[]>(() => page.props.auth.roles ?? []);
    const isCentralAdmin = computed<boolean>(
        () => page.props.auth.isCentralAdmin ?? false,
    );

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);
    const hasRole = (role: string): boolean => roles.value.includes(role);

    return { permissions, roles, isCentralAdmin, can, hasRole };
}
