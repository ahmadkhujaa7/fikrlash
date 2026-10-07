<?php

namespace App\Services\Push;

/** Push bildirishnoma mazmuni: sarlavha, matn, bosilganda ochiladigan sahifa. */
final class PushMessage
{
    public const CHANNEL_MESSAGES = 'messages';

    public const CHANNEL_ACTIVITY = 'activity';

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $channel = self::CHANNEL_ACTIVITY,
        public readonly ?string $tag = null,
        public readonly ?string $image = null,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'channel' => $this->channel,
            'tag' => $this->tag,
            'image' => $this->image,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['title'],
            (string) $data['body'],
            (string) $data['url'],
            (string) ($data['channel'] ?? self::CHANNEL_ACTIVITY),
            $data['tag'] ?? null,
            $data['image'] ?? null,
        );
    }

    /** FCM HTTP v1 "message" (tokensiz). Android va iOS uchun. */
    public function toFcm(): array
    {
        $notification = array_filter(['title' => $this->title, 'body' => $this->body, 'image' => $this->image]);

        return [
            'notification' => $notification,
            'data' => array_map('strval', array_filter([
                'url' => $this->url,
                'channel' => $this->channel,
                'tag' => $this->tag,
            ], fn ($v) => $v !== null)),
            'android' => [
                'priority' => 'high',
                'notification' => array_filter([
                    'channel_id' => $this->channel,
                    'tag' => $this->tag,
                    'icon' => 'ic_stat_fikrlash',
                    'color' => '#3049D1',
                    'sound' => 'default',
                ]),
            ],
            'apns' => [
                'payload' => ['aps' => array_filter(['sound' => 'default', 'thread-id' => $this->tag])],
            ],
        ];
    }
}
