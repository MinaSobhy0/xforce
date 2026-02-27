<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\AttendanceRule;
use Modules\Attendance\Models\AttendanceRuleAction;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;

class AttendanceRuleSeeder extends Seeder
{
    use ResolveTenantId;

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        $rules = [
            // Late Check-In Rule
            [
                'code' => 'LATE-CHECKIN',
                'name' => 'Late Check-In Policy',
                'description' => 'Policy for handling late arrivals',
                'category' => AttendanceRule::CATEGORY_LATE_CHECKIN,
                'auto_apply' => true,
                'send_notification' => true,
                'notify_manager' => false,
                'notify_hr' => false,
                'sequence' => 1,
                'actions' => [
                    // Grace period (first 15 minutes) - Warning
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 15,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_WARNING,
                        'severity' => AttendanceRuleAction::SEVERITY_MINOR,
                        'penalty_type' => null,
                        'penalty_amount_minor' => 0,
                        'notes' => 'Grace period - warning only',
                    ],
                    // 15-30 minutes late - Deduction
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 30,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_HOURLY,
                        'penalty_amount_minor' => 0,
                        'notes' => '30 minute hourly rate deduction',
                    ],
                    // 30-60 minutes late - Higher Deduction
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 60,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 25,
                        'penalty_amount_minor' => 0,
                        'notes' => '25% of daily salary deduction',
                    ],
                    // More than 120 minutes late - Block + Higher Deduction
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 120,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 50,
                        'penalty_amount_minor' => 0,
                        'notes' => '50% of daily salary (half day absence)',
                    ],
                ],
            ],

            // Early Check-Out Rule
            [
                'code' => 'EARLY-CHECKOUT',
                'name' => 'Early Check-Out Policy',
                'description' => 'Policy for handling early departures',
                'category' => AttendanceRule::CATEGORY_EARLY_CHECKOUT,
                'auto_apply' => true,
                'send_notification' => true,
                'notify_manager' => true,
                'notify_hr' => false,
                'sequence' => 2,
                'actions' => [
                    // 15-30 minutes early - Warning
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 30,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_WARNING,
                        'severity' => AttendanceRuleAction::SEVERITY_MINOR,
                        'penalty_type' => null,
                        'penalty_amount_minor' => 0,
                        'notes' => 'Warning only',
                    ],
                    // 30-60 minutes early - Hourly Deduction
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 60,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_HOURLY,
                        'penalty_amount_minor' => 0,
                        'notes' => '1 hour deduction',
                    ],
                    // More than 2 hours early - Half day
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 120,
                        'threshold_period' => AttendanceRuleAction::PERIOD_DAY,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 50,
                        'penalty_amount_minor' => 0,
                        'notes' => 'Count as half day absence',
                    ],
                ],
            ],

            // Missed Check-In
            [
                'code' => 'MISSED-CHECKIN',
                'name' => 'Missed Check-In Policy',
                'description' => 'Policy for missed check-ins',
                'category' => AttendanceRule::CATEGORY_MISSED_CHECKIN,
                'auto_apply' => false, // Manual review required
                'send_notification' => true,
                'notify_manager' => true,
                'notify_hr' => true,
                'sequence' => 3,
                'actions' => [
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_OCCURRENCE,
                        'threshold_value' => 1,
                        'threshold_period' => AttendanceRuleAction::PERIOD_MONTH,
                        'action_type' => AttendanceRuleAction::ACTION_APPROVAL_REQUIRED,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'penalty_type' => null,
                        'penalty_amount_minor' => 0,
                        'notes' => 'Requires manager approval',
                    ],
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_OCCURRENCE,
                        'threshold_value' => 3,
                        'threshold_period' => AttendanceRuleAction::PERIOD_MONTH,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 100,
                        'penalty_amount_minor' => 0,
                        'notify_hr' => true,
                        'notes' => 'Full day deduction + HR escalation',
                    ],
                ],
            ],

            // Unauthorized Absence
            [
                'code' => 'UNAUTH-ABSENCE',
                'name' => 'Unauthorized Absence Policy',
                'description' => 'Policy for unauthorized absences',
                'category' => AttendanceRule::CATEGORY_UNAUTHORIZED_ABSENCE,
                'auto_apply' => false,
                'send_notification' => true,
                'notify_manager' => true,
                'notify_hr' => true,
                'sequence' => 4,
                'actions' => [
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_OCCURRENCE,
                        'threshold_value' => 1,
                        'threshold_period' => AttendanceRuleAction::PERIOD_MONTH,
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 100,
                        'penalty_amount_minor' => 0,
                        'notes' => 'Full day deduction',
                    ],
                    [
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_OCCURRENCE,
                        'threshold_value' => 3,
                        'threshold_period' => AttendanceRuleAction::PERIOD_MONTH,
                        'action_type' => AttendanceRuleAction::ACTION_BLOCK,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 100,
                        'penalty_amount_minor' => 0,
                        'notify_hr' => true,
                        'notes' => 'Block attendance + HR disciplinary action',
                    ],
                ],
            ],
        ];

        foreach ($rules as $ruleData) {
            $actions = $ruleData['actions'];
            unset($ruleData['actions']);

            $existing = AttendanceRule::where('code', $ruleData['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                $rule = AttendanceRule::create(array_merge($ruleData, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]));

                // Create actions for this rule
                $occurrenceNumber = 1;
                foreach ($actions as $actionData) {
                    $actionData['occurrence_number'] = $occurrenceNumber++;
                    $actionData['notification_enabled'] = true;
                    $actionData['notify_manager'] = $actionData['notify_manager'] ?? true;
                    $actionData['notify_hr'] = $actionData['notify_hr'] ?? false;
                    $actionData['is_active'] = true;

                    $rule->actions()->create($actionData);
                }
            }
        }
    }
}
