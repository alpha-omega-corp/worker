<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('on a laptop db:seed makes the local admin, test@alphomega.org / test, and a second run keeps one', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed();
    $this->seed();

    $admin = User::query()->where('email', 'test@alphomega.org')->sole();
    expect($admin->getAttribute('is_admin'))->toBeTruthy()
        ->and(Hash::check('test', $admin->password))->toBeTrue();
});

test('anywhere else it makes nobody: a known password never reaches a server', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
