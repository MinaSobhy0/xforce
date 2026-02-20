<?php

namespace Modules\Loyalty\Services;

use Illuminate\Support\Facades\DB;
use Modules\Loyalty\Models\LoyaltyRule;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Loyalty\Models\Referral;
use Modules\Loyalty\Models\ReferralProgram;
use Modules\Patients\Models\Patient;

class LoyaltyService
{
    /**
     * Get the current points balance for a patient.
     */
    public function getBalance(Patient $patient): int
    {
        return $patient->loyalty_points ?? 0;
    }

    /**
     * Award points to a patient.
     */
    public function awardPoints(
        Patient $patient,
        int $points,
        string $type,
        ?string $description = null,
        ?LoyaltyRule $rule = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($patient, $points, $type, $description, $rule, $referenceType, $referenceId, $metadata) {
            // Update patient's points balance
            $patient->increment('loyalty_points', abs($points));
            $newBalance = $patient->fresh()->loyalty_points;

            // Create transaction record
            return LoyaltyTransaction::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'loyalty_rule_id' => $rule?->id,
                'type' => $type,
                'points' => abs($points),
                'running_balance' => $newBalance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
                'expires_at' => $this->calculateExpiryDate(),
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Redeem points from a patient.
     */
    public function redeemPoints(
        Patient $patient,
        int $points,
        ?string $description = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null
    ): LoyaltyTransaction {
        $currentBalance = $this->getBalance($patient);

        if ($points > $currentBalance) {
            throw new \InvalidArgumentException(
                __('loyalty::loyalty.errors.insufficient_points', [
                    'requested' => $points,
                    'available' => $currentBalance,
                ])
            );
        }

        return DB::transaction(function () use ($patient, $points, $description, $referenceType, $referenceId, $metadata) {
            // Deduct from patient's points balance
            $patient->decrement('loyalty_points', abs($points));
            $newBalance = $patient->fresh()->loyalty_points;

            // Create transaction record
            return LoyaltyTransaction::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'type' => LoyaltyTransaction::TYPE_REDEEM,
                'points' => -abs($points),
                'running_balance' => $newBalance,
                'description' => $description ?? __('loyalty::loyalty.transaction_descriptions.redeemed'),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Adjust points (admin action).
     */
    public function adjustPoints(
        Patient $patient,
        int $points,
        string $reason
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($patient, $points, $reason) {
            if ($points > 0) {
                $patient->increment('loyalty_points', $points);
            } else {
                $patient->decrement('loyalty_points', abs($points));
            }

            $newBalance = $patient->fresh()->loyalty_points;

            return LoyaltyTransaction::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'type' => LoyaltyTransaction::TYPE_ADJUST,
                'points' => $points,
                'running_balance' => $newBalance,
                'description' => $reason,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Calculate points for a payment amount.
     */
    public function calculatePointsForPayment(int $amountMinor, ?string $treatmentId = null, ?string $categoryId = null): int
    {
        $rules = LoyaltyRule::active()
            ->byType(LoyaltyRule::TYPE_PER_SPEND)
            ->ordered()
            ->get();

        $totalPoints = 0;

        foreach ($rules as $rule) {
            // Check if rule is specific to treatment/category
            if ($rule->treatment_id && $rule->treatment_id !== $treatmentId) {
                continue;
            }
            if ($rule->treatment_category_id && $rule->treatment_category_id !== $categoryId) {
                continue;
            }

            $points = $rule->calculatePoints($amountMinor);
            if ($points > 0) {
                $totalPoints += $points;
                // If not stacking rules, break after first match
                break;
            }
        }

        return $totalPoints;
    }

    /**
     * Award points for a completed visit.
     */
    public function awardVisitPoints(Patient $patient): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::active()
            ->byType(LoyaltyRule::TYPE_PER_VISIT)
            ->ordered()
            ->first();

        if (!$rule || $rule->points_amount <= 0) {
            return null;
        }

        return $this->awardPoints(
            $patient,
            $rule->points_amount,
            LoyaltyTransaction::TYPE_EARN,
            __('loyalty::loyalty.transaction_descriptions.visit_bonus'),
            $rule
        );
    }

    /**
     * Award birthday bonus points.
     */
    public function awardBirthdayPoints(Patient $patient): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::active()
            ->byType(LoyaltyRule::TYPE_BIRTHDAY)
            ->ordered()
            ->first();

        if (!$rule || $rule->points_amount <= 0) {
            return null;
        }

        // Check if already awarded this year
        $alreadyAwarded = LoyaltyTransaction::forPatient($patient->id)
            ->where('type', LoyaltyTransaction::TYPE_BONUS)
            ->where('loyalty_rule_id', $rule->id)
            ->whereYear('created_at', now()->year)
            ->exists();

        if ($alreadyAwarded) {
            return null;
        }

        return $this->awardPoints(
            $patient,
            $rule->points_amount,
            LoyaltyTransaction::TYPE_BONUS,
            __('loyalty::loyalty.transaction_descriptions.birthday_bonus'),
            $rule
        );
    }

    /**
     * Process referral rewards.
     */
    public function processReferralReward(Referral $referral): void
    {
        if ($referral->status !== Referral::STATUS_COMPLETED) {
            return;
        }

        $program = $referral->referralProgram ?? ReferralProgram::getActiveProgram();

        if (!$program) {
            return;
        }

        DB::transaction(function () use ($referral, $program) {
            // Award referrer points
            if ($program->referrer_points > 0 && $referral->referrerPatient) {
                $this->awardPoints(
                    $referral->referrerPatient,
                    $program->referrer_points,
                    LoyaltyTransaction::TYPE_REFERRAL,
                    __('loyalty::loyalty.transaction_descriptions.referral_bonus_referrer'),
                    null,
                    Referral::class,
                    $referral->id
                );
            }

            // Award referred points
            if ($program->referred_points > 0 && $referral->referredPatient) {
                $this->awardPoints(
                    $referral->referredPatient,
                    $program->referred_points,
                    LoyaltyTransaction::TYPE_REFERRAL,
                    __('loyalty::loyalty.transaction_descriptions.referral_bonus_referred'),
                    null,
                    Referral::class,
                    $referral->id
                );
            }

            // Mark referral as rewarded
            $referral->markAsRewarded(
                $program->referrer_points,
                $program->referred_points
            );
        });
    }

    /**
     * Create a referral for a patient.
     */
    public function createReferral(Patient $referrer): Referral
    {
        $program = ReferralProgram::getActiveProgram();

        return Referral::create([
            'tenant_id' => $referrer->tenant_id,
            'referral_program_id' => $program?->id,
            'referrer_patient_id' => $referrer->id,
            'status' => Referral::STATUS_PENDING,
            'expires_at' => now()->addDays(config('loyalty.referral_expiry_days', 90)),
        ]);
    }

    /**
     * Convert points to currency value (minor units).
     */
    public function pointsToCurrency(int $points): int
    {
        $ratio = config('loyalty.points_redemption_ratio', 100); // 100 points = 1 EGP
        return (int) floor($points / $ratio * 100);
    }

    /**
     * Convert currency to points value.
     */
    public function currencyToPoints(int $amountMinor): int
    {
        $ratio = config('loyalty.points_redemption_ratio', 100);
        return (int) floor($amountMinor / 100 * $ratio);
    }

    /**
     * Calculate points expiry date.
     */
    protected function calculateExpiryDate(): ?\DateTime
    {
        if (!config('loyalty.points_expiry_enabled', true)) {
            return null;
        }

        $months = config('loyalty.points_expiry_months', 24);
        return now()->addMonths($months);
    }

    /**
     * Expire old points.
     */
    public function expireOldPoints(): int
    {
        $count = 0;

        $expiredTransactions = LoyaltyTransaction::credits()
            ->where('expires_at', '<=', now())
            ->whereDoesntHave('patient', function ($q) {
                // Skip if patient already has 0 balance
                $q->where('loyalty_points', '>', 0);
            })
            ->get();

        foreach ($expiredTransactions as $transaction) {
            $patient = $transaction->patient;
            if (!$patient) {
                continue;
            }

            $pointsToExpire = min($transaction->points, $patient->loyalty_points);
            if ($pointsToExpire <= 0) {
                continue;
            }

            DB::transaction(function () use ($patient, $pointsToExpire) {
                $patient->decrement('loyalty_points', $pointsToExpire);
                $newBalance = $patient->fresh()->loyalty_points;

                LoyaltyTransaction::create([
                    'tenant_id' => $patient->tenant_id,
                    'patient_id' => $patient->id,
                    'type' => LoyaltyTransaction::TYPE_EXPIRE,
                    'points' => -$pointsToExpire,
                    'running_balance' => $newBalance,
                    'description' => __('loyalty::loyalty.transaction_descriptions.points_expired'),
                ]);
            });

            $count++;
        }

        return $count;
    }

    /**
     * Get transaction history for a patient.
     */
    public function getTransactionHistory(Patient $patient, int $limit = 20)
    {
        return LoyaltyTransaction::forPatient($patient->id)
            ->recent()
            ->with(['loyaltyRule', 'createdBy'])
            ->limit($limit)
            ->get();
    }
}
