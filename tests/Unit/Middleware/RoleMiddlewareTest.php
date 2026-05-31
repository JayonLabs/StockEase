<?php

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('allows user with correct role', function ($role) {
    $user = User::factory()->create(['role' => $role]);
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('user')->once()->andReturn($user);

    $middleware = new RoleMiddleware;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('Allowed');
    }, $role);

    expect($response->getContent())->toBe('Allowed');
})->with(['admin', 'cashier', 'warehouse']);

it('aborts if user does not have correct role', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('user')->once()->andReturn($user);

    $middleware = new RoleMiddleware;
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function () {}, 'admin');
})->throws(HttpException::class, 'Unauthorized access.');

it('aborts if user is not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $middleware = new RoleMiddleware;
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function () {}, 'admin');
})->throws(HttpException::class, 'Unauthorized access.');
