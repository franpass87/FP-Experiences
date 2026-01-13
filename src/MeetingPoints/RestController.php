<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function array_filter;
use function array_map;
use function array_values;
use function rest_ensure_response;
use function absint;
use function register_rest_route;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;

final class RestController implements HookableInterface
{
    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'fp-exp/v1',
            '/meeting-points/(?P<experience_id>\d+)',
            [
                'methods' => 'GET',
                'permission_callback' => [$this, 'can_view_meeting_points'],
                'callback' => [$this, 'get_meeting_points'],
                'args' => [
                    'experience_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    public function can_view_meeting_points(WP_REST_Request $request): bool
    {
        return Helpers::verify_public_rest_request($request);
    }

    public function get_meeting_points(WP_REST_Request $request)
    {
        if (! Helpers::meeting_points_enabled()) {
            return rest_ensure_response([
                'primary' => null,
                'alternatives' => [],
            ]);
        }

        $experience_id = absint((string) $request->get_param('experience_id'));

        $data = Repository::get_meeting_points_for_experience($experience_id);

        return rest_ensure_response([
            'primary' => $this->prepare_point($data['primary']),
            'alternatives' => array_values(array_filter(array_map([$this, 'prepare_point'], $data['alternatives']))),
        ]);
    }

    /**
     * @param array<string, mixed>|null $point
     *
     * @return array<string, mixed>|null
     */
    private function prepare_point(?array $point): ?array
    {
        if (! $point) {
            return null;
        }

        $title = sanitize_text_field((string) ($point['title'] ?? ''));
        $address = sanitize_textarea_field((string) ($point['address'] ?? ''));
        $notes = sanitize_textarea_field((string) ($point['notes'] ?? ''));
        $phone = sanitize_text_field((string) ($point['phone'] ?? ''));
        $email = sanitize_email((string) ($point['email'] ?? ''));
        $opening_hours = sanitize_textarea_field((string) ($point['opening_hours'] ?? ''));

        return [
            'id' => (int) ($point['id'] ?? 0),
            'title' => $title,
            'address' => $address,
            'lat' => isset($point['lat']) ? (float) $point['lat'] : null,
            'lng' => isset($point['lng']) ? (float) $point['lng'] : null,
            'notes' => $notes,
            'phone' => $phone,
            'email' => $email,
            'opening_hours' => $opening_hours,
        ];
    }
}
