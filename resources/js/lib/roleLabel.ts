import { i18n } from '@/i18n';

/**
 * Human-readable label for a role name. Built-in roles have translations under
 * the `roles.*` catalog namespace; custom roles have none, so their machine name
 * (e.g. `field_supervisor`) is humanized to "Field Supervisor".
 */
export function roleLabel(name: string): string {
    const key = `roles.${name}`;

    if (i18n.global.te(key)) {
        return i18n.global.t(key);
    }

    return name
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
