<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    public function test_pending_can_be_confirmed_or_cancelled(): void
    {
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Confirmed));
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Cancelled));
    }

    public function test_pending_cannot_jump_straight_to_completed(): void
    {
        $this->assertFalse(BookingStatus::Pending->canTransitionTo(BookingStatus::Completed));
    }

    public function test_confirmed_can_complete_or_cancel(): void
    {
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Completed));
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Cancelled));
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        $this->assertSame([], BookingStatus::Cancelled->allowedTransitions());
        $this->assertSame([], BookingStatus::Completed->allowedTransitions());
        $this->assertTrue(BookingStatus::Cancelled->isTerminal());
        $this->assertTrue(BookingStatus::Completed->isTerminal());
    }

    public function test_only_pending_and_confirmed_are_active(): void
    {
        $this->assertTrue(BookingStatus::Pending->isActive());
        $this->assertTrue(BookingStatus::Confirmed->isActive());
        $this->assertFalse(BookingStatus::Cancelled->isActive());
        $this->assertFalse(BookingStatus::Completed->isActive());
    }
}
