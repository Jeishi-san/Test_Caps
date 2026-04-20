<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\DocumentFileController;
use Tests\TestCase;

class DocumentFileRoutesTest extends TestCase
{
    public function test_phase2_file_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNotNull($routes->getByName('files.index'));
        $this->assertNotNull($routes->getByName('files.upload'));
        $this->assertNotNull($routes->getByName('files.download'));
        $this->assertNotNull($routes->getByName('files.view'));
        $this->assertNotNull($routes->getByName('files.filterByTag'));
        $this->assertNotNull($routes->getByName('files.updateVisibility'));
        $this->assertNotNull($routes->getByName('files.updateOrder'));
    }

    public function test_legacy_file_routes_delegate_to_document_file_controller(): void
    {
        $routes = app('router')->getRoutes();

        $getFilesAction = $routes->getByName('getFiles')->getActionName();
        $uploadAction = $routes->getByName('upload')->getActionName();
        $visibilityAction = $routes->getByName('update.visibility')->getActionName();

        $this->assertStringContainsString(DocumentFileController::class, $getFilesAction);
        $this->assertStringContainsString(DocumentFileController::class, $uploadAction);
        $this->assertStringContainsString(DocumentFileController::class, $visibilityAction);
    }

    public function test_phase2_api_file_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNotNull($routes->getByName('api.documents.files.index'));
        $this->assertNotNull($routes->getByName('api.documents.files.upload'));
        $this->assertNotNull($routes->getByName('api.documents.files.download'));
        $this->assertNotNull($routes->getByName('api.documents.files.view'));
        $this->assertNotNull($routes->getByName('api.documents.files.filterByTag'));
        $this->assertNotNull($routes->getByName('api.documents.files.updateVisibility'));
        $this->assertNotNull($routes->getByName('api.documents.files.updateOrder'));
    }
}
