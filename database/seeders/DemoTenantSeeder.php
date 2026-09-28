<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Quotations\Actions\CreateQuotationAction;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Tasks\Enums\BoardType;
use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Enums\Priority;
use App\Domain\Tasks\Models\Board;
use App\Domain\Workshop\Actions\CreateWorkshopTicketAction;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Rich Turkish demo data for a single tenant — run inside tenant context
 * (e.g. `$tenant->run(fn () => app(DemoTenantSeeder::class)->run())`). Populates
 * every list module with 15–20+ believable records for a live walkthrough.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // Guard against double-seeding a tenant.
        if (Customer::count() >= 15) {
            return;
        }

        $faker = fake('tr_TR');
        $owner = User::where('user_type', 'internal')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->firstOrFail();

        $departments = $this->departments();
        $team = $this->users($faker, $departments);           // internal staff
        $customers = $this->customers($faker, $departments);   // 20 companies (+contacts/addresses/devices)
        $this->externalReps($faker, $customers);               // customer representatives
        [$warehouse, $items] = $this->inventory($faker, $owner); // suppliers + items + stock
        $this->boards($faker, $team, $customers);              // project + internal boards, tasks, comments
        $this->jobs($faker, $team, $customers, $owner);        // field-service jobs
        $this->workshop($faker, $team, $customers, $owner);    // repair tickets
        $this->quotations($faker, $customers, $items, $owner); // quotations with lines
    }

    /** @return array<int, Department> */
    private function departments(): array
    {
        $names = ['Yazılım', 'Saha Servisi', 'Atölye', 'Satış', 'Muhasebe', 'İnsan Kaynakları'];

        return array_map(
            fn (string $name, int $i): Department => Department::create(['name' => $name, 'code' => 'DEP'.($i + 1), 'is_active' => true]),
            $names,
            array_keys($names),
        );
    }

    /**
     * @param  array<int, Department>  $departments
     * @return array<string, Collection<int, User>>
     */
    private function users(Generator $faker, array $departments): array
    {
        $all = collect();
        $groups = ['developers' => collect(), 'technicians' => collect(), 'sales' => collect()];
        $plan = [
            [Role::Manager, 2, null], [Role::Developer, 5, 'developers'],
            [Role::Technician, 5, 'technicians'], [Role::Sales, 3, 'sales'],
        ];

        foreach ($plan as [$role, $count, $group]) {
            for ($i = 0; $i < $count; $i++) {
                $user = $this->makeUser($faker, [
                    'user_type' => 'internal',
                    'department_id' => $faker->randomElement($departments)->id,
                ], $role);
                $all->push($user);
                if ($group !== null) {
                    $groups[$group]->push($user);
                }
            }
        }

        return ['all' => $all, ...$groups];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(Generator $faker, array $attributes, Role $role): User
    {
        $user = User::create([
            'name' => $faker->name(),
            'email' => $faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'status' => 'active',
            ...$attributes,
        ]);
        // email_verified_at is not mass-assignable — set it so the account can log in.
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @param  array<int, Department>  $departments
     * @return Collection<int, Customer>
     */
    private function customers(Generator $faker, array $departments): Collection
    {
        $suffixes = ['A.Ş.', 'Ltd. Şti.', 'San. ve Tic.', 'Holding', 'Mühendislik'];
        $sectors = ['İnşaat', 'Tekstil', 'Makine', 'Lojistik', 'Gıda', 'Yazılım', 'Enerji', 'Otomotiv', 'Mobilya', 'Turizm', 'Tarım', 'Seramik', 'Elektrik', 'Kozmetik', 'Reklam'];
        $customers = collect();

        for ($i = 0; $i < 20; $i++) {
            $company = $faker->unique()->lastName().' '.$faker->randomElement($sectors).' '.$faker->randomElement($suffixes);

            $customer = Customer::create([
                'company_name' => $company,
                'email' => $faker->companyEmail(),
                'phone' => $faker->phoneNumber(),
                'status' => $faker->boolean(85) ? 'active' : 'inactive',
                'tax_number' => (string) $faker->numerify('##########'),
                'notes' => $faker->boolean(40) ? $faker->sentence() : null,
            ]);

            foreach (range(1, $faker->numberBetween(1, 2)) as $c) {
                CustomerContact::create([
                    'customer_id' => $customer->id,
                    'first_name' => $faker->firstName(),
                    'last_name' => $faker->lastName(),
                    'email' => $faker->safeEmail(),
                    'phone' => $faker->phoneNumber(),
                    'job_title' => $faker->randomElement(['Genel Müdür', 'Satın Alma Uzmanı', 'Muhasebe Müdürü', 'Teknik Sorumlu', 'Proje Yöneticisi']),
                    'is_primary' => $c === 1,
                ]);
            }

            CustomerAddress::create([
                'customer_id' => $customer->id,
                'type' => 'service',
                'label' => 'Merkez',
                'address_line_1' => $faker->streetAddress(),
                'city' => $faker->city(),
                'postal_code' => (string) $faker->numerify('#####'),
                'country_code' => 'TR',
                'is_primary' => true,
            ]);

            $customers->push($customer);
        }

        return $customers;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    private function externalReps(Generator $faker, Collection $customers): void
    {
        foreach ($customers->take(6) as $customer) {
            $this->makeUser($faker, [
                'user_type' => 'external',
                'customer_id' => $customer->id,
            ], Role::CustomerRepresentative);
        }
    }

    /**
     * @return array{0: Warehouse, 1: Collection<int, InventoryItem>}
     */
    private function inventory(Generator $faker, User $owner): array
    {
        $warehouse = Warehouse::create(['name' => 'Ana Depo', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        foreach (range(1, 18) as $i) {
            Supplier::create([
                'name' => $faker->unique()->company(),
                'contact_name' => $faker->name(),
                'email' => $faker->companyEmail(),
                'phone' => $faker->phoneNumber(),
                'address' => $faker->address(),
            ]);
        }

        $products = [
            'Rulman 6203', 'V-Kayış 40', 'Hidrolik Pompa', 'Elektrik Motoru 2kW', 'PNP Sensör',
            'Kontaktör 25A', 'Termik Röle', 'Küresel Vana DN50', 'Conta Seti', 'Yağ Filtresi',
            'Kablo 2.5mm²', 'Otomatik Sigorta 16A', 'Fan Motoru', 'Redüktör 1:20', 'Şaft Keçesi',
            'Cıvata M8 (100 adet)', 'Boya Spreyi Gri', 'Silikon Kartuşu', 'Matkap Ucu Seti', 'İş Eldiveni (10 çift)',
        ];
        $items = collect();

        foreach ($products as $i => $name) {
            $item = InventoryItem::create([
                'sku' => sprintf('STK-%03d', $i + 1),
                'name' => $name,
                'description' => $faker->boolean(50) ? $faker->sentence() : null,
                'unit' => 'adet',
                'status' => 'active',
                'current_sale_price' => $faker->randomFloat(2, 25, 4500),
            ]);

            app(AdjustStockAction::class)->handle(
                $item, $warehouse, MovementType::Opening,
                (float) $faker->numberBetween(10, 250), $owner,
                (string) $faker->randomFloat(2, 15, 3200), 'Açılış stoğu',
            );

            $items->push($item);
        }

        return [$warehouse, $items];
    }

    /**
     * @param  array<string, Collection<int, User>>  $team
     * @param  Collection<int, Customer>  $customers
     */
    private function boards(Generator $faker, array $team, Collection $customers): void
    {
        $members = $team['all'];

        $boards = [
            ['name' => 'Mobil Uygulama Projesi', 'type' => BoardType::Project, 'customer' => $customers->first()],
            ['name' => 'Web Yenileme', 'type' => BoardType::Project, 'customer' => $customers->get(1)],
            ['name' => 'İç Operasyonlar', 'type' => BoardType::Internal, 'customer' => null],
        ];

        $titles = [
            'Giriş ekranı hatası', 'Ödeme entegrasyonu', 'Bildirim servisi', 'Performans iyileştirmesi',
            'Raporlama modülü', 'Kullanıcı yetkileri', 'API dokümantasyonu', 'Mobil uyumluluk',
            'Veri yedekleme', 'E-posta şablonları', 'Arama filtresi', 'Sepet hesaplaması',
            'Güvenlik denetimi', 'Dil desteği', 'Tema düzenlemesi', 'Test otomasyonu',
        ];

        foreach ($boards as $b) {
            $board = Board::create([
                'name' => $b['name'], 'type' => $b['type']->value,
                'customer_id' => $b['customer']?->id, 'is_active' => true,
            ]);

            $board->members()->syncWithoutDetaching($members->random(min(4, $members->count()))->pluck('id')->all());

            // On a project board, add the customer's own representative so a
            // "log in as customer" demo shows a real shared board.
            if ($b['customer'] !== null) {
                $rep = User::where('user_type', 'external')->where('customer_id', $b['customer']->id)->first();
                if ($rep !== null) {
                    $board->members()->syncWithoutDetaching([$rep->id]);
                }
            }

            $memberIds = $board->members()->pluck('users.id');
            $columns = $board->columns()->orderBy('position')->get();

            foreach ($faker->randomElements($titles, 8) as $title) {
                $column = $columns->random();
                $task = $board->tasks()->create([
                    'title' => $title,
                    'description' => $faker->sentence(10),
                    'status' => $column->category,
                    'board_column_id' => $column->id,
                    'priority' => $faker->randomElement(Priority::cases()),
                    'created_by' => $members->random()->id,
                    'position' => $faker->numberBetween(0, 20),
                ]);

                if ($faker->boolean(70) && $memberIds->isNotEmpty()) {
                    $task->assignees()->syncWithoutDetaching($memberIds->random(min(2, $memberIds->count())));
                }

                foreach (range(0, $faker->numberBetween(0, 3)) as $c) {
                    if ($c === 0) {
                        continue;
                    }
                    $task->comments()->create([
                        'user_id' => $memberIds->isNotEmpty() ? $memberIds->random() : $members->random()->id,
                        'body' => $faker->sentence($faker->numberBetween(6, 14)),
                        'visibility' => $faker->boolean(60) ? CommentVisibility::Internal : CommentVisibility::Customer,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, Collection<int, User>>  $team
     * @param  Collection<int, Customer>  $customers
     */
    private function jobs(Generator $faker, array $team, Collection $customers, User $owner): void
    {
        $titles = ['Klima bakımı', 'Jeneratör arızası', 'Elektrik panosu kontrolü', 'Pompa değişimi', 'Periyodik bakım', 'Montaj işi', 'Kaçak tespiti', 'Kalibrasyon'];
        $techs = $team['technicians'];

        // Spread creation across the last 6 months (weighted toward recent) so the
        // "jobs opened per month" chart has a real trend, not one tall bar.
        $monthWeights = [5, 5, 4, 4, 3, 3, 2, 1, 1, 0, 0]; // pick → months-ago

        foreach (range(1, 20) as $i) {
            $created = now()->subMonths($faker->randomElement($monthWeights))
                ->subDays($faker->numberBetween(0, 25))->setTime($faker->numberBetween(8, 17), 0);

            $tech = $techs->isNotEmpty() ? $techs->random() : $owner;
            $job = app(CreateJobAction::class)->handle([
                'customer_id' => $customers->random()->id,
                'title' => $faker->randomElement($titles).' — '.$faker->word(),
                'description' => $faker->sentence(),
                'assigned_user_id' => $tech->id,
                'planned_at' => (clone $created)->addDays($faker->numberBetween(0, 7)),
            ], $owner);

            // Spread across the lifecycle, with actual times anchored to creation.
            $roll = $faker->numberBetween(1, 10);
            if ($roll <= 3) {
                $job->update(['status' => JobStatus::InProgress, 'actual_start_at' => (clone $created)->addHours($faker->numberBetween(1, 8))]);
            } elseif ($roll <= 6) {
                $start = (clone $created)->addDays($faker->numberBetween(0, 3));
                $job->update([
                    'status' => JobStatus::Completed,
                    'actual_start_at' => $start,
                    'actual_end_at' => (clone $start)->addHours($faker->numberBetween(1, 6)),
                    'service_notes' => $faker->sentence(),
                    'completed_by' => $tech->id,
                ]);
            } elseif ($roll === 7) {
                $job->update(['status' => JobStatus::Canceled, 'canceled_by' => $owner->id]);
            }

            // Backdate the creation timestamp itself (the chart groups by it).
            $job->created_at = $created;
            $job->saveQuietly();
        }
    }

    /**
     * @param  array<string, Collection<int, User>>  $team
     * @param  Collection<int, Customer>  $customers
     */
    private function workshop(Generator $faker, array $team, Collection $customers, User $owner): void
    {
        $brands = ['Bosch', 'Siemens', 'Arçelik', 'Vestel', 'Makita', 'DeWalt', 'Karcher', 'Hilti'];
        $issues = ['Cihaz açılmıyor', 'Aşırı ısınma', 'Ses geliyor', 'Şarj tutmuyor', 'Ekran arızası', 'Motor dönmüyor', 'Su kaçırıyor', 'Titreşim var'];
        $techs = $team['technicians'];

        foreach (range(1, 18) as $i) {
            $customer = $customers->random();
            $device = CustomerDevice::create([
                'customer_id' => $customer->id,
                'brand' => $faker->randomElement($brands),
                'model' => strtoupper($faker->bothify('??-###')),
                'serial_number' => $faker->boolean(80) ? strtoupper($faker->bothify('SN########')) : null,
                'serial_number_unavailable' => false,
            ]);

            $ticket = app(CreateWorkshopTicketAction::class)->handle([
                'customer_id' => $customer->id,
                'device_id' => $device->id,
                'issue_description' => $faker->randomElement($issues).'. '.$faker->sentence(),
                'assigned_user_id' => $techs->isNotEmpty() ? $techs->random()->id : $owner->id,
            ], $owner);

            $roll = $faker->numberBetween(1, 10);
            if ($roll <= 4) {
                $ticket->update(['status' => WorkshopStatus::Completed, 'completed_at' => now()->subDays($faker->numberBetween(1, 5)), 'repair_notes' => $faker->sentence(), 'completed_by' => $owner->id]);
            } elseif ($roll <= 6) {
                $ticket->update(['status' => WorkshopStatus::Delivered, 'completed_at' => now()->subDays(6), 'delivered_at' => now()->subDays($faker->numberBetween(1, 4)), 'repair_notes' => $faker->sentence(), 'completed_by' => $owner->id, 'delivered_by' => $owner->id]);
            }
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, InventoryItem>  $items
     */
    private function quotations(Generator $faker, Collection $customers, Collection $items, User $owner): void
    {
        foreach (range(1, 20) as $i) {
            $lines = [];
            foreach (range(1, $faker->numberBetween(2, 5)) as $l) {
                $product = $items->random();
                $hasDiscount = $faker->boolean(30);
                $lines[] = [
                    'customer_alias' => $product->name,
                    'quantity' => $faker->numberBetween(1, 12),
                    'unit_price' => (float) $product->current_sale_price,
                    'tax_rate' => 20,
                    'discount_type' => $hasDiscount ? 'percent' : null,
                    'discount_value' => $hasDiscount ? $faker->numberBetween(5, 15) : null,
                ];
            }

            $quotation = app(CreateQuotationAction::class)->handle([
                'customer_id' => $customers->random()->id,
                'currency' => 'TRY',
                'valid_until' => now()->addDays(30)->toDateString(),
                'terms' => 'Fiyatlarımıza KDV dahildir. Ödeme 30 gün vadelidir.',
                'lines' => $lines,
            ], $owner);

            // Spread across statuses for the list view.
            $roll = $faker->numberBetween(1, 10);
            if ($roll <= 3) {
                $quotation->update(['status' => QuotationStatus::Sent, 'sent_at' => now()->subDays($faker->numberBetween(1, 10))]);
            } elseif ($roll <= 5) {
                $quotation->update(['status' => QuotationStatus::Accepted, 'sent_at' => now()->subDays(8), 'accepted_at' => now()->subDays($faker->numberBetween(1, 6)), 'decided_by_name' => $faker->name()]);
            } elseif ($roll === 6) {
                $quotation->update(['status' => QuotationStatus::Rejected, 'sent_at' => now()->subDays(8), 'rejected_at' => now()->subDays(2), 'decided_by_name' => $faker->name()]);
            }
        }
    }
}
