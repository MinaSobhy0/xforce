<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceRule;
use Modules\Attendance\Models\AttendanceRuleAction;
use Modules\Attendance\Models\WorkingSchedule;

class WorkingScheduleService
{
    /**
     * Create a working schedule with default rules.
     */
    public function createWithDefaultRules(array $data): WorkingSchedule
    {
        return DB::transaction(function () use ($data) {
            $schedule = WorkingSchedule::create($data);

            if ($data['create_default_rules'] ?? true) {
                $this->createDefaultRules($schedule);
            }

            return $schedule->fresh(['rules.actions']);
        });
    }

    /**
     * Update a working schedule.
     */
    public function updateSchedule(WorkingSchedule $schedule, array $data): WorkingSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule->update($data);

            return $schedule->fresh();
        });
    }

    /**
     * Update schedule rules.
     */
    public function updateScheduleRules(WorkingSchedule $schedule, array $rulesData): WorkingSchedule
    {
        return DB::transaction(function () use ($schedule, $rulesData) {
            foreach ($rulesData as $ruleData) {
                if (isset($ruleData['id'])) {
                    // Update existing rule
                    $rule = AttendanceRule::find($ruleData['id']);
                    if ($rule) {
                        $rule->update($ruleData);

                        // Update actions if provided
                        if (isset($ruleData['actions'])) {
                            $this->updateRuleActions($rule, $ruleData['actions']);
                        }
                    }
                } else {
                    // Create new rule
                    $ruleData['tenant_id'] = $schedule->tenant_id;
                    $ruleData['working_schedule_id'] = $schedule->id;
                    $rule = AttendanceRule::create($ruleData);

                    // Create actions if provided
                    if (isset($ruleData['actions'])) {
                        foreach ($ruleData['actions'] as $actionData) {
                            $actionData['attendance_rule_id'] = $rule->id;
                            AttendanceRuleAction::create($actionData);
                        }
                    }
                }
            }

            return $schedule->fresh(['rules.actions']);
        });
    }

    /**
     * Update rule actions.
     */
    protected function updateRuleActions(AttendanceRule $rule, array $actionsData): void
    {
        $existingActionIds = [];

        foreach ($actionsData as $actionData) {
            if (isset($actionData['id'])) {
                // Update existing action
                $action = AttendanceRuleAction::find($actionData['id']);
                if ($action) {
                    $action->update($actionData);
                    $existingActionIds[] = $action->id;
                }
            } else {
                // Create new action
                $actionData['attendance_rule_id'] = $rule->id;
                $action = AttendanceRuleAction::create($actionData);
                $existingActionIds[] = $action->id;
            }
        }

        // Delete removed actions
        $rule->actions()->whereNotIn('id', $existingActionIds)->delete();
    }

    /**
     * Check if a schedule can be deleted.
     */
    public function canDeleteSchedule(WorkingSchedule $schedule): bool
    {
        // Check if schedule is assigned to any attendance records
        if ($schedule->attendances()->exists()) {
            return false;
        }

        // Check if it's the only active schedule
        $activeCount = WorkingSchedule::where('tenant_id', $schedule->tenant_id)
            ->where('status', WorkingSchedule::STATUS_ACTIVE)
            ->count();

        if ($activeCount <= 1 && $schedule->status === WorkingSchedule::STATUS_ACTIVE) {
            return false;
        }

        return true;
    }

    /**
     * Delete a schedule.
     */
    public function deleteSchedule(WorkingSchedule $schedule): bool
    {
        if (!$this->canDeleteSchedule($schedule)) {
            return false;
        }

        return DB::transaction(function () use ($schedule) {
            // Delete associated rules and actions
            foreach ($schedule->rules as $rule) {
                $rule->actions()->delete();
            }
            $schedule->rules()->delete();

            // Delete schedule
            return $schedule->delete();
        });
    }

    /**
     * Duplicate a schedule.
     */
    public function duplicateSchedule(
        WorkingSchedule $schedule,
        string $name,
        string $code
    ): WorkingSchedule {
        return DB::transaction(function () use ($schedule, $name, $code) {
            // Create new schedule
            $newSchedule = $schedule->duplicate($name, $code);

            // Duplicate rules and actions
            foreach ($schedule->rules as $rule) {
                $newRule = $rule->replicate();
                $newRule->working_schedule_id = $newSchedule->id;
                $newRule->code = $rule->code . '_copy';
                $newRule->save();

                // Duplicate actions
                foreach ($rule->actions as $action) {
                    $newAction = $action->replicate();
                    $newAction->attendance_rule_id = $newRule->id;
                    $newAction->save();
                }
            }

            return $newSchedule->fresh(['rules.actions']);
        });
    }

    /**
     * Create default attendance rules for a schedule.
     */
    public function createDefaultRules(WorkingSchedule $schedule): void
    {
        $defaultRules = [
            [
                'name' => 'Late Check-In Rule',
                'code' => 'LATE_' . strtoupper($schedule->code),
                'category' => AttendanceRule::CATEGORY_LATE_CHECKIN,
                'description' => 'Default rule for late check-in violations',
                'auto_apply' => false,
                'send_notification' => true,
                'notify_manager' => true,
                'actions' => [
                    [
                        'action_type' => AttendanceRuleAction::ACTION_WARNING,
                        'severity' => AttendanceRuleAction::SEVERITY_MINOR,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 15, // 15 minutes
                        'notification_enabled' => true,
                    ],
                    [
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 30, // 30 minutes
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 25, // 25% of daily salary
                        'notification_enabled' => true,
                        'notify_manager' => true,
                    ],
                    [
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_SEVERE,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 60, // 1 hour
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 50, // 50% of daily salary
                        'notification_enabled' => true,
                        'notify_manager' => true,
                        'notify_hr' => true,
                    ],
                ],
            ],
            [
                'name' => 'Early Check-Out Rule',
                'code' => 'EARLY_' . strtoupper($schedule->code),
                'category' => AttendanceRule::CATEGORY_EARLY_CHECKOUT,
                'description' => 'Default rule for early check-out violations',
                'auto_apply' => false,
                'send_notification' => true,
                'notify_manager' => true,
                'actions' => [
                    [
                        'action_type' => AttendanceRuleAction::ACTION_WARNING,
                        'severity' => AttendanceRuleAction::SEVERITY_MINOR,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 15, // 15 minutes early
                        'notification_enabled' => true,
                    ],
                    [
                        'action_type' => AttendanceRuleAction::ACTION_DEDUCTION,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_TIME,
                        'threshold_value' => 30, // 30 minutes early
                        'penalty_type' => AttendanceRuleAction::PENALTY_PERCENTAGE,
                        'penalty_percentage' => 25,
                        'notification_enabled' => true,
                        'notify_manager' => true,
                    ],
                ],
            ],
            [
                'name' => 'Missed Check-In Rule',
                'code' => 'MISS_IN_' . strtoupper($schedule->code),
                'category' => AttendanceRule::CATEGORY_MISSED_CHECKIN,
                'description' => 'Default rule for missed check-in violations',
                'auto_apply' => false,
                'send_notification' => true,
                'notify_manager' => true,
                'notify_hr' => true,
                'actions' => [
                    [
                        'action_type' => AttendanceRuleAction::ACTION_APPROVAL_REQUIRED,
                        'severity' => AttendanceRuleAction::SEVERITY_MODERATE,
                        'threshold_type' => AttendanceRuleAction::THRESHOLD_OCCURRENCE,
                        'threshold_value' => 1, // First occurrence
                        'requires_approval' => true,
                        'notification_enabled' => true,
                        'notify_manager' => true,
                    ],
                ],
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            $actions = $ruleData['actions'];
            unset($ruleData['actions']);

            $ruleData['tenant_id'] = $schedule->tenant_id;
            $ruleData['working_schedule_id'] = $schedule->id;
            $ruleData['created_by'] = auth()->id();

            $rule = AttendanceRule::create($ruleData);

            foreach ($actions as $actionData) {
                $actionData['attendance_rule_id'] = $rule->id;
                AttendanceRuleAction::create($actionData);
            }
        }
    }

    /**
     * Set schedule as default for tenant/branch.
     */
    public function setAsDefault(WorkingSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            // Remove default from other schedules
            WorkingSchedule::where('tenant_id', $schedule->tenant_id)
                ->where('id', '!=', $schedule->id)
                ->where(function ($query) use ($schedule) {
                    if ($schedule->branch_id) {
                        $query->where('branch_id', $schedule->branch_id);
                    } else {
                        $query->whereNull('branch_id');
                    }
                })
                ->update(['is_default' => false]);

            // Set this schedule as default
            $schedule->is_default = true;
            $schedule->save();
        });
    }

    /**
     * Get available schedule types with descriptions.
     */
    public function getScheduleTypes(): array
    {
        return [
            WorkingSchedule::TYPE_FIXED => [
                'label' => 'Fixed Hours',
                'description' => 'Standard fixed working hours (e.g., 9 AM - 5 PM)',
            ],
            WorkingSchedule::TYPE_FLEXIBLE => [
                'label' => 'Flexible Hours',
                'description' => 'Flexible start/end times with core hours requirement',
            ],
            WorkingSchedule::TYPE_SHIFT => [
                'label' => 'Shift Work',
                'description' => 'Rotating shifts (morning, evening, night)',
            ],
            WorkingSchedule::TYPE_COMPRESSED => [
                'label' => 'Compressed Week',
                'description' => 'Longer days with fewer days per week (e.g., 4x10)',
            ],
            WorkingSchedule::TYPE_REMOTE => [
                'label' => 'Remote',
                'description' => 'Remote work schedule with flexible hours',
            ],
        ];
    }

    /**
     * Validate schedule configuration.
     */
    public function validateSchedule(array $data): array
    {
        $errors = [];

        // Validate fixed schedule times
        if (($data['type'] ?? WorkingSchedule::TYPE_FIXED) === WorkingSchedule::TYPE_FIXED) {
            if (empty($data['start_time'])) {
                $errors[] = 'Start time is required for fixed schedules';
            }
            if (empty($data['end_time'])) {
                $errors[] = 'End time is required for fixed schedules';
            }
        }

        // Validate flexible schedule
        if (($data['type'] ?? '') === WorkingSchedule::TYPE_FLEXIBLE) {
            if ($data['is_flexible'] ?? false) {
                if (empty($data['flexible_start_from']) || empty($data['flexible_start_to'])) {
                    $errors[] = 'Flexible start time range is required';
                }
            }
        }

        // Validate core hours
        if ($data['core_hours_required'] ?? false) {
            if (empty($data['core_hours_start']) || empty($data['core_hours_end'])) {
                $errors[] = 'Core hours start and end times are required';
            }
        }

        // Validate working days
        $workingDays = $data['working_days'] ?? [];
        if (empty($workingDays)) {
            $errors[] = 'At least one working day must be selected';
        }

        // Validate break settings
        if ($data['has_break'] ?? true) {
            if (empty($data['break_duration_minutes']) || $data['break_duration_minutes'] < 0) {
                $errors[] = 'Valid break duration is required';
            }
        }

        return $errors;
    }
}
