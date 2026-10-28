<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_can_save_multiple_listing_images(): void
    {
        Storage::fake('public');
        $user = User::create(['email' => 'multi@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Mia', 'last_name' => 'Grower', 'user_type' => 'Farmer']);
        Producer::create(['user_id' => $user->user_id, 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2UAAAAASUVORK5CYII=');

        $response = $this->actingAs($user, 'sanctum')->post('/api/producer/listings', [
            'product_name' => 'Fresh Tomatoes', 'category' => 'Vegetables', 'quantity' => 40, 'unit' => 'kg', 'price_per_unit' => 80,
            'images' => [UploadedFile::fake()->createWithContent('tomatoes-one.png', $tinyPng), UploadedFile::fake()->createWithContent('tomatoes-two.png', $tinyPng)],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'listing.images');
        $listingId = $response->json('listing.listing_id');
        $this->assertDatabaseCount('product_listing_images', 2);
        $this->assertSame(1, ProductListing::findOrFail($listingId)->images()->where('is_primary', true)->count());
        foreach (ProductListing::findOrFail($listingId)->images as $image) Storage::disk('public')->assertExists($image->path);
    }

    public function test_producer_can_upload_ten_images_and_an_eleven_image_listing_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::create(['email' => 'image-limit@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Mia', 'last_name' => 'Grower', 'user_type' => 'Farmer']);
        Producer::create(['user_id' => $user->user_id, 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2UAAAAASUVORK5CYII=');
        $listingData = [
            'product_name' => 'Image Limit Rice', 'category' => 'Grains', 'quantity' => 20, 'unit' => 'kg', 'price_per_unit' => 50,
        ];
        $tenImages = array_map(
            fn ($index) => UploadedFile::fake()->createWithContent("rice-{$index}.png", $tinyPng),
            range(1, 10),
        );

        $created = $this->actingAs($user, 'sanctum')->post('/api/producer/listings', [
            ...$listingData,
            'images' => $tenImages,
        ]);

        $created->assertCreated()->assertJsonCount(10, 'listing.images');
        $this->assertDatabaseCount('product_listing_images', 10);

        $elevenImages = array_map(
            fn ($index) => UploadedFile::fake()->createWithContent("too-many-{$index}.png", $tinyPng),
            range(1, 11),
        );

        $this->actingAs($user, 'sanctum')->post('/api/producer/listings', [
            ...$listingData,
            'product_name' => 'Eleven Images',
            'images' => $elevenImages,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images');

        $this->assertDatabaseCount('product_listings', 1);
        $this->assertDatabaseCount('product_listing_images', 10);
    }

    public function test_producer_can_add_and_remove_images_while_updating_a_listing(): void
    {
        Storage::fake('public');
        $user = User::create(['email' => 'image-edit@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Mia', 'last_name' => 'Grower', 'user_type' => 'Farmer']);
        $producer = Producer::create(['user_id' => $user->user_id, 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Image Edit Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'quantity_available' => 20,
            'status' => 'Active',
        ]);
        $removedPath = 'product_images/remove-this.png';
        $preservedPath = 'product_images/keep-this.png';
        Storage::disk('public')->put($removedPath, 'old image');
        Storage::disk('public')->put($preservedPath, 'preserved image');
        $removed = $listing->images()->create(['path' => $removedPath, 'is_primary' => true]);
        $preserved = $listing->images()->create(['path' => $preservedPath, 'is_primary' => false]);
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2UAAAAASUVORK5CYII=');

        $response = $this->actingAs($user, 'sanctum')->post("/api/producer/listings/{$listing->listing_id}", [
            '_method' => 'PUT',
            'product_name' => 'Updated Image Edit Rice',
            'quantity' => 25,
            'remove_image_ids' => [$removed->id],
            'images' => [UploadedFile::fake()->createWithContent('new-rice.png', $tinyPng)],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('listing.product_name', 'Updated Image Edit Rice')
            ->assertJsonPath('listing.quantity_available', 25)
            ->assertJsonCount(2, 'listing.images');
        $this->assertDatabaseMissing('product_listing_images', ['id' => $removed->id]);
        $this->assertDatabaseHas('product_listing_images', ['id' => $preserved->id, 'listing_id' => $listing->listing_id]);
        Storage::disk('public')->assertMissing($removedPath);
        Storage::disk('public')->assertExists($preservedPath);
        $this->assertDatabaseCount('product_listing_images', 2);
    }

    public function test_buyer_marketplace_returns_only_available_listings_with_public_producer_details(): void
    {
        $buyer = User::create(['email' => 'buyer-market@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Buyer', 'user_type' => 'Buyer']);
        $producerUser = User::create(['email' => 'seller-market@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Lina', 'last_name' => 'Seller', 'user_type' => 'Farmer']);
        $producer = Producer::create(['user_id' => $producerUser->user_id, 'location' => 'Aparri', 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Sweet Corn', 'product_category' => 'Corn', 'quantity_available' => 25, 'unit_of_measure' => 'kg', 'current_price_per_unit' => 65, 'status' => 'Active']);
        ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Sold Corn', 'product_category' => 'Corn', 'quantity_available' => 0, 'unit_of_measure' => 'kg', 'status' => 'Sold']);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/buyer/products');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_name', 'Sweet Corn')->assertJsonPath('data.0.producer.name', 'Lina Seller')->assertJsonPath('data.0.producer.location', 'Aparri');
        $this->assertArrayNotHasKey('email', $response->json('data.0.producer'));
    }

    public function test_buyer_marketplace_exposes_the_uploaded_photos_for_each_listing(): void
    {
        Storage::fake('public');
        $buyer = User::create(['email' => 'buyer-photos@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Buyer', 'user_type' => 'Buyer']);
        $producerUser = User::create(['email' => 'seller-photos@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Lina', 'last_name' => 'Seller', 'user_type' => 'Farmer']);
        $producer = Producer::create(['user_id' => $producerUser->user_id, 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2UAAAAASUVORK5CYII=');

        $withPhotos = ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Sweet Corn', 'product_category' => 'Corn', 'quantity_available' => 25, 'unit_of_measure' => 'kg', 'current_price_per_unit' => 65, 'status' => 'Active']);
        $withPhotos->images()->create(['path' => UploadedFile::fake()->createWithContent('corn-b.png', $tinyPng)->store('product_images', 'public'), 'is_primary' => false]);
        $primary = $withPhotos->images()->create(['path' => UploadedFile::fake()->createWithContent('corn-a.png', $tinyPng)->store('product_images', 'public'), 'is_primary' => true]);
        ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Plain Rice', 'product_category' => 'Grains', 'quantity_available' => 10, 'unit_of_measure' => 'kg', 'current_price_per_unit' => 40, 'status' => 'Active']);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/buyer/products');

        $response->assertOk()->assertJsonCount(2, 'data');
        $byName = collect($response->json('data'))->keyBy('product_name');

        $photos = $byName['Sweet Corn']['images'];
        $this->assertCount(2, $photos);
        $this->assertTrue($photos[0]['is_primary']);
        $storedPaths = $withPhotos->images()->pluck('path')->all();
        foreach ($photos as $photo) {
            $this->assertStringContainsString('/storage/product_images/', $photo['url']);
            $relative = ltrim(substr($photo['url'], strrpos($photo['url'], '/storage/') + strlen('/storage/')), '/');
            $this->assertContains($relative, $storedPaths);
            Storage::disk('public')->assertExists($relative);
        }
        // The primary photo must be reachable under its stored relative path.
        $this->assertStringEndsWith($primary->path, $photos[0]['url']);

        $this->assertSame([], $byName['Plain Rice']['images']);
        $this->assertNull($byName['Plain Rice']['image_url']);
    }
}
