<?php

return [
    // Module info
    'module_name' => 'Loyalty',
    'module_description' => 'Loyalty points system with rewards and referrals',

    // Navigation
    'navigation_label' => 'Loyalty Program',
    'navigation_group' => 'Sales',

    // Resources
    'loyalty_rules' => 'Loyalty Rules',
    'loyalty_rule' => 'Loyalty Rule',
    'loyalty_transactions' => 'Point Transactions',
    'loyalty_transaction' => 'Point Transaction',
    'referral_programs' => 'Referral Programs',
    'referral_program' => 'Referral Program',
    'referrals' => 'Referrals',
    'referral' => 'Referral',

    // Fields
    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'type' => 'Type',
        'points_amount' => 'Points Amount',
        'points_per_currency_unit' => 'Points per Currency Unit',
        'min_spend' => 'Minimum Spend',
        'max_points' => 'Maximum Points',
        'treatment' => 'Treatment',
        'treatment_category' => 'Treatment Category',
        'multiplier' => 'Multiplier',
        'conditions' => 'Conditions',
        'is_active' => 'Active',
        'priority' => 'Priority',
        'starts_at' => 'Starts At',
        'ends_at' => 'Ends At',
        'patient' => 'Patient',
        'points' => 'Points',
        'running_balance' => 'Running Balance',
        'reference' => 'Reference',
        'expires_at' => 'Expires At',
        'created_by' => 'Created By',
        'code' => 'Code',
        'referrer' => 'Referrer',
        'referred' => 'Referred',
        'status' => 'Status',
        'referrer_points' => 'Referrer Points',
        'referred_points' => 'Referred Points',
        'referrer_discount' => 'Referrer Discount %',
        'referred_discount' => 'Referred Discount %',
        'require_first_purchase' => 'Require First Purchase',
        'loyalty_points' => 'Loyalty Points',
        'lifetime_points' => 'Lifetime Points',
        'tier' => 'Tier',
    ],

    // Sections
    'sections' => [
        'basic_info' => 'Basic Information',
        'points_config' => 'Points Configuration',
        'targeting' => 'Targeting',
        'validity' => 'Validity Period',
        'rewards' => 'Rewards',
        'requirements' => 'Requirements',
        'transaction_details' => 'Transaction Details',
    ],

    // Rule types
    'rule_types' => [
        'per_spend' => 'Per Spend',
        'per_visit' => 'Per Visit',
        'referral' => 'Referral',
        'birthday' => 'Birthday',
        'signup' => 'Sign Up',
        'first_purchase' => 'First Purchase',
    ],

    // Transaction types
    'transaction_types' => [
        'earn' => 'Earned',
        'redeem' => 'Redeemed',
        'expire' => 'Expired',
        'adjust' => 'Adjusted',
        'refund' => 'Refunded',
        'bonus' => 'Bonus',
        'referral' => 'Referral',
    ],

    // Referral statuses
    'referral_statuses' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'rewarded' => 'Rewarded',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    // Tiers
    'tiers' => [
        'bronze' => 'Bronze',
        'silver' => 'Silver',
        'gold' => 'Gold',
        'platinum' => 'Platinum',
        'diamond' => 'Diamond',
    ],

    // Actions
    'actions' => [
        'earn_points' => 'Earn Points',
        'redeem_points' => 'Redeem Points',
        'adjust_points' => 'Adjust Points',
        'create_referral' => 'Create Referral Code',
        'process_referral' => 'Process Referral',
        'view_history' => 'View History',
    ],

    // Transaction descriptions
    'transaction_descriptions' => [
        'payment_bonus' => 'Points earned from payment of :amount EGP',
        'visit_bonus' => 'Points earned for completed visit',
        'birthday_bonus' => 'Birthday bonus points',
        'first_purchase_bonus' => 'Welcome bonus for first purchase',
        'referral_bonus_referrer' => 'Referral bonus - referred a friend',
        'referral_bonus_referred' => 'Welcome bonus - referred by a friend',
        'redeemed' => 'Points redeemed',
        'points_expired' => 'Points expired due to inactivity',
        'manual_adjustment' => 'Manual adjustment by admin',
        'invoice_payment' => 'Points used for invoice :invoice payment',
    ],

    // Messages
    'messages' => [
        'points_earned' => ':points points earned successfully',
        'points_redeemed' => ':points points redeemed successfully',
        'points_adjusted' => 'Points adjusted successfully',
        'referral_created' => 'Referral code created: :code',
        'referral_completed' => 'Referral completed and rewards awarded',
        'insufficient_points' => 'Insufficient points. You have :available points but need :required.',
    ],

    // Errors
    'errors' => [
        'insufficient_points' => 'Insufficient points. Requested: :requested, Available: :available',
        'invalid_referral' => 'Invalid or expired referral code',
        'self_referral' => 'You cannot refer yourself',
        'already_referred' => 'This patient has already been referred',
        'max_referrals_reached' => 'Maximum referrals limit reached',
    ],

    // Stats
    'stats' => [
        'total_points_earned' => 'Total Points Earned',
        'total_points_redeemed' => 'Total Points Redeemed',
        'active_members' => 'Active Members',
        'referrals_this_month' => 'Referrals This Month',
        'points_expiring_soon' => 'Points Expiring Soon',
    ],

    // Widgets
    'widgets' => [
        'points_summary' => 'Points Summary',
        'recent_transactions' => 'Recent Transactions',
        'top_earners' => 'Top Point Earners',
        'referral_leaderboard' => 'Referral Leaderboard',
    ],

    // Notifications
    'notifications' => [
        'points_earned_title' => 'Points Earned!',
        'points_earned_body' => 'You earned :points loyalty points. Your balance is now :balance points.',
        'tier_upgrade_title' => 'Congratulations! Tier Upgrade',
        'tier_upgrade_body' => 'You have been upgraded to :tier tier! Enjoy your new benefits.',
        'points_expiring_title' => 'Points Expiring Soon',
        'points_expiring_body' => 'You have :points points expiring on :date. Use them before they expire!',
    ],
];
