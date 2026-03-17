<?php

return [
    'evaluations' => 'Evaluations',
    'evaluation' => 'Evaluation',

    'fields' => [
        'date' => 'Date',
        'patient' => 'Patient',
        'visit' => 'Visit',
        'branch' => 'Branch',
        'overall_rating' => 'Overall Rating',
        'service_quality_rating' => 'Service Quality',
        'staff_friendliness_rating' => 'Staff Friendliness',
        'cleanliness_rating' => 'Cleanliness',
        'wait_time_rating' => 'Wait Time',
        'value_for_money_rating' => 'Value for Money',
        'satisfaction_score' => 'Satisfaction Score',
        'nps_category' => 'NPS Category',
        'would_recommend' => 'Would Recommend',
        'feedback_text' => 'Feedback',
        'improvement_suggestions' => 'Suggestions for Improvement',
        'source' => 'Source',
        'evaluated_by' => 'Evaluated By',
        'has_feedback' => 'Has Feedback',
        'nps' => 'NPS',
    ],

    'sections' => [
        'visit_info' => 'Visit Information',
        'ratings' => 'Ratings',
        'nps' => 'Net Promoter Score',
        'feedback' => 'Patient Feedback',
    ],

    'tabs' => [
        'all' => 'All',
        'positive' => 'Positive',
        'neutral' => 'Neutral',
        'negative' => 'Negative',
        'with_feedback' => 'With Feedback',
    ],

    'sources' => [
        'staff' => 'Staff Entry',
        'kiosk' => 'In-Clinic Kiosk',
        'sms' => 'SMS Survey',
        'email' => 'Email Survey',
        'portal' => 'Patient Portal',
    ],

    'nps' => [
        'promoter' => 'Promoter',
        'passive' => 'Passive',
        'detractor' => 'Detractor',
    ],

    'filters' => [
        'from' => 'From Date',
        'until' => 'Until Date',
    ],

    'actions' => [
        'take_review' => 'Take Review',
        'view_evaluation' => 'View Evaluation',
    ],

    'form' => [
        'overall_rating_helper' => 'How would you rate your overall experience?',
        'service_quality_helper' => 'Quality of the services received',
        'staff_friendliness_helper' => 'How friendly and helpful was our staff?',
        'cleanliness_helper' => 'Cleanliness of the clinic',
        'wait_time_helper' => 'Was the wait time acceptable?',
        'value_for_money_helper' => 'Did you get good value for your money?',
        'satisfaction_score_helper' => 'On a scale of 0-10, how satisfied are you?',
        'would_recommend_helper' => 'Would you recommend us to friends and family?',
        'feedback_placeholder' => 'Share your experience with us...',
        'suggestions_placeholder' => 'How can we improve?',
    ],

    'no_feedback' => 'No feedback provided',
    'no_suggestions' => 'No suggestions provided',

    'report' => [
        'title' => 'Satisfaction Report',
        'average_rating' => 'Average Rating',
        'total_evaluations' => 'Total Evaluations',
        'response_rate' => 'Response Rate',
        'nps_score' => 'NPS Score',
        'positive' => 'Positive',
        'visits' => 'visits',
        'rating_distribution' => 'Rating Distribution',
        'low_ratings_follow_up' => 'Low Ratings for Follow-up',
    ],

    'messages' => [
        'evaluation_created' => 'Evaluation submitted successfully!',
        'already_evaluated' => 'This visit has already been evaluated.',
    ],
];
