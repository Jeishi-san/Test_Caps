<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * StegoDocumentGrant
 *
 * Records that a StegoDocument owner has granted decode-view access to
 * another user (the "viewer").
 *
 * Relationships:
 *   stegoDocument — the document for which access was granted
 *   viewer        — the User who received access
 *   grantedBy     — the User (owner) who created the grant
 */
class StegoDocumentGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'stego_document_id',
        'viewer_user_id',
        'granted_by',
        'grant_status',
        'accepted_at',
        'viewer_wrapped_dek',
        'viewer_wrapped_dek_iv',
        'viewer_wrapped_dek_auth_tag',
        'viewer_wrapped_dek_alg',
        'viewer_wrapped_dek_version',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'viewer_wrapped_dek_version' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function stegoDocument()
    {
        return $this->belongsTo(StegoDocument::class);
    }

    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
