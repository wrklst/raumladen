<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Webauthn\Exception\AuthenticatorResponseVerificationException;

test('the allowed passkey origin is the origin of APP_URL, whatever follows the host', function (string $appUrl, string $origin) {
    config(['app.url' => $appUrl]);

    $fortify = require config_path('fortify.php');

    expect($fortify['passkeys']['allowed_origins'])->toBe([$origin])
        ->and($fortify['passkeys']['relying_party_id'])->toBe(parse_url($origin, PHP_URL_HOST));
})->with([
    'plain' => ['https://shop.example.org', 'https://shop.example.org'],
    'trailing slash' => ['https://shop.example.org/', 'https://shop.example.org'],
    'with a path' => ['https://shop.example.org/shop/', 'https://shop.example.org'],
    'with a port' => ['http://localhost:8000/', 'http://localhost:8000'],
]);

test('a passkey that fails verification is answered as a validation error, not as a server error', function () {
    Route::post('test/passkey-failure', fn () => throw AuthenticatorResponseVerificationException::create('Invalid origin'))->middleware('web');

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('test/passkey-failure')
        ->assertStatus(422)
        ->assertJsonValidationErrors('credential');
});
