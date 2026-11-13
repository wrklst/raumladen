<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pages/index', [
            'pages' => Page::query()->orderBy('id')->get()->map(fn (Page $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->translate('title', config('shop.locales')[0]),
                'is_published' => $page->is_published,
                'updated_at' => $page->updated_at?->toDateString(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/form', ['page' => null, 'locales' => config('shop.locales')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('admin.pages.edit', $page);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/form', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $page->body ?? [],
                'is_published' => $page->is_published,
            ],
            'locales' => config('shop.locales'),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return to_route('admin.pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('admin.pages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        $default = config('shop.locales')[0];

        $data = $request->validate([
            'slug' => ['required', 'string', 'alpha_dash:ascii', 'max:80', Rule::unique(Page::class)->ignore($page?->id)],
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:200'],
            'title.*' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:100000'],
            'is_published' => ['boolean'],
        ]);

        return [
            'slug' => mb_strtolower($data['slug']),
            'title' => array_filter($data['title']),
            'body' => array_filter($data['body'] ?? []),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
