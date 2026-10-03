<?php

namespace App\Providers;

use AlphaOmega\Site\Identity\Identity;
use App\Support\Pages;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Pages::class, fn (): Pages => new Pages(resource_path('pages.json')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSiteChrome();

        $this->app->make(Pages::class)->share();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
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

    /**
     * The site header and the site footer of every page name the business from
     * the base's identity, which the owner keeps at /admin, wherever the tag
     * leaves it out: the name, and the footer's address, phone and email. So no
     * page is built with a header that leads nowhere, and none drifts from the
     * others by a contact line. What a tag gives still wins.
     */
    protected function configureSiteChrome(): void
    {
        View::composer(['components.kit.site-header', 'components.kit.site-footer'], function (ViewContract $view): void {
            $identity = Identity::current();
            $defaults = ['brand' => $identity->name];

            if ($view->name() === 'components.kit.site-footer') {
                $defaults += ['address' => $identity->address(), 'phone' => $identity->phone, 'email' => $identity->email];
            }

            foreach ($defaults as $prop => $value) {
                if (blank($view->getData()[$prop] ?? null) && filled($value)) {
                    $view->with($prop, $value);
                }
            }
        });
    }
}
