<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\JournalEntry;

class JournalEntryPrintController extends Controller
{
    public function __invoke(JournalEntry $journalEntry)
    {
        $journalEntry->load(['journal', 'lines.account', 'lines.partner', 'createdBy', 'fiscalPeriod']);

        return view('accounting::print.journal-entry', [
            'entry' => $journalEntry,
        ]);
    }
}
