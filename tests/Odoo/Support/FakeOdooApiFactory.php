<?php

namespace Tests\Odoo\Support;

use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;

/**
 * Container replacement for OdooApiFactory that always hands back the
 * test's FakeOdooClient — covers both constructor-injected factories
 * (SyncEngine) and ad-hoc app(OdooApiFactory::class) calls inside model
 * hooks (PayrollLine/StaffProfile applyOdooImport).
 */
class FakeOdooApiFactory extends OdooApiFactory
{
    public function __construct(protected FakeOdooClient $client) {}

    public function make(OdooConnection $connection): OdooApiClientInterface
    {
        return $this->client->init($connection);
    }

    public function createClient(OdooConnection $connection): OdooApiClientInterface
    {
        return $this->client->init($connection);
    }
}
