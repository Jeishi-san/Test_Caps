<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFileController;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class DocumentControllerDelegationTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_document_order_delegates_to_document_file_controller(): void
    {
        $request = Request::create('/update-document-order', 'POST', [
            'folder_id' => 1,
            'document_ids' => [1, 2, 3],
        ]);

        $service = Mockery::mock(DocumentService::class);
        $fileController = Mockery::mock(DocumentFileController::class);

        $expected = new JsonResponse(['message' => 'ok'], 200);

        $fileController
            ->shouldReceive('updateOrder')
            ->once()
            ->with($request)
            ->andReturn($expected);

        $controller = new DocumentController($service, $fileController);

        $this->assertSame($expected, $controller->updateDocumentOrder($request));
    }

    public function test_filter_by_tag_delegates_to_document_file_controller(): void
    {
        $request = Request::create('/filter-documents-by-tags', 'GET', [
            'folder' => 1,
            'tags' => [10, 11],
        ]);

        $service = Mockery::mock(DocumentService::class);
        $fileController = Mockery::mock(DocumentFileController::class);

        $expected = new JsonResponse(['documents' => []], 200);

        $fileController
            ->shouldReceive('filterByTag')
            ->once()
            ->with($request)
            ->andReturn($expected);

        $controller = new DocumentController($service, $fileController);

        $this->assertSame($expected, $controller->filterDocumentByTag($request));
    }
}
