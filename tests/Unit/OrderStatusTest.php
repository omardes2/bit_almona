<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_the_happy_path_is_allowed(): void
    {
        $this->assertTrue(OrderStatus::New->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Preparing));
        $this->assertTrue(OrderStatus::Preparing->canTransitionTo(OrderStatus::OutForDelivery));
        $this->assertTrue(OrderStatus::OutForDelivery->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_skipping_steps_or_leaving_final_states_is_not_allowed(): void
    {
        $this->assertFalse(OrderStatus::New->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::New));
        $this->assertTrue(OrderStatus::Delivered->isFinal());
    }
}
