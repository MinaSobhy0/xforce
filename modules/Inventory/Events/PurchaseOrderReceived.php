<?php

namespace Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\PurchaseOrderLine;

class PurchaseOrderReceived
{
    use Dispatchable, SerializesModels;

    public PurchaseOrderLine $line;
    public int $quantityReceived;
    public string $tenantId;

    public function __construct(PurchaseOrderLine $line, int $quantityReceived)
    {
        $this->line = $line;
        $this->quantityReceived = $quantityReceived;
        $this->tenantId = $line->tenant_id;
    }
}
