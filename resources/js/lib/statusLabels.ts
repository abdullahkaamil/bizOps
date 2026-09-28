export type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

/**
 * Central mapping of status values to a badge style + an i18n key, so status
 * labels are defined once (UI convention: mapped centrally, never color alone —
 * the label text always accompanies the color). The label text itself lives in
 * the i18n catalogs under `status.*`; `StatusBadge` resolves it with `t()`.
 */
const STATUS_VARIANTS: Record<string, BadgeVariant> = {
    // Tenant lifecycle
    provisioning: 'outline',
    active: 'secondary',
    suspended: 'destructive',
    failed: 'destructive',
    archived: 'outline',
    deletion_pending: 'outline',
    deleted: 'destructive',
    // User status
    invited: 'outline',
    // Task
    todo: 'outline',
    review: 'secondary',
    // Job / workshop / quotation shared
    in_progress: 'secondary',
    completed: 'default',
    pending: 'outline',
    canceled: 'destructive',
    delivered: 'default',
    draft: 'outline',
    sent: 'secondary',
    accepted: 'default',
    rejected: 'destructive',
    expired: 'destructive',
};

/**
 * Data-viz palette colour per operational status (jobs / workshop / tasks /
 * quotations), matching the dashboard charts and board columns. Lifecycle
 * statuses (tenant/user active/suspended/…) are intentionally absent and keep the
 * plain shadcn variant. The colour is carried by a dot + tint; the label text
 * always stays in the foreground ink, so a status is never colour-alone.
 */
const STATUS_COLORS: Record<string, string> = {
    todo: 'yellow',
    pending: 'yellow',
    draft: 'yellow',
    in_progress: 'blue',
    sent: 'blue',
    review: 'violet',
    expired: 'violet',
    completed: 'green',
    accepted: 'green',
    delivered: 'green',
    canceled: 'red',
    rejected: 'red',
};

export function statusMeta(status: string): { key: string; variant: BadgeVariant; color?: string } {
    return {
        key: `status.${status}`,
        variant: STATUS_VARIANTS[status] ?? 'outline',
        color: STATUS_COLORS[status],
    };
}
