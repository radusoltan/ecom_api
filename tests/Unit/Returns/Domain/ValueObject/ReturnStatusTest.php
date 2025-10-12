<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\ValueObject;

use App\Returns\Domain\ValueObject\ReturnStatus;
use PHPUnit\Framework\TestCase;

final class ReturnStatusTest extends TestCase
{
    // Factory Method Tests

    public function testRequestedCreatesRequestedStatus(): void
    {
        $status = ReturnStatus::requested();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('requested', $status->value());
        $this->assertTrue($status->isRequested());
    }

    public function testApprovedCreatesApprovedStatus(): void
    {
        $status = ReturnStatus::approved();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('approved', $status->value());
        $this->assertTrue($status->isApproved());
    }

    public function testReceivedCreatesReceivedStatus(): void
    {
        $status = ReturnStatus::received();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('received', $status->value());
        $this->assertTrue($status->isReceived());
    }

    public function testInspectedCreatesInspectedStatus(): void
    {
        $status = ReturnStatus::inspected();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('inspected', $status->value());
        $this->assertTrue($status->isInspected());
    }

    public function testCompletedCreatesCompletedStatus(): void
    {
        $status = ReturnStatus::completed();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('completed', $status->value());
        $this->assertTrue($status->isCompleted());
    }

    public function testRejectedCreatesRejectedStatus(): void
    {
        $status = ReturnStatus::rejected();

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('rejected', $status->value());
        $this->assertTrue($status->isRejected());
    }

    // FromString Tests

    public function testFromStringWithValidValue(): void
    {
        $status = ReturnStatus::fromString('requested');

        $this->assertInstanceOf(ReturnStatus::class, $status);
        $this->assertEquals('requested', $status->value());
    }

    public function testFromStringThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid return status: "invalid_status"');

        ReturnStatus::fromString('invalid_status');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid return status: ""');

