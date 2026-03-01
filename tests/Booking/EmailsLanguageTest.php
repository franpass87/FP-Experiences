<?php

declare(strict_types=1);

namespace FP_Exp\Tests\Booking;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Senders\CustomerEmailSender;
use FP_Exp\Booking\Email\Senders\StaffEmailSender;
use FP_Exp\Booking\Emails;
use FP_Exp\Services\Options\OptionsInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EmailsLanguageTest extends TestCase
{
    private Emails $emails;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $options = $this->createMock(OptionsInterface::class);
        $options->method('get')->willReturn([]);

        $mailer = $this->createMock(Mailer::class);
        $customer = $this->createMock(CustomerEmailSender::class);
        $staff = $this->createMock(StaffEmailSender::class);

        $this->emails = new Emails($options, $customer, $staff);
        $this->reflection = new ReflectionClass(Emails::class);
    }

    public function testResolveLanguagePrefersExplicitArgument(): void
    {
        $method = $this->reflection->getMethod('resolve_language');
        $method->setAccessible(true);

        $language = $method->invoke($this->emails, [], 'ita');

        $this->assertSame('it', $language);
    }

    public function testResolveLanguageFallsBackToContextLanguage(): void
    {
        $method = $this->reflection->getMethod('resolve_language');
        $method->setAccessible(true);

        $language = $method->invoke($this->emails, ['language' => 'ita-prefixed']);

        $this->assertSame('it', $language);
    }

    public function testDetectLanguageFromReservationCode(): void
    {
        $method = $this->reflection->getMethod('detect_language');
        $method->setAccessible(true);

        $language = $method->invoke($this->emails, [
            'reservation' => [
                'code' => 'ITA-1234',
            ],
            'experience' => [
                'title' => 'Wine Tour',
            ],
        ]);

        $this->assertSame('it', $language);
    }

    public function testDetectLanguageFromLocale(): void
    {
        $method = $this->reflection->getMethod('detect_language');
        $method->setAccessible(true);

        $language = $method->invoke($this->emails, [
            'locale' => 'it_IT',
        ]);

        $this->assertSame('it', $language);
    }

    public function testDetectLanguageFallsBackToEnglish(): void
    {
        $method = $this->reflection->getMethod('detect_language');
        $method->setAccessible(true);

        $language = $method->invoke($this->emails, [
            'reservation' => [
                'code' => 'EN-1234',
            ],
        ]);

        $this->assertSame('en', $language);
    }

    public function testHasItaPrefixMatchesVariousFormats(): void
    {
        $method = $this->reflection->getMethod('has_ita_prefix');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->emails, 'ita-tour'));
        $this->assertTrue($method->invoke($this->emails, 'ITA Tour Deluxe'));
        $this->assertFalse($method->invoke($this->emails, 'ultimate experience'));
    }
}
