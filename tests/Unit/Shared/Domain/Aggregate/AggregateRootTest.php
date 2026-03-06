<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Aggregate;

use App\Shared\Domain\Aggregate\AggregateRoot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRoot::class)]
final class AggregateRootTest extends TestCase
{
    private AggregateRoot $aggregate;

    protected function setUp(): void
    {
        // Concrete anonymous subclass for testing the abstract class
        $this->aggregate = new class extends AggregateRoot {
            public function raiseEvent(object $event): void
            {
                $this->recordEvent($event);
            }

            public function clearAllEvents(): void
            {
                $this->clearEvents();
            }
        };
    }

    // -------
    // Initial state
    // -------

    #[Test]
    public function itHasNoEventsInitially(): void
    {
        self::assertFalse($this->aggregate->hasEvents());
    }

    #[Test]
    public function popEventsReturnsEmptyArrayWhenNoEventsRecorded(): void
    {
        self::assertSame([], $this->aggregate->popEvents());
    }

    // -------
    // recordEvent / popEvents
    // -------

    #[Test]
    public function itRecordsASingleEvent(): void
    {
        $event = new \stdClass();
        $this->aggregate->raiseEvent($event);

        self::assertTrue($this->aggregate->hasEvents());
    }

    #[Test]
    public function popEventsReturnsSingleRecordedEvent(): void
    {
        $event = new \stdClass();
        $this->aggregate->raiseEvent($event);

        $events = $this->aggregate->popEvents();

        self::assertCount(1, $events);
        self::assertSame($event, $events[0]);
    }

    #[Test]
    public function itRecordsMultipleEventsInOrder(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();
        $event3 = new \stdClass();

        $this->aggregate->raiseEvent($event1);
        $this->aggregate->raiseEvent($event2);
        $this->aggregate->raiseEvent($event3);

        $events = $this->aggregate->popEvents();

        self::assertCount(3, $events);
        self::assertSame($event1, $events[0]);
        self::assertSame($event2, $events[1]);
        self::assertSame($event3, $events[2]);
    }

    #[Test]
    public function popEventsClearsEventsAfterReturning(): void
    {
        $this->aggregate->raiseEvent(new \stdClass());

        $this->aggregate->popEvents();

        self::assertFalse($this->aggregate->hasEvents());
        self::assertSame([], $this->aggregate->popEvents());
    }

    // -------
    // pullDomainEvents() - alias for popEvents
    // -------

    #[Test]
    public function pullDomainEventsReturnsSameAsPopEvents(): void
    {
        $event = new \stdClass();
        $this->aggregate->raiseEvent($event);

        $events = $this->aggregate->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertSame($event, $events[0]);
    }

    #[Test]
    public function pullDomainEventsClearsEventsAfterReturning(): void
    {
        $this->aggregate->raiseEvent(new \stdClass());
        $this->aggregate->pullDomainEvents();

        self::assertSame([], $this->aggregate->pullDomainEvents());
    }

    // -------
    // clearEvents()
    // -------

    #[Test]
    public function clearEventsRemovesAllRecordedEvents(): void
    {
        $this->aggregate->raiseEvent(new \stdClass());
        $this->aggregate->raiseEvent(new \stdClass());

        $this->aggregate->clearAllEvents();

        self::assertFalse($this->aggregate->hasEvents());
    }

    #[Test]
    public function clearEventsDoesNotThrowWhenNoEventsPresent(): void
    {
        // Should not throw
        $this->aggregate->clearAllEvents();

        self::assertFalse($this->aggregate->hasEvents());
    }

    // -------
    // hasEvents()
    // -------

    #[Test]
    public function hasEventsReturnsTrueAfterRecordingEvent(): void
    {
        $this->aggregate->raiseEvent(new \stdClass());

        self::assertTrue($this->aggregate->hasEvents());
    }

    #[Test]
    public function hasEventsReturnsFalseAfterPoppingEvents(): void
    {
        $this->aggregate->raiseEvent(new \stdClass());
        $this->aggregate->popEvents();

        self::assertFalse($this->aggregate->hasEvents());
    }
}
