<?php

class Slack
{
    private string $token;
    private string $baseUrl = 'https://slack.com/api';

    private ?string $botUserId = null;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the bot's own user ID
     *
     * @return string|null Bot user ID or null on failure
     */
    public function getBotUserId(): ?string
    {
        if ($this->botUserId !== null) {
            return $this->botUserId;
        }

        $url = $this->baseUrl . '/auth.test';

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $this->token,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $result = json_decode($response, true);

        if ($result['ok'] ?? false) {
            $this->botUserId = $result['user_id'] ?? null;
            return $this->botUserId;
        }

        return null;
    }

    /**
     * Convert channel name to channel ID
     *
     * @param string $channel Channel name (with or without #) or channel ID
     * @return string|null Channel ID or null if not found
     */
    public function getChannelId(string $channel): ?string
    {
        // If it's already a channel ID (starts with C), return it as-is
        if (preg_match('/^C[A-Z0-9]+$/', $channel)) {
            return $channel;
        }

        // Remove # prefix if present
        $channelName = ltrim($channel, '#');

        $url = $this->baseUrl . '/conversations.list?' . http_build_query([
            'types' => 'public_channel,private_channel',
            'limit' => 1000,
        ]);

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $this->token,
            ],
        ]));

        if ($response === false) {
            echo "ERROR: Failed to get channel list from Slack\n";
            return null;
        }

        $result = json_decode($response, true);

        if (!($result['ok'] ?? false)) {
            echo "ERROR from Slack API: " . ($result['error'] ?? 'unknown') . "\n";
            return null;
        }

        $channels = $result['channels'] ?? [];

        foreach ($channels as $ch) {
            if ($ch['name'] === $channelName) {
                return $ch['id'];
            }
        }

        return null;
    }

    public function sendMessage(string $channel, string $text): array
    {
        $response = file_get_contents($this->baseUrl . '/chat.postMessage', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $this->token,
                ]),
                'content' => json_encode([
                    'channel' => $channel,
                    'text'    => $text,
                ]),
            ],
        ]));

        return json_decode($response, true);
    }

    /**
     * Send a reply to a thread in a channel
     *
     * @param string $channel Channel ID
     * @param string $threadTs Thread timestamp to reply to
     * @param string $text Message text
     * @return array Response from Slack API
     */
    public function sendThreadReply(string $channel, string $threadTs, string $text): array
    {
        $response = file_get_contents($this->baseUrl . '/chat.postMessage', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $this->token,
                ]),
                'content' => json_encode([
                    'channel' => $channel,
                    'text'    => $text,
                    'thread_ts' => $threadTs,
                ]),
            ],
        ]));

        return json_decode($response, true);
    }

    /**
     * Get conversation history for a channel
     *
     * @param string $channel Channel ID
     * @param int $limit Maximum number of messages to retrieve
     * @param string|null $oldest Only messages after this timestamp (Unix timestamp)
     * @return array Array of messages
     */
    public function getConversationHistory(string $channel, int $limit = 100, ?string $oldest = null): array
    {
        $params = [
            'channel' => $channel,
            'limit' => $limit,
        ];

        if ($oldest !== null) {
            $params['oldest'] = $oldest;
        }

        $url = $this->baseUrl . '/conversations.history?' . http_build_query($params);

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $this->token,
            ],
        ]));

        if ($response === false) {
            return [];
        }

        $result = json_decode($response, true);
        return $result['messages'] ?? [];
    }

    /**
     * Get replies to a specific thread
     *
     * @param string $channel Channel ID
     * @param string $threadTs Thread timestamp
     * @return array Array of reply messages
     */
    public function getThreadReplies(string $channel, string $threadTs): array
    {
        $url = $this->baseUrl . '/conversations.replies?' . http_build_query([
            'channel' => $channel,
            'ts' => $threadTs,
        ]);

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $this->token,
            ],
        ]));

        if ($response === false) {
            echo "    ERROR: Failed to get response from Slack API\n";
            return [];
        }

        $result = json_decode($response, true);

        // Debug: show the full response
        if (!($result['ok'] ?? false)) {
            echo "    ERROR from Slack API: " . ($result['error'] ?? 'unknown') . "\n";
            echo "    Full response: " . json_encode($result) . "\n";
            return [];
        }

        $messages = $result['messages'] ?? [];

        // First message is the parent, rest are replies
        // Remove the parent message and return only the replies
        if (count($messages) > 1) {
            array_shift($messages);
        } else {
            return [];
        }

        return $messages;
    }
}
