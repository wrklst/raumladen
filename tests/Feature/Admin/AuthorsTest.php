<?php

use App\Models\Author;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an author is entered with a text about them, changed and deleted', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.authors.store'), ['name' => ' Zara Pfeifer ', 'bio' => ['en' => 'Artist.', 'de' => 'Künstlerin.', 'xx' => 'ignored']])->assertRedirect();

    $author = Author::query()->sole();
    expect($author->name)->toBe('Zara Pfeifer')->and($author->bio)->toBe(['en' => 'Artist.', 'de' => 'Künstlerin.']);

    // One entry per name.
    $this->post(route('admin.authors.store'), ['name' => 'Zara Pfeifer'])->assertSessionHasErrors('name');

    $this->put(route('admin.authors.update', $author), ['name' => 'Zara Pfeifer', 'bio' => ['en' => 'Artist and photographer.']])->assertRedirect();
    expect($author->refresh()->translate('bio', 'en'))->toBe('Artist and photographer.');

    $this->get(route('admin.authors.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/authors/index')->has('authors', 1));

    $this->delete(route('admin.authors.destroy', $author))->assertRedirect();
    expect(Author::query()->count())->toBe(0);
});

test('the listing of an author shows the text about them above their books', function () {
    CustomerGroup::factory()->consumer()->create();
    Product::factory()->create(['specs' => ['authors' => 'Zara Pfeifer, Jonas Weber']]);
    Author::factory()->create(['name' => 'Zara Pfeifer', 'bio' => ['de' => 'Künstlerin in Berlin.', 'en' => 'Artist in Berlin.']]);

    // Found by the name the book carries, whatever the capitals.
    $this->get('/en?author='.urlencode('zara pfeifer'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 1)
        ->where('author', ['name' => 'Zara Pfeifer', 'bio' => 'Artist in Berlin.']));

    // An author the shop has no text about, and the plain catalogue.
    $this->get('/en?author='.urlencode('Jonas Weber'))->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('author', null));
    $this->get('/en')->assertInertia(fn (Assert $page) => $page->where('author', null));
});
