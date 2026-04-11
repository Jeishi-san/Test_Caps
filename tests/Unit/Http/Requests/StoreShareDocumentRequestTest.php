<?php

namespace Tests\Unit\Http\Requests;

use Tests\TestCase;
use App\Http\Requests\StoreShareDocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StoreShareDocumentRequestTest extends TestCase
{
    use RefreshDatabase;

    protected StoreShareDocumentRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new StoreShareDocumentRequest();
    }

    /** @test */
    public function validation_passes_with_valid_data()
    {
        $data = [
            'shared_id' => 123,
            'slug' => 'document',
            'name' => 'Test Document',
            'valid_until' => now()->addDay()->toISOString(),
            'visibility' => 'public',
            'permission_level' => 'editor',
        ];

        $validator = validator($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function shared_id_is_required()
    {
        $data = [
            'slug' => 'document',
            'name' => 'Test Document',
        ];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('shared_id', $validator->errors()->toArray());
    }

    /** @test */
    public function slug_is_required()
    {
        $data = [
            'shared_id' => 123,
            'name' => 'Test Document',
        ];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    /** @test */
    public function slug_must_be_valid_type()
    {
        $invalidSlugs = ['invalid', 'file', 'image', '', null];

        foreach ($invalidSlugs as $slug) {
            $data = [
                'shared_id' => 123,
                'slug' => $slug,
                'name' => 'Test Document',
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertTrue($validator->fails(), "Slug '{$slug}' should fail validation");
        }

        $validSlugs = ['document', 'folder', 'stego'];

        foreach ($validSlugs as $slug) {
            $data = [
                'shared_id' => 123,
                'slug' => $slug,
                'name' => 'Test Document',
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertFalse($validator->fails(), "Slug '{$slug}' should pass validation");
        }
    }

    /** @test */
    public function name_is_required()
    {
        $data = [
            'shared_id' => 123,
            'slug' => 'document',
        ];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /** @test */
    public function valid_until_must_be_future_date()
    {
        $data = [
            'shared_id' => 123,
            'slug' => 'document',
            'name' => 'Test Document',
            'valid_until' => now()->subDay()->toISOString(),
        ];

        $validator = validator($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('valid_until', $validator->errors()->toArray());

        $data['valid_until'] = now()->addHour()->toISOString();
        $validator = validator($data, $this->request->rules());
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function visibility_must_be_valid()
    {
        $invalidVisibilities = ['hidden', 'restricted', '', null];

        foreach ($invalidVisibilities as $visibility) {
            $data = [
                'shared_id' => 123,
                'slug' => 'document',
                'name' => 'Test Document',
                'visibility' => $visibility,
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertTrue($validator->fails(), "Visibility '{$visibility}' should fail validation");
        }

        $validVisibilities = ['public', 'private'];

        foreach ($validVisibilities as $visibility) {
            $data = [
                'shared_id' => 123,
                'slug' => 'document',
                'name' => 'Test Document',
                'visibility' => $visibility,
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertFalse($validator->fails(), "Visibility '{$visibility}' should pass validation");
        }
    }

    /** @test */
    public function permission_level_must_be_valid()
    {
        $invalidLevels = ['guest', 'admin', 'super', '', null];

        foreach ($invalidLevels as $level) {
            $data = [
                'shared_id' => 123,
                'slug' => 'document',
                'name' => 'Test Document',
                'permission_level' => $level,
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertTrue($validator->fails(), "Permission level '{$level}' should fail validation");
        }

        $validLevels = ['viewer', 'commenter', 'editor', 'co_owner', 'owner'];

        foreach ($validLevels as $level) {
            $data = [
                'shared_id' => 123,
                'slug' => 'document',
                'name' => 'Test Document',
                'permission_level' => $level,
            ];

            $validator = validator($data, $this->request->rules());
            $this->assertFalse($validator->fails(), "Permission level '{$level}' should pass validation");
        }
    }

    /** @test */
    public function token_must_be_unique_if_provided()
    {
        // Create existing share with token
        $existingToken = 'existing-token-12345';
        \App\Models\ShareDocument::factory()->create(['token' => $existingToken]);

        $data = [
            'shared_id' => 123,
            'slug' => 'document',
            'name' => 'Test Document',
            'token' => $existingToken,
        ];

        $validator = validator($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());

        $data['token'] = 'new-unique-token-67890';
        $validator = validator($data, $this->request->rules());
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function authorize_always_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }
}
