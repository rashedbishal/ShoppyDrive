<?php

declare(strict_types=1);

use App\Models\PopularKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
    $this->keywords = collect(['phone', 'shoes', 'watch'])->map(
        fn (string $keyword, int $i) => PopularKeyword::create(['keyword' => $keyword, 'sort_order' => $i + 1, 'is_active' => true]),
    );
});

test('dragging keywords saves their new order', function () {
    [$phone, $shoes, $watch] = $this->keywords->all();

    actingAs($this->admin, 'admin')
        ->post(route('admin.popular-keywords.reorder'), ['ids' => [$watch->id, $phone->id, $shoes->id]])
        ->assertSessionHasNoErrors();

    expect(PopularKeyword::orderBy('sort_order')->pluck('keyword')->all())->toBe(['watch', 'phone', 'shoes'])
        ->and($watch->fresh()->sort_order)->toBe(1)
        ->and($shoes->fresh()->sort_order)->toBe(3);
});

test('reordering refuses ids that are not keywords, or the same id twice', function () {
    $first = $this->keywords->first();

    actingAs($this->admin, 'admin')->post(route('admin.popular-keywords.reorder'), ['ids' => [$first->id, 99999]])
        ->assertSessionHasErrors('ids.1');
    actingAs($this->admin, 'admin')->post(route('admin.popular-keywords.reorder'), ['ids' => [$first->id, $first->id]])
        ->assertSessionHasErrors('ids.0');
});

test('an admin without update permission on keywords cannot reorder them', function () {
    $limited = createAdmin(['permissions' => ['search_keywords' => ['view']]]);

    actingAs($limited, 'admin')->post(route('admin.popular-keywords.reorder'), ['ids' => $this->keywords->pluck('id')->all()])
        ->assertForbidden();
});
