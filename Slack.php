<?php

class Slack
{
    private string $token;
    private string $baseUrl = 'https://slack.com/api';

    public function __construct(string $token)
    {
        $this->token = $token;
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
     * Get conversation history for a channel
     *
     * @param string $channel Channel ID
     * @param int $limit Maximum number of messages to retrieve
     * @return array Array of messages
     */
    public function getConversationHistory(string $channel, int $limit = 100): array
    {
        $url = $this->baseUrl . '/conversations.history?' . http_build_query([
            'channel' => $channel,
            'limit' => $limit,
        ]);

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
            return [];
        }

        $result = json_decode($response, true);
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
