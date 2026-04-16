<?php

header('Content-Type: text/plain; charset=utf-8');

// Load environment variables from .env file
require_once __DIR__ . '/loadEnv.php';
require_once __DIR__ . '/Mastodon.php';
require_once __DIR__ . '/Slack.php';
require_once __DIR__ . '/BridgeState.php';

// Configuration from environment variables
$mastodonInstance = $_ENV['MASTO_INSTANCE'] ?? 'https://social.codefor.nl';
$mastodonToken = $_ENV['MASTO_TOKEN'] ?? null;
$slackToken = $_ENV['SLACK_TOKEN'] ?? null;
$slackChannel = $_ENV['SLACK_CHANNEL'] ?? '#vragen-vanuit-mastodon';

if (!$mastodonToken || !$slackToken) {
    die("Error: MASTO_TOKEN and SLACK_TOKEN environment variables are required\n");
}

// Initialize API clients and state
$mastodon = new Mastodon($mastodonInstance, $mastodonToken);
$slack = new Slack($slackToken);
$state = new BridgeState();

// Convert channel name to channel ID if needed
$channelId = $slack->getChannelId($slackChannel);
if (!$channelId) {
    die("Error: Could not find channel '$slackChannel'. Make sure the channel exists and the bot has access.\n");
}
echo "Using channel ID: $channelId (from $slackChannel)\n";

// Get bot's own user ID to skip messages from ourselves
$botUserId = $slack->getBotUserId();
if (!$botUserId) {
    echo "Warning: Could not get bot user ID. Will skip based on bot_id field only.\n";
} else {
    echo "Bot user ID: $botUserId\n";
}
echo "\n";

// Get thread mapping
$mapping = $state->getThreadMapping();
if (empty($mapping)) {
    echo "No thread mappings found. Run checkNewMessages.php first.\n";
    exit(0);
}

// Get last check timestamp
$lastCheck = $state->getLastSlackCheck();

echo "Checking for new Slack replies...\n";
echo "Found " . count($mapping) . " thread(s) in mapping\n";
if ($lastCheck) {
    echo "Last check: " . date('Y-m-d H:i:s', (int)$lastCheck) . "\n";
}
echo "\n";

// Update last check timestamp to now
$currentTimestamp = (string)time();
$state->setLastSlackCheck($currentTimestamp);

$newRepliesCount = 0;

// Check each tracked thread for new replies
foreach ($mapping as $threadTs => $mastodonStatusId) {
    echo "Checking thread $threadTs (Mastodon status: $mastodonStatusId)\n";

    // Get all replies in this thread
    $replies = $slack->getThreadReplies($channelId, $threadTs);

    if (empty($replies)) {
        echo "  No replies found\n\n";
        continue;
    }

    echo "  Found " . count($replies) . " reply/replies\n";

    foreach ($replies as $reply) {
        $replyTs = $reply['ts'] ?? null;
        $replyText = $reply['text'] ?? '';
        $replyUser = $reply['user'] ?? 'unknown';
        $botId = $reply['bot_id'] ?? null;
        $appId = $reply['app_id'] ?? null;

        if (!$replyTs) {
            continue;
        }

        // Skip messages from the bot itself (to avoid circular posting)
        if ($botUserId && $replyUser === $botUserId) {
            echo "    Reply $replyTs is from the bot itself, skipping to avoid loop\n";
            continue;
        }

        // Also skip other bot messages as fallback
        if ($botId || $appId || isset($reply['bot_profile'])) {
            echo "    Reply $replyTs is from a bot, skipping\n";
            continue;
        }

        // Check if this reply has already been processed
        if ($state->isReplyProcessed($replyTs)) {
            echo "    Reply $replyTs already processed, skipping\n";
            continue;
        }

        echo "    Processing reply from user $replyUser:\n";
        echo "      Text: " . substr($replyText, 0, 100) . "...\n";

        // Format the reply for Mastodon
        $mastodonReplyText = "Antwoord van de community:\n\n" . $replyText;

        // Post reply to Mastodon
        $result = $mastodon->postReply($mastodonStatusId, $mastodonReplyText);

        if ($result) {
            echo "      Posted to Mastodon successfully\n";
            $state->markReplyProcessed($replyTs, $result['id'] ?? null);
            $newRepliesCount++;
        } else {
            echo "      Failed to post to Mastodon\n";
        }
    }

    echo "\n";
}

// Save state
$state->save();

if ($newRepliesCount > 0) {
    echo "Posted $newRepliesCount new reply/replies to Mastodon\n";
} else {
    echo "No new replies to post\n";
}

echo "Done.\n";
