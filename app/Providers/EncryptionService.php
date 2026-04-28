<?php

namespace App\Providers;

use App\Models\Document;

class EncryptionService
{
    /**
     * Checks if document's encryption_mode is set to envelope_wrapped
     */
    public function isEnvelopeMode(Document $document): bool
    {
        return $document->encryption_mode === 'envelope_wrapped';
    }
}
