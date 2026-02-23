@php
    $rules = $getState() ?? [];
    $type = $type ?? 'earnings';
    $currency = current_currency();

    // Filter rules based on type
    // Exclude 'gross' and 'basic' summary types to avoid double-counting
    $filteredRules = collect($rules)->filter(function ($rule) use ($type) {
        $categoryType = $rule['category_type'] ?? 'earning';
        $ruleCode = $rule['rule_code'] ?? '';

        // Skip gross/net totals as they are summary values, not individual earnings
        if (in_array($categoryType, ['gross', 'net'])) {
            return false;
        }

        if ($type === 'earnings') {
            return in_array($categoryType, ['earning', 'allowance', 'benefit']);
        } else {
            return in_array($categoryType, ['deduction', 'tax', 'social_insurance']);
        }
    });

    $total = $filteredRules->sum('amount_minor') / 100;
@endphp

@if($filteredRules->isNotEmpty())
<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                    {{ __('payroll::payroll.fields.rule_code') }}
                </th>
                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                    {{ __('payroll::payroll.fields.rule_name') }}
                </th>
                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">
                    {{ __('payroll::payroll.fields.amount') }}
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($filteredRules as $rule)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400">
                    {{ $rule['rule_code'] ?? '-' }}
                </td>
                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                    {{ $rule['rule_name'] ?? '-' }}
                </td>
                <td class="px-4 py-3 text-right font-medium {{ $type === 'earnings' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ format_money($rule['amount_minor'] ?? 0) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-100 dark:bg-gray-700">
            <tr>
                <td colspan="2" class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                    {{ $type === 'earnings' ? __('payroll::payroll.fields.total_earnings') : __('payroll::payroll.fields.total_deductions') }}
                </td>
                <td class="px-4 py-3 text-right font-bold {{ $type === 'earnings' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ format_money($total * 100) }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>
@else
<div class="py-4 text-center text-gray-500 dark:text-gray-400">
    {{ $type === 'earnings' ? __('payroll::payroll.messages.no_earnings') : __('payroll::payroll.messages.no_deductions') }}
</div>
@endif
