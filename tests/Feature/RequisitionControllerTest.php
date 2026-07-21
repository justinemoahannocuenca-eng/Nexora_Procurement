<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequisitionControllerTest extends TestCase
{
    public function test_requisition_index_returns_ok(): void
    {
        $response = $this->get('/requisitions');

        $response->assertOk();
    }
}
