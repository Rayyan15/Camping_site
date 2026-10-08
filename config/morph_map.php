<?php

use App\Models\ActivityLog;
use App\Models\Addon;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\BookingUnit;
use App\Models\CleaningLog;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationScore;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use App\Models\User;

/*
| Morph aliases stored in polymorphic columns (payments.payable_type, activity_logs.subject_type, ...).
| Aliases keep class names out of the database; AppServiceProvider enforces this map.
*/

return [
    'activity_log' => ActivityLog::class,
    'addon' => Addon::class,
    'attendance' => Attendance::class,
    'booking' => Booking::class,
    'booking_addon' => BookingAddon::class,
    'booking_unit' => BookingUnit::class,
    'cleaning_log' => CleaningLog::class,
    'customer' => Customer::class,
    'dining_spot' => DiningSpot::class,
    'employee' => Employee::class,
    'evaluation' => Evaluation::class,
    'evaluation_criteria' => EvaluationCriteria::class,
    'evaluation_score' => EvaluationScore::class,
    'menu_category' => MenuCategory::class,
    'menu_item' => MenuItem::class,
    'order' => Order::class,
    'order_item' => OrderItem::class,
    'payment' => Payment::class,
    'refund' => Refund::class,
    'refund_policy' => RefundPolicy::class,
    'setting' => Setting::class,
    'shift' => Shift::class,
    'special_price' => SpecialPrice::class,
    'unit' => Unit::class,
    'unit_block' => UnitBlock::class,
    'unit_type' => UnitType::class,
    'unit_type_photo' => UnitTypePhoto::class,
    'user' => User::class,
];
