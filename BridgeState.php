<?php

/**
 * Manages the state of the Mastodon-Slack bridge
 * All state is stored in a single JSON file for easy management
 */
class BridgeState
{
    private string $stateFile;
    private array $state;

    public function __construct(string $stateFile = __DIR__ . '/bridge_state.json')
    {
        $this->stateFile = $stateFile;
        $this->load();
    }

    /**
     * Load state from file
     */
    private function load(): void
    {
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true) ?? [];
        } else {
            $this->state = [
                'last_mastodon_id' => null,
                'last_slack_check' => null,
                'thread_mapping' => [],           // Slack thread_ts => Mastodon status_id
                'processed_replies' => [],        // Slack message ts => metadata
            ];
        }
    }

    /**
     * Save state to file
     */
    public function save(): void
    {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }

    /**
     * Get last processed Mastodon notification ID
     */
    public function getLastMastodonId(): ?string
    {
        return $this->state['last_mastodon_id'];
    }

    /**
     * Set last processed Mastodon notification ID
     */
    public function setLastMastodonId(string $id): void
    {
        $this->state['last_mastodon_id'] = $id;
    }

    /**
     * Get last Slack check timestamp
     */
    public function getLastSlackCheck(): ?string
    {
        return $this->state['last_slack_check'];
    }

    /**
     * Set last Slack check timestamp
     */
    public function setLastSlackCheck(string $timestamp): void
    {
        $this->state['last_slack_check'] = $timestamp;
    }

    /**
     * Get thread mapping (Slack thread_ts => Mastodon status_id)
     */
    public function getThreadMapping(): array
    {
        return $this->state['thread_mapping'] ?? [];
    }

    /**
     * Add a thread mapping
     */
    public function addThreadMapping(string $slackThreadTs, string $mastodonStatusId): void
    {
        $this->state['thread_mapping'][$slackThreadTs] = $mastodonStatusId;
    }

    /**
     * Get Mastodon status ID for a Slack thread
     */
    public function getMastodonStatusForThread(string $slackThreadTs): ?string
    {
        return $this->state['thread_mapping'][$slackThreadTs] ?? null;
    }

    /**
     * Get processed replies
     */
    public function getProcessedReplies(): array
    {
        return $this->state['processed_replies'] ?? [];
    }

    /**
     * Check if a Slack reply has been processed
     */
    public function isReplyProcessed(string $replyTs): bool
    {
        return isset($this->state['processed_replies'][$replyTs]);
    }

    /**
     * Mark a Slack reply as processed
     */
    public function markReplyProcessed(string $replyTs, ?string $mastodonStatusId = null): void
    {
        $this->state['processed_replies'][$replyTs] = [
            'mastodon_status_id' => $mastodonStatusId,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
    }
}
