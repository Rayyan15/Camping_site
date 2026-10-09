<?php

/*
| Single source of truth for the roles and permissions matrix (PRD section 2).
| RoleSeeder syncs this file into the database; policies only read permission names.
*/

return [
    /*
    | Models that get the standard ability set. Permission name is the ability, an underscore, then the model.
    */
    'resources' => [
        'activity_log', 'addon', 'attendance', 'booking', 'booking_addon', 'booking_unit',
        'cleaning_log', 'customer', 'dining_spot', 'employee', 'evaluation', 'evaluation_criteria',
        'evaluation_score', 'menu_category', 'menu_item', 'order', 'order_item', 'payment', 'refund',
        'refund_policy', 'setting', 'shift', 'special_price', 'unit', 'unit_block', 'unit_type',
        'unit_type_photo', 'user',
    ],

    'abilities' => ['view_any', 'view', 'create', 'update', 'delete'],

    'functional_permissions' => [
        'view_kitchen_queue',
        'process_orders',
        'create_walkin_orders',
        'manage_menu',
        'toggle_menu_availability',
        'approve_refund',
        'request_refund',
        'view_reports',
        'view_financials',
        'view_occupancy_dashboard',
        'view_occupancy_calendar',
        'record_manual_payment',
        'check_in_booking',
        'check_out_booking',
        'record_order_payment',
        'view_customer_contact',
        'view_customer_spend',
        'manage_own_attendance',
        'review_cleaning_logs',
    ],

    /*
    | Owner receives every permission. Other roles list resource abilities per model and
    | functional permissions explicitly.
    */
    'roles' => [
        'owner' => '*',

        'operator_fo' => [
            'resources' => [
                'booking' => ['view_any', 'view', 'create', 'update'],
                'booking_unit' => ['view_any', 'view'],
                'booking_addon' => ['view_any', 'view'],
                'customer' => ['view_any', 'view', 'create', 'update'],
                'payment' => ['view_any', 'view', 'create'],
                'refund' => ['view_any', 'view', 'create'],
                'order' => ['view_any', 'view'],
                'order_item' => ['view_any', 'view'],
                'unit' => ['view_any', 'view'],
                'unit_type' => ['view_any', 'view'],
                'addon' => ['view_any', 'view'],
                'cleaning_log' => ['view_any', 'view', 'create', 'update'],
            ],
            'functional' => [
                'request_refund',
                'record_manual_payment',
                'check_in_booking',
                'check_out_booking',
                'view_customer_contact',
                'view_customer_spend',
                'view_occupancy_dashboard',
                'view_occupancy_calendar',
                'manage_own_attendance',
            ],
        ],

        'operator_kasir' => [
            'resources' => [
                'booking' => ['view_any', 'view'],
                'customer' => ['view_any', 'view'],
                'order' => ['view_any', 'view', 'create', 'update'],
                'order_item' => ['view_any', 'view', 'create', 'update'],
                'payment' => ['view_any', 'view', 'create'],
                'menu_category' => ['view_any', 'view'],
                'menu_item' => ['view_any', 'view'],
                'dining_spot' => ['view_any', 'view'],
            ],
            'functional' => [
                'view_kitchen_queue',
                'process_orders',
                'create_walkin_orders',
                'toggle_menu_availability',
                'record_order_payment',
                'manage_own_attendance',
            ],
        ],
    ],

    /*
    | Roles that existed before the PRD matrix was enforced. The finance role is not in the PRD
    | and carried no permissions, so its users fall back to the back office role that can still
    | do payment related work, never to owner.
    */
    'legacy_role_map' => [
        'operator' => 'operator_fo',
        'finance' => 'operator_fo',
    ],

    'demo_password' => env('DEMO_PASSWORD'),

    'owner_bootstrap_password' => env('OWNER_PASSWORD'),

    'min_password_length' => 12,

    'owner_two_factor_required' => (bool) env('OWNER_2FA_REQUIRED', env('APP_ENV') === 'production'),
];
