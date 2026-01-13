<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Email;

use FP_Exp\Gift\Email\Templates\VoucherEmailTemplate;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Utils\Helpers;

use function add_query_arg;
use function current_time;
use function date_i18n;
use function esc_html__;
use function get_option;
use function get_post;
use function get_permalink;
use function is_email;
use function sanitize_email;
use function update_post_meta;
use function wp_mail;

use const MINUTE_IN_SECONDS;

/**
 * Service for sending voucher emails.
 *
 * Handles all email sending for vouchers.
 */
final class VoucherEmailSender
{
    private VoucherRepository $repository;

    public function __construct(?VoucherRepository $repository = null)
    {
        $this->repository = $repository ?? new VoucherRepository();
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
        $headers = $template->getHeaders();

        wp_mail($email, $subject, $body, $headers);

        // Send copy to purchaser if different
        $purchaser = $this->repository->getPurchaser($voucher_id);
        $purchaser_email = sanitize_email($purchaser['email'] ?? '');

        if ($purchaser_email && $purchaser_email !== $email && is_email($purchaser_email)) {
            $copy = '<p>' . esc_html__('Your gift voucher was sent to the recipient.', 'fp-experiences') . '</p>';
            $copy .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code->toString())) . '</strong></p>';
            wp_mail($purchaser_email, esc_html__('Gift voucher dispatched', 'fp-experiences'), $copy, $headers);
        }

        // Update delivery status
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
            /* translators: %d: days left. */
            esc_html__('Your gift voucher will expire in %d day(s).', 'fp-experiences'),
            $offset
        ) . '</p>';
        $message .= '<p>' . esc_html__('Voucher code:', 'fp-experiences') . ' <strong>' . esc_html(strtoupper($code->toString())) . '</strong></p>';
        $message .= '<p>' . esc_html__('Valid until:', 'fp-experiences') . ' ' . esc_html(date_i18n(get_option('date_format', 'Y-m-d'), $valid_until)) . '</p>';
        $message .= '<p><a href="' . esc_url($redeem_link) . '">' . esc_html__('Schedule your experience', 'fp-experiences') . '</a></p>';

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
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

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
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

        if ($experience instanceof WP_Post) {
            $message .= '<p><strong>' . esc_html($experience->post_title) . '</strong></p>';
        }

        if (! empty($slot['start_datetime'])) {
            $timestamp = strtotime((string) $slot['start_datetime'] . ' UTC');

            if ($timestamp) {
                $message .= '<p>' . esc_html__('Scheduled for:', 'fp-experiences') . ' ' . esc_html(wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'H:i'), $timestamp)) . '</p>';
            }
        }

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}















