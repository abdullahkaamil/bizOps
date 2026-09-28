<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Documents\NextDocumentNumberService;
use App\Domain\Settings\TenantSettings;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Domain\Workshop\Models\WorkshopStatusHistory;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Intake: resolve (or create) the device, then open a repair ticket. Devices are
 * never silently reassigned — a serial that belongs to another customer is
 * rejected rather than moved.
 */
class CreateWorkshopTicketAction
{
    public function __construct(
        private NextDocumentNumberService $numbers,
        private TenantSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  customer_id, device_id?, serial_number?,
     *                                      serial_number_unavailable?, brand?, model?,
     *                                      issue_description, assigned_user_id?, job_id?
     */
    public function handle(array $data, User $creator): WorkshopTicket
    {
        return DB::transaction(function () use ($data, $creator): WorkshopTicket {
            $device = $this->resolveDevice($data);

            $ticket = WorkshopTicket::create([
                'number' => $this->numbers->next('workshop', $this->settings->workshopNumberPrefix()),
                'device_id' => $device->id,
                'job_id' => $data['job_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'issue_description' => $data['issue_description'],
                'status' => WorkshopStatus::InProgress,
                'received_at' => now(),
                'created_by' => $creator->id,
            ]);

            WorkshopStatusHistory::create([
                'workshop_ticket_id' => $ticket->id,
                'from_status' => null,
                'to_status' => WorkshopStatus::InProgress,
                'actor_id' => $creator->id,
                'created_at' => now(),
            ]);

            activity('workshop')->performedOn($ticket)->causedBy($creator)->event('created')->log('workshop.created');

            return $ticket->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDevice(array $data): CustomerDevice
    {
        $customerId = (int) $data['customer_id'];

        // An explicitly chosen existing device — must belong to this customer.
        if (! empty($data['device_id'])) {
            $device = CustomerDevice::findOrFail((int) $data['device_id']);
            $this->assertSameCustomer($device, $customerId);

            return $device;
        }

        $serial = ($data['serial_number_unavailable'] ?? false)
            ? null
            : (($data['serial_number'] ?? '') !== '' ? $data['serial_number'] : null);

        // A real serial: reuse the matching device, or create a new one. Never
        // reassign a device that belongs to a different customer.
        if ($serial !== null) {
            $existing = CustomerDevice::where('serial_number', $serial)->first();

            if ($existing !== null) {
                $this->assertSameCustomer($existing, $customerId);

                return $existing;
            }

            return CustomerDevice::create([
                'customer_id' => $customerId,
                'serial_number' => $serial,
                'serial_number_unavailable' => false,
                'brand' => $data['brand'],
                'model' => $data['model'],
            ]);
        }

        // No serial (unavailable) — always a new device with a null serial.
        return CustomerDevice::create([
            'customer_id' => $customerId,
            'serial_number' => null,
            'serial_number_unavailable' => true,
            'brand' => $data['brand'],
            'model' => $data['model'],
        ]);
    }

    private function assertSameCustomer(CustomerDevice $device, int $customerId): void
    {
        if ($device->customer_id !== $customerId) {
            throw ValidationException::withMessages([
                'serial_number' => __('This serial number is registered to a different customer.'),
            ]);
        }
    }
}
