<?php
require_once 'Telegram.php';

// Configuration
const TOKEN = '7986275489:AAERlkq-qphmG53IGUPiRmPcgu6JVg0MSfo';
const MEDIA_QUEUE_FILE = 'media_queue.json';
const ACTIVE_CHATS_FILE = 'active_chats.json';
const LOG_FILE = 'bot.log';

logMessage("start file delele media", 'LOG');
// Initialize Telegram bot
$telegram = new Telegram(TOKEN, true);

// Logging function
function logMessage(string $message, string $level = 'INFO')
{
    $logEntry = date('Y-m-d H:i:s') . " [$level] $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

logMessage('start file');

// File handling
function loadJsonFile(string $file): array
{
    try {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }
    } catch (Exception $e) {
        logMessage("Error loading $file: " . $e->getMessage(), 'ERROR');
    }
    return [];
}

function saveJsonFile(string $file, array $data)
{
    try {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        logMessage("Error saving $file: " . $e->getMessage(), 'ERROR');
    }
}

// Process media queue
$mediaQueue = loadJsonFile(MEDIA_QUEUE_FILE);
$activeChats = loadJsonFile(ACTIVE_CHATS_FILE);
$currentTime = time();
$updatedQueue = [];

foreach ($mediaQueue as $item) {
    logMessage("in foreach");
    $chatId = $item['chat_id'];
    $deleteTimer = $activeChats[$chatId]['delete_timer'] ?? 7; // تایمر پیش‌فرض
    $deleteTimestamp = $item['timestamp'] ?? (time() + ($deleteTimer * 60));
    if ($currentTime >= $deleteTimestamp) {
        try {
            logMessage("before delete Message");
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $item['message_id']
            ]);
            logMessage("Deleted message {$item['message_id']} in chat $chatId");
        } catch (Exception $e) {
            logMessage("Error deleting message {$item['message_id']}: " . $e->getMessage(), 'ERROR');
        }
    } else {
        $updatedQueue[] = $item;
    }
}

saveJsonFile(MEDIA_QUEUE_FILE, $updatedQueue);
?>