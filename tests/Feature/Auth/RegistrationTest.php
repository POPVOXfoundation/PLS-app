<?php

use App\Domain\Institutions\Country;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $country = Country::factory()->create([
        'name' => 'Belize',
        'iso2' => 'BZ',
        'iso3' => 'BLZ',
    ]);

    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('Country')
        ->assertSee($country->name);
});

test('new users can register', function () {
    $country = Country::factory()->create([
        'name' => 'Belize',
        'iso2' => 'BZ',
        'iso3' => 'BLZ',
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'country_id' => $country->id,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    expect(auth()->user()->country_id)->toBe($country->id);
});

test('registration requires a country', function () {
    Country::factory()->create();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('country_id');
});
