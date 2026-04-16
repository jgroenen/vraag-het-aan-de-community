<?php

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/Mastodon.php';
require_once __DIR__ . '/Slack.php';

// Configuration from environment variables
$mastodonInstance = $_SERVER['MASTO_INSTANCE'] ?? 'https://social.codefor.nl';
$mastodonToken = $_SERVER['MASTO_TOKEN'] ?? null;
$slackToken = $_SERVER['SLACK_TOKEN'] ?? null;
$slackChannel = $_SERVER['SLACK_CHANNEL'] ?? '#vragen-vanuit-mastodon';

// File to store the last processed notification ID
$lastIdFile = __DIR__ . '/last_mastodon_id.txt';
// File to store the mapping between Slack thread_ts and Mastodon status_id
$mappingFile = __DIR__ . '/slack_mastodon_mapping.json';

if (!$mastodonToken || !$slackToken) {
    die("Error: MASTO_TOKEN and SLACK_TOKEN environment variables are required\n");
}

// Initialize API clients
$mastodon = new Mastodon($mastodonInstance, $mastodonToken);
$slack = new Slack($slackToken);

// Get last processed ID
$lastId = file_exists($lastIdFile) ? trim(file_get_contents($lastIdFile)) : null;

// Load existing mapping
$mapping = file_exists($mappingFile) ? json_decode(file_get_contents($mappingFile), true) : [];

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

    echo "Processing mention from @$username:\n";
    echo "  Status ID: $statusId\n";
    echo "  Content: " . substr($content, 0, 100) . "...\n";

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
            $mapping[$threadTs] = $statusId;
            echo "   Stored mapping: Slack thread $threadTs -> Mastodon status $statusId\n";
        }
    } else {
        echo "   Failed to send to Slack: " . ($result['error'] ?? 'unknown error') . "\n";
    }

    echo "\n";
}

// Save the mapping
if (!empty($mapping)) {
    file_put_contents($mappingFile, json_encode($mapping, JSON_PRETTY_PRINT));
}

// Save the last processed ID
if ($newLastId !== $lastId) {
    file_put_contents($lastIdFile, $newLastId);
    echo "Updated last processed ID to: $newLastId\n";
}

echo "Done.\n";
