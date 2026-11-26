<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/authors/index', [
            'authors' => Author::query()->orderBy('name')->get(['id', 'name', 'bio']),
            'locales' => config('shop.supported_locales'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Author::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Author created.')]);

        return back();
    }

    public function update(Request $request, Author $author): RedirectResponse
    {
        $author->update($this->validated($request, $author));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Author updated.')]);

        return back();
    }

    public function destroy(Author $author): RedirectResponse
    {
        $author->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Author deleted.')]);

        return back();
    }

    /**
     * @return array{name: string, bio: array<string, string|null>}
     */
    private function validated(Request $request, ?Author $author = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique(Author::class)->ignore($author?->id)],
            'bio' => ['nullable', 'array'],
            'bio.*' => ['nullable', 'string', 'max:5000'],
        ]);

        return ['name' => trim($data['name']), 'bio' => array_intersect_key($data['bio'] ?? [], array_flip(config('shop.supported_locales')))];
    }
}
