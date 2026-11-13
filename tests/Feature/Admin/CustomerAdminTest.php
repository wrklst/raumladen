<?php

use App\Models\CustomerGroup;
use App\Models\User;
use App\Tax\VatIdValidator;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function customerPayload(User $customer, array $overrides = []): array
{
    return [
        'name' => 'Erika Mustermann',
        'email' => $customer->email,
        'company' => null,
        'customer_group_id' => $customer->customer_group_id,
        ...$overrides,
    ];
}

test('the customer list shows customers only and can be searched', function () {
    User::factory()->create(['name' => 'Anna Alpha', 'email' => 'anna@example.com', 'customer_group_id' => $this->consumer->id]);
    User::factory()->create(['name' => 'Bernd Beta', 'company' => 'Findus GmbH', 'customer_group_id' => $this->consumer->id]);

    $this->get(route('admin.customers.index'))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 2));

    $this->get(route('admin.customers.index', ['q' => 'findus']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Bernd Beta'));

    $this->get(route('admin.customers.index', ['q' => 'anna@']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Anna Alpha'));
});

test('administrators cannot be edited as customers', function () {
    $this->get(route('admin.customers.edit', $this->admin))->assertNotFound();
    $this->patch(route('admin.customers.update', $this->admin), ['name' => 'x', 'email' => 'x@example.com', 'customer_group_id' => $this->consumer->id])->assertNotFound();
});

test('an admin edits a customer', function () {
    $business = CustomerGroup::factory()->business()->create();
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);

    $this->get(route('admin.customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/customers/edit')->where('customer.id', $customer->id));

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, [
        'name' => 'Neuer Name', 'email' => 'neu@example.com', 'company' => 'Buch GmbH', 'customer_group_id' => $business->id,
    ]))->assertSessionHasNoErrors();

    $customer->refresh();

    expect($customer->name)->toBe('Neuer Name')
        ->and($customer->email)->toBe('neu@example.com')
        ->and($customer->company)->toBe('Buch GmbH')
        ->and($customer->customer_group_id)->toBe($business->id);
});

test('a customer update is validated', function (array $overrides, string $field) {
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, $overrides))->assertSessionHasErrors($field);
})->with([
    'name missing' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'nope'], 'email'],
    'email taken' => [['email' => 'taken@example.com'], 'email'],
    'group missing' => [['customer_group_id' => null], 'customer_group_id'],
    'unknown group' => [['customer_group_id' => 999], 'customer_group_id'],
    'vat id too long' => [['vat_id' => str_repeat('A', 21)], 'vat_id'],
]);

test('a customer may keep their own email', function () {
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer))->assertSessionHasNoErrors();
});

test('a VAT ID is normalized and stamped when VIES confirms it', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, ['vat_id' => 'at u 12345678']));

    $customer->refresh();

    expect($customer->vat_id)->toBe('ATU12345678')
        ->and($customer->vat_id_validated_at)->not->toBeNull()
        ->and($customer->hasValidVatId())->toBeTrue();
});

test('a VAT ID that VIES rejects is stored but not stamped', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => false])]);
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, ['vat_id' => 'ATU00000000']));

    $customer->refresh();

    expect($customer->vat_id)->toBe('ATU00000000')->and($customer->vat_id_validated_at)->toBeNull();
});

test('an admin can vouch for a VAT ID without asking VIES', function () {
    Http::fake();
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, ['vat_id' => 'ATU12345678', 'vat_id_valid' => 1]));

    expect($customer->refresh()->hasValidVatId())->toBeTrue();
    Http::assertNothingSent();
});

test('clearing the VAT ID clears its validation', function () {
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id, 'vat_id' => 'ATU12345678', 'vat_id_validated_at' => now()]);

    $this->patch(route('admin.customers.update', $customer), customerPayload($customer, ['vat_id' => '']));

    $customer->refresh();

    expect($customer->vat_id)->toBeNull()->and($customer->vat_id_validated_at)->toBeNull();
});

test('the optional company field may be left out of the update', function () {
    $customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);
    $payload = customerPayload($customer);
    unset($payload['company']);

    $this->patch(route('admin.customers.update', $customer), $payload)->assertSessionHasNoErrors()->assertRedirect();
});
