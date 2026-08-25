<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => BookingFactory::new()->paid(),
            'method' => fake()->randomElement(Payment::METHODS),
            'status' => Payment::STATUS_PENDING,
            'note' => null,
            'amount' => fake()->numberBetween(100, 900) * 1000,
            'paid_at' => now(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
