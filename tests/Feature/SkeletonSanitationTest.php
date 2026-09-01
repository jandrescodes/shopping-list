<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Pest\TestSuite;

it('has no User model', function () {
    expect(class_exists(User::class))->toBeFalse();
});

it('does not create auth or queue tables when migrations run', function () {
    // RefreshDatabase has already run every migration before this test.
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeFalse()
        ->and(Schema::hasTable('personal_access_tokens'))->toBeFalse()
        ->and(Schema::hasTable('jobs'))->toBeFalse();
});

it('has removed laravel/sanctum from composer', function () {
    $composer = file_get_contents(base_path('composer.json'));

    expect(str_contains(strtolower($composer), 'sanctum'))->toBeFalse();
});

it('exposes no user route', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri());

    expect($uris)->not->toContain('api/user')
        ->and($uris)->not->toContain('user');
});

it('runs the test suite through Pest', function () {
    expect(function_exists('test'))->toBeTrue()
        ->and(class_exists(TestSuite::class))->toBeTrue();
});

it('defaults the application locale to Spanish', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('es');
});

it('ships Spanish validation translations', function () {
    expect(is_file(lang_path('es/validation.php')))->toBeTrue();

    $messages = require lang_path('es/validation.php');

    expect($messages)->toBeArray()
        ->and($messages['required'])->toContain('obligatorio');
});

it('registers Alpine as a pinned frontend dependency', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);

    expect($package['dependencies']['alpinejs'] ?? null)
        ->toMatch('/^\d+\.\d+\.\d+$/');
});

it('wires resources/js/list.js into the Vite build', function () {
    expect(is_file(resource_path('js/list.js')))->toBeTrue()
        ->and(file_get_contents(base_path('vite.config.js')))
        ->toContain('resources/js/list.js');
});

it('sets .env.example to MySQL with a cookie session driver', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('DB_CONNECTION=mysql')
        ->and($env)->toContain('SESSION_DRIVER=cookie')
        ->and($env)->toContain('APP_LOCALE=es');
});
