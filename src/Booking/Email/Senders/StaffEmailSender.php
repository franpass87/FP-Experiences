<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Email\Senders;

use FP_Exp\Integrations\Brevo;

/**
 * Email sender for staff/admin emails.
 */
final class StaffEmailSender extends AbstractEmailSender
{
    // Staff emails are always sent via wp_mail
    // No special logic needed, inherits from AbstractEmailSender
}















