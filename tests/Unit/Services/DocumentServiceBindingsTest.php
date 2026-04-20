<?php

namespace Tests\Unit\Services;

use App\Services\DocumentAccessService;
use App\Services\DocumentEncryptionService;
use App\Services\DocumentFileService;
use App\Services\DocumentNotificationService;
use App\Services\DocumentService;
use Tests\TestCase;

class DocumentServiceBindingsTest extends TestCase
{
    public function test_document_services_are_resolvable_from_container(): void
    {
        $this->assertInstanceOf(DocumentEncryptionService::class, app(DocumentEncryptionService::class));
        $this->assertInstanceOf(DocumentFileService::class, app(DocumentFileService::class));
        $this->assertInstanceOf(DocumentAccessService::class, app(DocumentAccessService::class));
        $this->assertInstanceOf(DocumentNotificationService::class, app(DocumentNotificationService::class));
        $this->assertInstanceOf(DocumentService::class, app(DocumentService::class));
    }

    public function test_document_services_are_singletons(): void
    {
        $this->assertSame(app(DocumentEncryptionService::class), app(DocumentEncryptionService::class));
        $this->assertSame(app(DocumentFileService::class), app(DocumentFileService::class));
        $this->assertSame(app(DocumentAccessService::class), app(DocumentAccessService::class));
        $this->assertSame(app(DocumentNotificationService::class), app(DocumentNotificationService::class));
        $this->assertSame(app(DocumentService::class), app(DocumentService::class));
    }
}
