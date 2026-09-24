<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-local roles seeded into every tenant database.
 */
enum Role: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Developer = 'developer';
    case Technician = 'technician';
    case Sales = 'sales';
    case CustomerRepresentative = 'customer_representative';

    /**
     * Whether this role is for an internal employee or an external customer rep.
     */
    public function userType(): UserType
    {
        return $this === self::CustomerRepresentative ? UserType::External : UserType::Internal;
    }

    /**
     * Permissions granted to this role.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner, self::Administrator => Permission::cases(),
            self::Manager => [
                Permission::ViewUsers,
                Permission::ViewActivity,
                Permission::ViewCustomers, Permission::CreateCustomers, Permission::UpdateCustomers, Permission::DeleteCustomers,
                Permission::ViewTasks, Permission::CreateTasks, Permission::UpdateTasks, Permission::AssignTasks,
                Permission::StartTasks, Permission::SubmitTaskReview, Permission::ApproveTasks, Permission::RejectTasks,
                Permission::ViewJobs, Permission::CreateJobs, Permission::AssignJobs, Permission::StartJobs, Permission::CompleteJobs, Permission::CancelJobs,
                Permission::ViewWorkshop, Permission::CreateWorkshop, Permission::UpdateWorkshop, Permission::CompleteWorkshop, Permission::DeliverWorkshop,
                Permission::ViewInventory, Permission::ManageInventory, Permission::ViewInventoryCost,
                Permission::ViewQuotations, Permission::CreateQuotations, Permission::UpdateQuotations, Permission::SendQuotations, Permission::AcceptQuotations,
                Permission::ViewReports, Permission::ViewSettings,
            ],
            self::Developer => [
                Permission::ViewCustomers,
                Permission::ViewTasks, Permission::CreateTasks, Permission::UpdateTasks, Permission::AssignTasks,
                Permission::StartTasks, Permission::SubmitTaskReview,
                Permission::ViewJobs,
                Permission::ViewReports,
            ],
            self::Technician => [
                Permission::ViewCustomers,
                // Reaches internal boards they are assigned to (permission matrix:
                // "Internal boards" = Yes for technicians). Task write actions stay
                // optional and are not granted by default.
                Permission::ViewTasks,
                Permission::ViewJobs, Permission::StartJobs, Permission::CompleteJobs,
                Permission::ViewWorkshop, Permission::CreateWorkshop, Permission::UpdateWorkshop, Permission::CompleteWorkshop, Permission::DeliverWorkshop,
                Permission::ViewInventory,
            ],
            self::Sales => [
                Permission::ViewCustomers, Permission::CreateCustomers, Permission::UpdateCustomers,
                Permission::ViewQuotations, Permission::CreateQuotations, Permission::UpdateQuotations, Permission::SendQuotations, Permission::AcceptQuotations,
                Permission::ViewTasks,
                // Sales need to see stock/products to build quotations (permission
                // matrix: "Inventory view" = Yes for sales). Cost stays hidden.
                Permission::ViewInventory,
                Permission::ViewReports,
            ],
            // External customer representatives: board-scoped review actions only.
            self::CustomerRepresentative => [
                Permission::ViewTasks, Permission::ApproveTasks, Permission::RejectTasks,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(static fn (Permission $permission): string => $permission->value, $this->permissions());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
