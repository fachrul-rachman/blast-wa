<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

uses(DatabaseTransactions::class);

beforeEach(function () {
    RateLimiter::clear('login:testadmin|127.0.0.1');
});

test('admin can log in with valid username and password', function () {
    Admin::factory()->create([
        'username' => 'testadmin',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('login.store'), [
        'username' => 'testadmin',
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard'));

    assertAuthenticated();
});

test('admin cannot log in with invalid credentials', function () {
    Admin::factory()->create([
        'username' => 'testadmin',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('login.store'), [
        'username' => 'testadmin',
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors('username')
        ->assertRedirect();

    assertGuest();
});

test('guest cannot access authenticated pages', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('admin can log out', function () {
    $admin = Admin::factory()->create();

    actingAs($admin);

    post(route('logout'))->assertRedirect(route('login'));

    assertGuest();
});

test('login attempts are rate limited', function () {
    Admin::factory()->create([
        'username' => 'testadmin',
        'password' => Hash::make('secret-password'),
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        post(route('login.store'), [
            'username' => 'testadmin',
            'password' => 'wrong-password',
        ]);
    }

    post(route('login.store'), [
        'username' => 'testadmin',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('username');
});
