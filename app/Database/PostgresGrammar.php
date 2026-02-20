<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BaseGrammar;

/**
 * Custom PostgreSQL Grammar.
 *
 * This class can be extended if we need custom SQL generation for PostgreSQL.
 * Currently, the main boolean handling is done in PostgresConnection.
 */
class PostgresGrammar extends BaseGrammar
{
    //
}
