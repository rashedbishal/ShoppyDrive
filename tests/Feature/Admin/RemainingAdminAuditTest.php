<?php

declare(strict_types=1);

use App\Mail\ReviewApprovedMail;
use App\Models\BlockedIp;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\PopularKeyword;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\LayoutCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Mail::fake();
    $this->auditAdmin = createAdmin(['name' => 'Audit Admin', 'email' => 'audit-admin@example.com', 'password' => bcrypt('password')]);
    actingAs($this->auditAdmin, 'admin');
});

function auditedReview(bool $approved = false): Review
{
    return Review::create([
        'product_id' => Product::factory()->create()->id,
        'user_id' => User::factory()->create(['name' => 'Private Reviewer', 'email' => 'reviewer@example.com'])->id,
        'rating' => 4,
        'title' => 'Private title',
        'body' => 'Private review body',
        'is_approved' => $approved,
    ]);
}

function adminEntries(string $event, ?string $subjectClass = null): Collection
{
    return Activity::query()->where('event', $event)
        ->when($subjectClass, fn ($query) => $query->where('subject_type', $subjectClass))
        ->get();
}

function failNextAuditEntry(): void
{
    Activity::creating(fn () => throw new RuntimeException('Audit write failed.'));
    test()->withoutExceptionHandling();
}

test('approving a review is audited once, with no private text', function () {
    $review = auditedReview();

    $this->patch(route('admin.reviews.approve', $review))->assertRedirect();
    $this->patch(route('admin.reviews.approve', $review))->assertRedirect();

    $entries = adminEntries('approved', Review::class);

    expect($entries)->toHaveCount(1);
    expect($entries->first()->causer->is($this->auditAdmin))->toBeTrue();
    expect($entries->first()->subject->is($review))->toBeTrue();
    expect($entries->first()->properties->toArray())->toEqual(['product_id' => $review->product_id, 'rating' => 4, 'was_approved' => false, 'is_approved' => true]);
    expect(json_encode($entries->first()->getAttributes()))->not->toContain('Private')->not->toContain('reviewer@example.com');
    Mail::assertQueued(ReviewApprovedMail::class, 1);
});

test('rejecting a pending review is audited once, even though no column changes', function () {
    $review = auditedReview();

    $this->patch(route('admin.reviews.reject', $review));
    $this->patch(route('admin.reviews.reject', $review));

    expect(adminEntries('rejected', Review::class))->toHaveCount(1);
});

test('each real change of decision on a review is audited', function () {
    $review = auditedReview();

    foreach (['reject', 'approve', 'reject'] as $action) {
        $this->patch(route("admin.reviews.{$action}", $review));
    }

    expect(adminEntries('rejected', Review::class))->toHaveCount(2);
    expect(adminEntries('approved', Review::class))->toHaveCount(1);
    expect(adminEntries('rejected', Review::class)->last()->properties['was_approved'])->toBeTrue();
});

test('deleting a review is audited', function () {
    $review = auditedReview(true);

    $this->delete(route('admin.reviews.destroy', $review))->assertRedirect();

    $entry = adminEntries('deleted', Review::class)->sole();

    expect($entry->causer->is($this->auditAdmin))->toBeTrue();
    expect($entry->properties['was_approved'])->toBeTrue();
});

test('a customer submitting a review, message or subscription writes no admin entry', function () {
    auditedReview();
    ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'subject' => 'Hello', 'message' => 'Hi']);
    NewsletterSubscriber::create(['email' => 'reader@example.com', 'token' => 'token-1', 'is_active' => true, 'subscribed_at' => now()]);

    expect(Activity::query()->whereIn('subject_type', [Review::class, ContactMessage::class, NewsletterSubscriber::class])->count())->toBe(0);
});

test('blocking and unblocking an IP is audited with the admin', function () {
    $this->post(route('admin.blocked-ips.store'), ['ip_address' => '203.0.113.5', 'reason' => 'Abuse'])->assertSessionHasNoErrors();

    $blockedIp = BlockedIp::where('ip_address', '203.0.113.5')->firstOrFail();
    $created = adminEntries('created', BlockedIp::class)->sole();

    expect($created->causer->is($this->auditAdmin))->toBeTrue();
    expect($created->description)->toContain('203.0.113.5');
    expect($created->properties['attributes']['reason'])->toBe('Abuse');

    $this->delete(route('admin.blocked-ips.destroy', $blockedIp))->assertRedirect();

    expect(adminEntries('deleted', BlockedIp::class)->sole()->properties['old']['ip_address'])->toBe('203.0.113.5');
});

test('the block list takes effect straight after a change', function () {
    expect(BlockedIp::isBlocked('203.0.113.6'))->toBeFalse();

    $this->post(route('admin.blocked-ips.store'), ['ip_address' => '203.0.113.6']);

    expect(BlockedIp::isBlocked('203.0.113.6'))->toBeTrue();

    $this->delete(route('admin.blocked-ips.destroy', BlockedIp::firstOrFail()));

    expect(BlockedIp::isBlocked('203.0.113.6'))->toBeFalse();
});

test('contact messages are audited on delete without their content', function () {
    $message = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'subject' => 'Private subject', 'message' => 'Private message']);

    $this->delete(route('admin.contact-messages.destroy', $message))->assertRedirect();

    $entry = adminEntries('deleted', ContactMessage::class)->sole();

    expect($entry->causer->is($this->auditAdmin))->toBeTrue();
    expect($entry->properties['email'])->toBe('v******@example.com');
    expect(json_encode($entry->getAttributes()))->not->toContain('Private')->not->toContain('visitor@example.com');
});

