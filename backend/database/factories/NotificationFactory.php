<?php

namespace Database\Factories;

use App\Enums\NotificationEventTypeEnum;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => OrderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => [
                'event_type' => NotificationEventTypeEnum::ORDER_CREATED->value,
                'order_id' => fake()->numberBetween(1, 1000),
                'order_url' => '/orders/1',
                'message' => fake()->sentence(),
            ],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
