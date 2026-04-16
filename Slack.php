<?php

class Slack
{
    private string $token;
    private string $apiUrl = 'https://slack.com/api/chat.postMessage';

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function sendMessage(string $channel, string $text): array
    {
        $response = file_get_contents($this->apiUrl, false, stream_context_create([
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
}
