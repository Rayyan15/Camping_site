<?php
$migrationsDir = __DIR__ . '/database/migrations/';
$files = scandir($migrationsDir);

$order = [
    'create_unit_types_table',
    'create_unit_type_photos_table',
    'create_units_table',
    'create_special_prices_table',
    'create_unit_blocks_table',
    'create_customers_table',
    'create_bookings_table',
    'create_booking_units_table',
    'create_addons_table',
    'create_booking_addons_table',
    'create_menu_categories_table',
    'create_menu_items_table',
    'create_dining_spots_table',
    'create_orders_table',
    'create_order_items_table',
    'create_payments_table',
    'create_refund_policies_table',
    'create_refunds_table',
    'create_shifts_table',
    'create_employees_table',
    'create_attendances_table',
    'create_evaluation_criterias_table',
    'create_evaluations_table',
    'create_evaluation_scores_table',
    'create_activity_logs_table',
    'create_settings_table',
];

$second = 10;
foreach ($order as $table) {
    foreach ($files as $file) {
        if (str_contains($file, $table)) {
            $timestamp = sprintf("2026_10_04_1250%02d", $second);
            $newName = $timestamp . "_" . $table . ".php";
            if ($file !== $newName) {
                rename($migrationsDir . $file, $migrationsDir . $newName);
            }
            $second++;
            break;
        }
    }
}
echo "Renamed migrations successfully.\n";
