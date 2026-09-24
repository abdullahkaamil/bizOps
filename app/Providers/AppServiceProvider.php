<?php

namespace App\Providers;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Policies\CustomerPolicy;
use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Policies\GeneratedDocumentPolicy;
use App\Domain\Documents\Support\DompdfRenderer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Policies\InventoryPolicy;
use App\Domain\Inventory\Policies\SupplierPolicy;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Policies\JobPolicy;
use App\Domain\Notifications\Channels\IdempotentDatabaseChannel;
use App\Domain\Notifications\Channels\TenantMailChannel;
use App\Domain\Notifications\Listeners\SendTaskTransitionNotifications;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Policies\QuotationPolicy;
use App\Domain\Tasks\Events\TaskTransitioned;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Policies\BoardPolicy;
use App\Domain\Tasks\Policies\TaskPolicy;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Domain\Workshop\Policies\WorkshopPolicy;
use App\Listeners\LogAuthenticationEvents;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The PDF engine is an implementation detail behind the renderer contract.
        $this->app->bind(
            PdfRenderer::class,
            DompdfRenderer::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Domain models live outside App\Models, so their policies are mapped explicitly.
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Board::class, BoardPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(GeneratedDocument::class, GeneratedDocumentPolicy::class);
        Gate::policy(WorkshopTicket::class, WorkshopPolicy::class);
        Gate::policy(InventoryItem::class, InventoryPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);

        $this->configureNotifications();
    }

    /**
     * Wire the tenant-aware notification foundation: custom channels (email
     * logging + idempotent in-app storage) and the domain-event listeners that
     * turn business events into notifications.
     */
    protected function configureNotifications(): void
    {
        Notification::extend('tenant-mail', fn ($app) => $app->make(TenantMailChannel::class));
        Notification::extend('tenant-database', fn ($app) => $app->make(IdempotentDatabaseChannel::class));

        Event::listen(TaskTransitioned::class, SendTaskTransitionNotifications::class);

        // Audit authentication events (login / logout / failed) without leaking credentials.
        Event::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Served over TLS (e.g. Valet secured sites, production). Force generated
        // URLs to https so tenant subdomain redirects don't bounce http<->https,
        // which breaks Inertia's XHR-followed redirects with a mixed-content error.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