test('clearing contact messages is audited with a count, and an empty clear writes nothing', function () {
    $this->delete(route('admin.contact-messages.clear'))->assertRedirect();

    expect(adminEntries('cleared'))->toHaveCount(0);

    foreach ([1, 2] as $number) {
        ContactMessage::create(['name' => 'Visitor', 'email' => "visitor{$number}@example.com", 'subject' => 'Hello', 'message' => 'Hi']);
    }

    $this->delete(route('admin.contact-messages.clear'))->assertRedirect();

    $entry = adminEntries('cleared')->sole();

    expect($entry->causer->is($this->auditAdmin))->toBeTrue();
    expect($entry->properties['count'])->toBe(2);
    expect(ContactMessage::query()->count())->toBe(0);
});

test('removing a newsletter subscriber is audited with a masked address and no token', function () {
    $subscriber = NewsletterSubscriber::create(['email' => 'reader@example.com', 'token' => 'secret-token', 'is_active' => true, 'subscribed_at' => now()]);

    $this->delete(route('admin.newsletter.destroy', $subscriber))->assertRedirect();

    $entry = adminEntries('deleted', NewsletterSubscriber::class)->sole();

    expect($entry->properties['email'])->toBe('r*****@example.com');
    expect(json_encode($entry->getAttributes()))->not->toContain('reader@example.com')->not->toContain('secret-token');
});

test('keyword create, update and delete are audited with before and after', function () {
    $this->post(route('admin.popular-keywords.store'), ['keyword' => 'Shirts'])->assertSessionHasNoErrors();

    $keyword = PopularKeyword::where('keyword', 'Shirts')->firstOrFail();

    expect(adminEntries('created', PopularKeyword::class)->sole()->causer->is($this->auditAdmin))->toBeTrue();

    $this->put(route('admin.popular-keywords.update', $keyword), ['keyword' => 'Polos', 'sort_order' => 3, 'is_active' => true])->assertSessionHasNoErrors();

    $updated = adminEntries('updated', PopularKeyword::class)->sole();

    expect($updated->properties['old']['keyword'])->toBe('Shirts');
    expect($updated->properties['attributes']['keyword'])->toBe('Polos');

    $this->delete(route('admin.popular-keywords.destroy', $keyword))->assertRedirect();

    expect(adminEntries('deleted', PopularKeyword::class))->toHaveCount(1);
});

test('bulk keyword changes audit every row and clear the cache', function () {
    $first = PopularKeyword::create(['keyword' => 'One', 'sort_order' => 1, 'is_active' => true]);
    $second = PopularKeyword::create(['keyword' => 'Two', 'sort_order' => 2, 'is_active' => true]);
    Activity::query()->delete();

    $this->patch(route('admin.popular-keywords.updateStatusMany'), ['ids' => [$first->id, $second->id], 'active' => false])->assertRedirect();

    expect(adminEntries('updated', PopularKeyword::class))->toHaveCount(2);

    LayoutCache::popularKeywords();

    $this->delete(route('admin.popular-keywords.destroyMany'), ['ids' => [$first->id, $second->id]])->assertRedirect();

    expect(adminEntries('deleted', PopularKeyword::class))->toHaveCount(2);
    expect(LayoutCache::popularKeywords())->toBeEmpty();
});

test('a failed audit write rolls the action back', function (Closure $action, Closure $unchanged) {
    $subject = $action('arrange');
    failNextAuditEntry();

    expect(fn () => $action('act', $subject))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect($unchanged($subject))->toBeTrue();
})->with([
    'review approval' => [
        fn (string $step, ?Review $review = null) => $step === 'arrange' ? auditedReview() : test()->patch(route('admin.reviews.approve', $review)),
        fn (Review $review) => $review->refresh()->is_approved === false,
    ],
    'review deletion' => [
        fn (string $step, ?Review $review = null) => $step === 'arrange' ? auditedReview() : test()->delete(route('admin.reviews.destroy', $review)),
        fn (Review $review) => Review::whereKey($review->id)->exists(),
    ],
    'blocking an IP' => [
        fn (string $step, mixed $unused = null) => $step === 'arrange' ? null : test()->post(route('admin.blocked-ips.store'), ['ip_address' => '203.0.113.7']),
        fn () => BlockedIp::query()->count() === 0,
    ],
    'deleting a message' => [
        fn (string $step, ?ContactMessage $message = null) => $step === 'arrange'
            ? ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'subject' => 'Hello', 'message' => 'Hi'])
            : test()->delete(route('admin.contact-messages.destroy', $message)),
        fn (ContactMessage $message) => ContactMessage::whereKey($message->id)->exists(),
    ],
    'clearing messages' => [
        fn (string $step, ?ContactMessage $message = null) => $step === 'arrange'
            ? ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'subject' => 'Hello', 'message' => 'Hi'])
            : test()->delete(route('admin.contact-messages.clear')),
        fn (ContactMessage $message) => ContactMessage::whereKey($message->id)->exists(),
    ],
    'removing a subscriber' => [
        fn (string $step, ?NewsletterSubscriber $subscriber = null) => $step === 'arrange'
            ? NewsletterSubscriber::create(['email' => 'reader@example.com', 'token' => 'token-2', 'is_active' => true, 'subscribed_at' => now()])
            : test()->delete(route('admin.newsletter.destroy', $subscriber)),
        fn (NewsletterSubscriber $subscriber) => NewsletterSubscriber::whereKey($subscriber->id)->exists(),
    ],
    'adding a keyword' => [
        fn (string $step, mixed $unused = null) => $step === 'arrange' ? null : test()->post(route('admin.popular-keywords.store'), ['keyword' => 'Rolled back']),
        fn () => PopularKeyword::where('keyword', 'Rolled back')->doesntExist(),
    ],
]);
