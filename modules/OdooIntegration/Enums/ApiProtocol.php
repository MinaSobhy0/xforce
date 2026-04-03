<?php

namespace Modules\OdooIntegration\Enums;

enum ApiProtocol: string
{
    case XMLRPC = 'xmlrpc';
    case REST = 'rest';

    public function label(): string
    {
        return match($this) {
            self::XMLRPC => 'XML-RPC',
            self::REST => 'REST API',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::XMLRPC => 'Standard Odoo XML-RPC protocol (most compatible)',
            self::REST => 'REST API (requires Odoo REST module)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
