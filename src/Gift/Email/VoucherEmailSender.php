<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Email;

use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Gift\Email\Templates\VoucherEmailTemplate;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Utils\Helpers;

use function add_query_arg;
use function current_time;
use function date_i18n;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_option;
use function get_post;
use function get_permalink;
use function is_email;
use function sanitize_email;
use function sprintf;
use function strtoupper;
use function update_post_meta;

use const MINUTE_IN_SECONDS;

/**
 * Service for sending voucher emails.
 *
 * All dispatch goes through the centralised Mailer.
 */
final class VoucherEmailSender
{
    private VoucherRepository $repository;
    private ?Mailer $mailer;

    public function __construct($mailerOrRepository = null, ?VoucherRepository $repository = null)
    {
        if ($mailerOrRepository instanceof Mailer) {
            $this->mailer = $mailerOrRepository;
            $this->repository = $repository ?? new VoucherRepository();
        } elseif ($mailerOrRepository instanceof VoucherRepository) {
            $this->mailer = null;
            $this->repository = $mailerOrRepository;
        } else {
            $this->mailer = null;
            $this->repository = $repository ?? new VoucherRepository();
        }
    }

    private function getMailer(): Mailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel !== null) {
                $container = $kernel->container();
                if ($container->has(Mailer::class)) {
                    $this->mailer = $container->make(Mailer::class);
                    return $this->mailer;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $options = new \FP_Exp\Services\Options\Options();
        $this->mailer = new Mailer($options);

        return $this->mailer;
    }

    /**
     * Send voucher email to recipient.
     */
    public function sendVoucherEmail(int $voucher_id): void
    {
        $recipient = $this->repository->getRecipient($voucher_id);
        $email = sanitize_email($recipient['email'] ?? '');

        if (! is_email($email)) {
            return;
        }

        $experience_id = $this->repository->getExperienceId($voucher_id);
        $experience = get_post($experience_id);
        $code = $this->repository->getCode($voucher_id);
        $valid_until = $this->repository->getValidUntil($voucher_id);
        $value = $this->repository->getValue($voucher_id);
        $currency = $this->repository->getCurrency($voucher_id);

        if (! $code) {
            return;
        }

        $template = new VoucherEmailTemplate();
        $data = VoucherEmailTemplate::buildData(
            $voucher_id,
            $experience,
            $code->toString(),
            $value,
            $currency,
            $valid_until
        );

        $subject = $template->getSubject($data);
        $body = $template->getBody($data);

        $this->getMailer()->send([$email], $subject, $body);

        // Send copy to purchaser if different
        $purchaser = $this->repository->getPurchaser($voucher_id);
        $purchaser_email = sanitize_email($purchaser['email'] ?? '');

        if ($purchaser_email && $purchaser_email !== $email && is_email($purchaser_email)) {
            $copy = '<p>' . esc_html__('Your gift voucher was sent to the recipient.', 'fp-experiences') . '</p>';
            $copy .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code->toString())) . '</strong></p>';
            $this->getMailer()->send([$purchaser_email], esc_html__('Gift voucher dispatched', 'fp-experiences'), $copy);
        }

        $delivery = $this->repository->getDelivery($voucher_id);
        $delivery['sent_at'] = current_time('timestamp', true);
        $delivery['send_at'] = 0;
        unset($delivery['scheduled_at']);
        $this->repository->updateDelivery($voucher_id, $delivery);
        $this->repository->appendLog($voucher_id, 'dispatched');
    }

    /**
     * Send reminder email.
     */
    public function sendReminderEmail(int $voucher_id, int $offset, int $valid_until): void
    {
        $recipient = $this->repository->getRecipient($voucher_id);
        $email = sanitize_email($recipient['email'] ?? '');

        if (! is_email($email)) {
            return;
        }

        $code = $this->repository->getCode($voucher_id);

        if (! $code) {
            return;
        }

        $redeem_link = add_query_arg('gift', $code->toString(), Helpers::gift_redeem_page());

        $subject = esc_html__('Reminder: your experience gift is waiting', 'fp-experiences');
        $message = '<p>' . sprintf(
            esc_html__('Your gift voucher will expire in %d day(s).', 'fp-experiences'),
            $offset
        ) . '</p>';
        $message .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code->toString())) . '</strong></p>';
        $message .= '<p>' . esc_html__('Valid until:', 'fp-experiences') . ' ' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</p>';
        $message .= '<p><a href="' . esc_url($redeem_link) . '">' . esc_html__('Schedule your experience', 'fp-experiences') . '</a></p>';

        $this->getMailer()->send([$email], $subject, $message);
    }

    /**
     * Send expired email.
     */
    public function sendExpiredEmail(int $voucher_id): void
    {
        $recipient = $this->repository->getRecipient($voucher_id);
        $email = sanitize_email($recipient['email'] ?? '');

        if (! is_email($email)) {
            return;
        }

        $subject = esc_html__('Your experience gift has expired', 'fp-experiences');
        $message = '<p>' . esc_html__('Il voucher collegato alla tua esperienza FP è scaduto. Contatta l\'operatore per assistenza.', 'fp-experiences') . '</p>';

        $this->getMailer()->send([$email], $subject, $message);
    }

    /**
     * Send redeemed email.
     */
    public function sendRedeemedEmail(int $voucher_id, int $order_id, array $slot): void
    {
        $recipient = $this->repository->getRecipient($voucher_id);
        $email = sanitize_email($recipient['email'] ?? '');

        if (! is_email($email)) {
            return;
        }

        $experience_id = $this->repository->getExperienceId($voucher_id);
        $experience = get_post($experience_id);

        $subject = esc_html__('Your gift experience is booked', 'fp-experiences');
        $message = '<p>' . esc_html__('Your gift voucher has been successfully redeemed.', 'fp-experiences') . '</p>';

        if ($experience instanceof \WP_Post) {
            $message .= '<p><strong>' . esc_html($experience->post_title) . '</strong></p>';
        }

        if (! empty($slot['start_datetime'])) {
            $timestamp = strtotime((string) $slot['start_datetime'] . ' UTC');

            if ($timestamp) {
                $message .= '<p>' . esc_html__('Scheduled for:', 'fp-experiences') . ' ' . esc_html(wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'H:i'), $timestamp)) . '</p>';
            }
        }

        $this->getMailer()->send([$email], $subject, $message);
    }
}
