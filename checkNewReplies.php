<?php

header('Content-Type: text/plain; charset=utf-8');

// Load environment variables from .env file
require_once __DIR__ . '/loadEnv.php';
require_once __DIR__ . '/Mastodon.php';
require_once __DIR__ . '/Slack.php';

// Configuration from environment variables
$mastodonInstance = $_ENV['MASTO_INSTANCE'] ?? 'https://social.codefor.nl';
$mastodonToken = $_ENV['MASTO_TOKEN'] ?? null;
$slackToken = $_ENV['SLACK_TOKEN'] ?? null;
$slackChannel = $_ENV['SLACK_CHANNEL'] ?? '#vragen-vanuit-mastodon';

// File to store the mapping between Slack thread_ts and Mastodon status_id
$mappingFile = __DIR__ . '/slack_mastodon_mapping.json';
// File to store which Slack replies have been processed
$processedRepliesFile = __DIR__ . '/processed_slack_replies.json';

if (!$mastodonToken || !$slackToken) {
    die("Error: MASTO_TOKEN and SLACK_TOKEN environment variables are required\n");
}

// Initialize API clients
$mastodon = new Mastodon($mastodonInstance, $mastodonToken);
$slack = new Slack($slackToken);

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

// Load mapping between Slack thread_ts and Mastodon status_id
if (!file_exists($mappingFile)) {
    echo "No mapping file found. Run checkNewMessages.php first.\n";
    exit(0);
}

$mapping = json_decode(file_get_contents($mappingFile), true);

if (empty($mapping)) {
    echo "No thread mappings found.\n";
    exit(0);
}

// Load processed replies
$processedReplies = file_exists($processedRepliesFile)
    ? json_decode(file_get_contents($processedRepliesFile), true)
    : [];

echo "Checking for new Slack replies...\n";
echo "Found " . count($mapping) . " thread(s) to check\n\n";

$newRepliesCount = 0;

foreach ($mapping as $threadTs => $mastodonStatusId) {
    echo "Checking thread $threadTs (Mastodon status: $mastodonStatusId)\n";

    // Get thread replies from Slack (use channel ID instead of name)
    $replies = $slack->getThreadReplies($channelId, $threadTs);

    if (empty($replies)) {
        echo "  No replies found\n";
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
        if (isset($processedReplies[$replyTs])) {
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
            $processedReplies[$replyTs] = [
                'mastodon_status_id' => $result['id'] ?? null,
                'processed_at' => date('Y-m-d H:i:s'),
            ];
            $newRepliesCount++;
        } else {
            echo "      Failed to post to Mastodon\n";
        }
    }

    echo "\n";
}

// Save processed replies
file_put_contents($processedRepliesFile, json_encode($processedReplies, JSON_PRETTY_PRINT));

if ($newRepliesCount > 0) {
    echo "Posted $newRepliesCount new reply/replies to Mastodon\n";
} else {
    echo "No new replies to post\n";
}

echo "Done.\n";
