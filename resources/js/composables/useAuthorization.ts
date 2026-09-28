import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Tenant authorization helpers for UI presentation only. These mirror the
 * permissions/roles/type the server shares on every Inertia response. All real
 * authorization is enforced server-side through Laravel policies and gates.
 */
export function useAuthorization() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => page.props.auth.permissions ?? [],
    );
    const roles = computed<string[]>(() => page.props.auth.roles ?? []);
    const userType = computed(() => page.props.auth.userType);

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);
    const canAny = (list: string[]): boolean =>
        list.some((permission) => permissions.value.includes(permission));
    const canAll = (list: string[]): boolean =>
        list.every((permission) => permissions.value.includes(permission));
    const hasRole = (role: string): boolean => roles.value.includes(role);

    const isExternal = computed<boolean>(() => userType.value === 'external');
    const isInternal = computed<boolean>(() => userType.value === 'internal');
    const isCentralAdmin = computed<boolean>(
        () => page.props.auth.isCentralAdmin ?? false,
    );

    return {
        permissions,
        roles,
        userType,
        can,
        canAny,
        canAll,
        hasRole,
        isExternal,
        isInternal,
        isCentralAdmin,
    };
}
