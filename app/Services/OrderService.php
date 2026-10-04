<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Buat pesanan baru dari QR atau Walk-in atau Pre-order
     */
    public function createOrder($source, $items, $bookingId = null, $diningSpotId = null, $scheduledAt = null)
    {
        return DB::transaction(function () use ($source, $items, $bookingId, $diningSpotId, $scheduledAt) {
            
            $total = 0;
            $orderItems = [];

            // Validasi menu & hitung total
            foreach ($items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                
                if (!$menuItem->is_available) {
                    throw new Exception("Menu {$menuItem->name} sedang tidak tersedia.");
                }

                $subtotal = $menuItem->price * $item['qty'];
                $total += $subtotal;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'qty' => $item['qty'],
                    'price' => $menuItem->price,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // Buat record Order utama
            $order = Order::create([
                'code' => 'ORD-' . now()->format('ymd') . '-' . rand(1000, 9999),
                'source' => $source,
                'booking_id' => $bookingId,
                'dining_spot_id' => $diningSpotId,
                'scheduled_at' => $scheduledAt,
                'status' => 'baru',
                'total' => $total,
                'payment_status' => 'unpaid',
                'bill_to_booking' => ($source === 'preorder' || !empty($bookingId)),
            ]);

            // Simpan detail OrderItem
            foreach ($orderItems as $orderItem) {
                OrderItem::create(array_merge($orderItem, ['order_id' => $order->id]));
            }

            return $order;
        });
    }

    /**
     * Ubah status antrian pesanan di dapur
     */
    public function updateStatus(Order $order, $newStatus)
    {
        $validStatuses = ['baru', 'diproses', 'siap', 'diantar', 'selesai'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception("Status pesanan tidak valid.");
        }

        $order->update(['status' => $newStatus]);
        
        return $order;
    }
}
