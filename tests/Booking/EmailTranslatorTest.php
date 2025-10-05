<?php

declare(strict_types=1);

namespace FP_Exp\Tests\Booking;

use FP_Exp\Booking\EmailTranslator;
use PHPUnit\Framework\TestCase;

final class EmailTranslatorTest extends TestCase
{
    public function testNormalizeDetectsItalianPrefix(): void
    {
        $this->assertSame('it', EmailTranslator::normalize('ita-it'));
        $this->assertSame('it', EmailTranslator::normalize('ITA_booking'));
    }

    public function testNormalizeFallsBackToEnglish(): void
    {
        $this->assertSame('en', EmailTranslator::normalize(''));
        $this->assertSame('en', EmailTranslator::normalize('de'));
    }

    public function testTextReturnsTemplateForLanguage(): void
    {
        $subject = EmailTranslator::text('customer_confirmation.subject', 'ita', ['Esperienza Test']);

        $this->assertSame('La tua prenotazione per Esperienza Test', $subject);
    }

    public function testTextFallsBackToEnglishWhenMissingLanguage(): void
    {
        $subject = EmailTranslator::text('customer_confirmation.subject', 'fr', ['Experience Test']);

        $this->assertSame('Your reservation for Experience Test', $subject);
    }

    public function testTextReturnsEmptyStringForUnknownKey(): void
    {
        $this->assertSame('', EmailTranslator::text('unknown.key', 'it'));
    }
}
