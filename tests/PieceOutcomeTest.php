<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\Shippable;
use OxidShipping\Engine\Domain\UnknownZone;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class PieceOutcomeTest extends TestCase
{
    public function testShippableHoldsClassAndRejectedHoldsReason(): void
    {
        $shippable = new Shippable(ShippingClass::Paket);
        $rejected = new Rejected(RejectReason::UnknownZone);

        $this->assertSame(ShippingClass::Paket, $shippable->class);
        $this->assertSame(RejectReason::UnknownZone, $rejected->reason);
        $this->assertSame('unknown_zone', $rejected->reason->value);
        $this->assertSame(
            'Postal code is not in the directory.',
            $rejected->reason->explanation(),
        );
    }

    public function testUnknownZoneHasNoDataAndKnownZoneKeepsId(): void
    {
        $unknown = new UnknownZone();
        $known = new KnownZone('de-sn');

        $this->assertSame([], get_object_vars($unknown));
        $this->assertSame('de-sn', $known->zoneId);
    }

    public function testEmptyKnownZoneIdIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new KnownZone('');
    }
}
