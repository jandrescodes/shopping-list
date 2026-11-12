<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Fuera de producción, un lazy loading o un atributo descartado en
        // silencio falla en vez de pasar inadvertido.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
