<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BookingItem>
 */
class BookingItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => BookingFactory::new(),
            'equipment_id' => EquipmentFactory::new(),
            'quantity' => 1,
            'subtotal' => fake()->numberBetween(50, 500) * 1000,
        ];
    }
}
