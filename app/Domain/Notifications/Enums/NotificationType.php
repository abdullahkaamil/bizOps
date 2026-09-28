<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

/**
 * Every notification the platform can emit. Modules dispatch these types through
 * the shared notification foundation rather than building their own mail plumbing.
 */
enum NotificationType: string
{
    case UserInvited = 'user_invited';
    case TaskAssigned = 'task_assigned';
    case TaskDueSoon = 'task_due_soon';
    // Communication-focused board engine: broad board-wide events plus a targeted
    // "your action is needed" alert when a card enters a step under your authority.
    case TaskCreated = 'task_created';
    case TaskCommented = 'task_commented';
    case TaskMoved = 'task_moved';
    case TaskActionNeeded = 'task_action_needed';
    case JobAssigned = 'job_assigned';
    case WorkshopCompleted = 'workshop_completed';
    case WorkshopDelivered = 'workshop_delivered';
    case QuotationSent = 'quotation_sent';
    case QuotationDecided = 'quotation_decided';

    public function label(): string
    {
        return match ($this) {
            self::UserInvited => 'User invited',
            self::TaskAssigned => 'Task assigned',
            self::TaskDueSoon => 'Task due soon',
            self::TaskCreated => 'Task created',
            self::TaskCommented => 'Task commented',
            self::TaskMoved => 'Task moved',
            self::TaskActionNeeded => 'Task action needed',
            self::JobAssigned => 'Job assigned',
            self::WorkshopCompleted => 'Workshop completed',
            self::WorkshopDelivered => 'Workshop delivered',
            self::QuotationSent => 'Quotation sent',
            self::QuotationDecided => 'Quotation decided',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
