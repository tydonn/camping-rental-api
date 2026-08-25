<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 weeks');

        return [
            'user_id' => UserFactory::new()->pelanggan(),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+2 days'),
            'status' => Booking::STATUS_PENDING,
            'total_amount' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_PAID]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_CONFIRMED]);
    }
}