        ReturnStatus::fromString('');
    }

    public function testFromStringIsCaseSensitive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReturnStatus::fromString('REQUESTED');
    }

    public function testFromStringAcceptsAllValidStatuses(): void
    {
        $validStatuses = [
            'requested',
            'approved',
            'received',
            'inspected',
            'completed',
            'rejected',
        ];

        foreach ($validStatuses as $statusValue) {
            $status = ReturnStatus::fromString($statusValue);
            $this->assertEquals($statusValue, $status->value());
        }
    }

    // State Machine Transition Tests

    public function testRequestedCanTransitionToApproved(): void
    {
        $current = ReturnStatus::requested();
        $next = ReturnStatus::approved();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testRequestedCanTransitionToRejected(): void
    {
        $current = ReturnStatus::requested();
        $next = ReturnStatus::rejected();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testRequestedCannotTransitionToReceived(): void
    {
        $current = ReturnStatus::requested();
        $next = ReturnStatus::received();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testRequestedCannotTransitionToInspected(): void
    {
        $current = ReturnStatus::requested();
        $next = ReturnStatus::inspected();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testRequestedCannotTransitionToCompleted(): void
    {
        $current = ReturnStatus::requested();
        $next = ReturnStatus::completed();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testApprovedCanTransitionToReceived(): void
    {
        $current = ReturnStatus::approved();
        $next = ReturnStatus::received();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testApprovedCannotTransitionToRequested(): void
    {
        $current = ReturnStatus::approved();
        $next = ReturnStatus::requested();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testApprovedCannotTransitionToInspected(): void
    {
        $current = ReturnStatus::approved();
        $next = ReturnStatus::inspected();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testApprovedCannotTransitionToCompleted(): void
    {
        $current = ReturnStatus::approved();
        $next = ReturnStatus::completed();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testApprovedCannotTransitionToRejected(): void
    {
        $current = ReturnStatus::approved();
        $next = ReturnStatus::rejected();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testReceivedCanTransitionToInspected(): void
    {
        $current = ReturnStatus::received();
        $next = ReturnStatus::inspected();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testReceivedCannotTransitionToRequested(): void
    {
        $current = ReturnStatus::received();
        $next = ReturnStatus::requested();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testReceivedCannotTransitionToApproved(): void
    {
        $current = ReturnStatus::received();
        $next = ReturnStatus::approved();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testReceivedCannotTransitionToCompleted(): void
    {
        $current = ReturnStatus::received();
        $next = ReturnStatus::completed();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testReceivedCannotTransitionToRejected(): void
    {
        $current = ReturnStatus::received();
        $next = ReturnStatus::rejected();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testInspectedCanTransitionToCompleted(): void
    {
        $current = ReturnStatus::inspected();
        $next = ReturnStatus::completed();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testInspectedCanTransitionToRejected(): void
    {
        $current = ReturnStatus::inspected();
        $next = ReturnStatus::rejected();

        $this->assertTrue($current->canTransitionTo($next));
    }

    public function testInspectedCannotTransitionToRequested(): void
    {
        $current = ReturnStatus::inspected();
        $next = ReturnStatus::requested();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testInspectedCannotTransitionToApproved(): void
    {
        $current = ReturnStatus::inspected();
        $next = ReturnStatus::approved();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testInspectedCannotTransitionToReceived(): void
    {
        $current = ReturnStatus::inspected();
        $next = ReturnStatus::received();

        $this->assertFalse($current->canTransitionTo($next));
    }

    public function testCompletedCannotTransitionToAnyStatus(): void
    {
        $current = ReturnStatus::completed();

        $this->assertFalse($current->canTransitionTo(ReturnStatus::requested()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::approved()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::received()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::inspected()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::rejected()));
    }

    public function testRejectedCannotTransitionToAnyStatus(): void
    {
        $current = ReturnStatus::rejected();

        $this->assertFalse($current->canTransitionTo(ReturnStatus::requested()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::approved()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::received()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::inspected()));
        $this->assertFalse($current->canTransitionTo(ReturnStatus::completed()));
    }

    // Equals Tests

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $status1 = ReturnStatus::requested();
        $status2 = ReturnStatus::fromString('requested');

        $this->assertTrue($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $status1 = ReturnStatus::requested();
        $status2 = ReturnStatus::approved();

        $this->assertFalse($status1->equals($status2));
    }

    // Status Check Tests

    public function testIsRequestedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::approved()->isRequested());
        $this->assertFalse(ReturnStatus::received()->isRequested());
        $this->assertFalse(ReturnStatus::inspected()->isRequested());
        $this->assertFalse(ReturnStatus::completed()->isRequested());
        $this->assertFalse(ReturnStatus::rejected()->isRequested());
    }

    public function testIsApprovedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::requested()->isApproved());
        $this->assertFalse(ReturnStatus::received()->isApproved());
        $this->assertFalse(ReturnStatus::inspected()->isApproved());
        $this->assertFalse(ReturnStatus::completed()->isApproved());
        $this->assertFalse(ReturnStatus::rejected()->isApproved());
    }

    public function testIsReceivedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::requested()->isReceived());
        $this->assertFalse(ReturnStatus::approved()->isReceived());
        $this->assertFalse(ReturnStatus::inspected()->isReceived());
        $this->assertFalse(ReturnStatus::completed()->isReceived());
        $this->assertFalse(ReturnStatus::rejected()->isReceived());
    }

    public function testIsInspectedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::requested()->isInspected());
        $this->assertFalse(ReturnStatus::approved()->isInspected());
        $this->assertFalse(ReturnStatus::received()->isInspected());
        $this->assertFalse(ReturnStatus::completed()->isInspected());
        $this->assertFalse(ReturnStatus::rejected()->isInspected());
    }

    public function testIsCompletedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::requested()->isCompleted());
        $this->assertFalse(ReturnStatus::approved()->isCompleted());
        $this->assertFalse(ReturnStatus::received()->isCompleted());
        $this->assertFalse(ReturnStatus::inspected()->isCompleted());
        $this->assertFalse(ReturnStatus::rejected()->isCompleted());
    }

    public function testIsRejectedReturnsFalseForOtherStatuses(): void
    {
        $this->assertFalse(ReturnStatus::requested()->isRejected());
        $this->assertFalse(ReturnStatus::approved()->isRejected());
        $this->assertFalse(ReturnStatus::received()->isRejected());
        $this->assertFalse(ReturnStatus::inspected()->isRejected());
        $this->assertFalse(ReturnStatus::completed()->isRejected());
    }

    // Complete Workflow Test

    public function testCompleteHappyPathWorkflow(): void
    {
        $requested = ReturnStatus::requested();
        $approved = ReturnStatus::approved();
        $received = ReturnStatus::received();
        $inspected = ReturnStatus::inspected();
        $completed = ReturnStatus::completed();

        $this->assertTrue($requested->canTransitionTo($approved));
        $this->assertTrue($approved->canTransitionTo($received));
        $this->assertTrue($received->canTransitionTo($inspected));
        $this->assertTrue($inspected->canTransitionTo($completed));
    }

    public function testCompleteRejectionWorkflow(): void
    {
        $requested = ReturnStatus::requested();
        $rejected = ReturnStatus::rejected();

        $this->assertTrue($requested->canTransitionTo($rejected));
    }

    public function testLateRejectionWorkflow(): void
    {
        $inspected = ReturnStatus::inspected();
        $rejected = ReturnStatus::rejected();

        $this->assertTrue($inspected->canTransitionTo($rejected));
    }
}
