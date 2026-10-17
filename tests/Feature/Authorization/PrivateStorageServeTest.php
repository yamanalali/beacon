<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Readiness\ReadinessTestHelpers;
use Tests\TestCase;

/** Private disk files are never served by bare /storage/{path} (receipts must go through documents.download). */
class PrivateStorageServeTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    public function test_unsigned_private_storage_url_is_refused_even_when_signed_in(): void
    {
        Storage::disk('local')->put('documents/tenant-b/receipt.jpg', 'secret');
        $sme = $this->smeOf($this->enterprise());

        $this->actingAs($sme)->get('/storage/documents/tenant-b/receipt.jpg')->assertForbidden();

        Storage::disk('local')->delete('documents/tenant-b/receipt.jpg');
    }
}
