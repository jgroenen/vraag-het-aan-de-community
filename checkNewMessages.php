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

// Get last processed ID
$lastId = $state->getLastMastodonId();

echo "Checking for new Mastodon mentions...\n";
if ($lastId) {
    echo "Last processed ID: $lastId\n";
}

// Get new notifications
$notifications = $mastodon->getNotifications($lastId);

if (empty($notifications)) {
    echo "No new mentions found.\n";
    exit(0);
}

echo "Found " . count($notifications) . " new mention(s)\n\n";

// Process notifications in reverse order (oldest first)
$notifications = array_reverse($notifications);
$newLastId = $lastId;

foreach ($notifications as $notification) {
    $status = $notification['status'] ?? null;
    if (!$status) {
        continue;
    }

    $notificationId = $notification['id'];
    $statusId = $status['id'];
    $account = $status['account'];
    $username = $account['acct'];
    $content = strip_tags($status['content']); // Remove HTML tags
    $url = $status['url'];
    $inReplyToId = $status['in_reply_to_id'] ?? null;

    echo "Processing mention from @$username:\n";
    echo "  Status ID: $statusId\n";
    echo "  Content: " . substr($content, 0, 100) . "...\n";

    // Check if this is a reply to an existing Mastodon status
    if ($inReplyToId) {
        echo "  This is a reply to Mastodon status: $inReplyToId\n";

        // Find the corresponding Slack thread
        $slackThreadTs = array_search($inReplyToId, $state->getThreadMapping());

        if ($slackThreadTs) {
            echo "  Found corresponding Slack thread: $slackThreadTs\n";

            // Format message for Slack thread reply
            $slackMessage = sprintf(
                "Reactie van @%s op Mastodon:\n\n%s\n\n<%s|Bekijk op Mastodon>",
                $username,
                $content,
                $url
            );

            // Send as reply in Slack thread
            $result = $slack->sendThreadReply($slackChannel, $slackThreadTs, $slackMessage);

            if ($result['ok'] ?? false) {
                echo "   Sent as thread reply to Slack\n";
                $newLastId = $notificationId;
            } else {
                echo "   Failed to send thread reply to Slack: " . ($result['error'] ?? 'unknown error') . "\n";
            }

            echo "\n";
            continue;
        } else {
            echo "  No Slack thread found for Mastodon status $inReplyToId, posting as new message\n";
        }
    }

    // This is a new top-level mention, not a reply
    // Format message for Slack
    $slackMessage = sprintf(
        "Nieuwe vraag van @%s op Mastodon:\n\n%s\n\n<%s|Bekijk op Mastodon>",
        $username,
        $content,
        $url
    );

    // Send to Slack
    $result = $slack->sendMessage($slackChannel, $slackMessage);

    if ($result['ok'] ?? false) {
        echo "   Sent to Slack\n";
        $newLastId = $notificationId;

        // Store mapping between Slack thread_ts and Mastodon status_id
        $threadTs = $result['ts'] ?? null;
        if ($threadTs) {
            $state->addThreadMapping($threadTs, $statusId);
            echo "   Stored mapping: Slack thread $threadTs -> Mastodon status $statusId\n";
        }
    } else {
        echo "   Failed to send to Slack: " . ($result['error'] ?? 'unknown error') . "\n";
    }

    echo "\n";
}

// Save state (mapping and last processed ID)
if ($newLastId !== $lastId) {
    $state->setLastMastodonId($newLastId);
    echo "Updated last processed ID to: $newLastId\n";
}
$state->save();

echo "Done.\n";
