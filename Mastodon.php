<?php

class Mastodon
{
    private string $token;
    private string $instanceUrl;

    public function __construct(string $instanceUrl, string $token)
    {
        $this->instanceUrl = rtrim($instanceUrl, '/');
        $this->token = $token;
    }

    /**
     * Get notifications (mentions) from Mastodon
     *
     * @param string|null $sinceId Only get notifications newer than this ID
     * @param int $limit Maximum number of notifications to retrieve (default: 20)
     * @return array Array of notifications
     */
    public function getNotifications(?string $sinceId = null, int $limit = 20): array
    {
        $url = $this->instanceUrl . '/api/v1/notifications';

        $params = [
            'types[]' => 'mention',
            'limit' => $limit,
        ];

        if ($sinceId !== null) {
            $params['since_id'] = $sinceId;
        }

        $url .= '?' . http_build_query($params);

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json; charset=utf-8',
                ]),
            ],
        ]));

        if ($response === false) {
            return [];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Post a reply to a status
     *
     * @param string $inReplyToId The ID of the status to reply to
     * @param string $text The content of the reply
     * @return array|null The created status or null on failure
     */
    public function postReply(string $inReplyToId, string $text): ?array
    {
        $url = $this->instanceUrl . '/api/v1/statuses';

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json; charset=utf-8',
                ]),
                'content' => json_encode([
                    'status' => $text,
                    'in_reply_to_id' => $inReplyToId,
                ]),
            ],
        ]));

        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
