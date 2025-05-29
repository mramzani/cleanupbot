<?php
require_once 'Telegram.php';

// Configuration
const TOKEN = '7986275489:AAERlkq-qphmG53IGUPiRmPcgu6JVg0MSfo';
const MEDIA_QUEUE_FILE = 'media_queue.json';
const ACTIVE_CHATS_FILE = 'active_chats.json';
const LOG_FILE = 'bot.log';


error_log('Delete media script started at: ' . date('Y-m-d H:i:s'));

// Initialize Telegram bot
$telegram = new Telegram(TOKEN, true);

// Logging function
function logMessage(string $message, string $level = 'INFO')
{
    $logEntry = date('Y-m-d H:i:s') . " [$level] $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    error_log($logEntry); // Log to system error log for cron debugging
}

// File handling
function loadJsonFile(string $file): array
{
    logMessage("Attempting to load file: $file", 'DEBUG');
    try {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logMessage("JSON decode error in $file: " . json_last_error_msg(), 'ERROR');
                return [];
            }
            logMessage("Successfully loaded $file", 'DEBUG');
            return $data ?: [];
        } else {
            logMessage("File $file does not exist", 'ERROR');
        }
    } catch (Exception $e) {
        logMessage("Error loading $file: " . $e->getMessage(), 'ERROR');
    }
    return [];
}

function saveJsonFile(string $file, array $data)
{
    logMessage("Attempting to save file: $file", 'DEBUG');
    try {
        $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($result === false) {
            logMessage("Failed to write to $file", 'ERROR');
        } else {
            logMessage("Successfully saved $file", 'DEBUG');
        }
    } catch (Exception $e) {
        logMessage("Error saving $file: " . $e->getMessage(), 'ERROR');
    }
}

// Process media queue
logMessage("Starting media queue processing at: " . date('Y-m-d H:i:s'), 'INFO');
$mediaQueue = loadJsonFile(MEDIA_QUEUE_FILE);
$activeChats = loadJsonFile(ACTIVE_CHATS_FILE);
$currentTime = time();
$updatedQueue = [];

logMessage("Media queue size: " . count($mediaQueue), 'DEBUG');
logMessage("Active chats: " . json_encode($activeChats), 'DEBUG');

foreach ($mediaQueue as $item) {
    logMessage("Processing item: " . json_encode($item), 'DEBUG');
    
    if (!isset($item['chat_id'], $item['message_id'], $item['timestamp'])) {
        logMessage("Invalid media queue item: " . json_encode($item), 'ERROR');
        continue;
    }
    
    $chatId = $item['chat_id'];
    $deleteTimer = $activeChats[$chatId]['delete_timer'] ?? 7;
    $deleteTimestamp = $item['timestamp'];
    
    logMessage("Chat ID: $chatId, Message ID: {$item['message_id']}, Delete Timer: $deleteTimer, Delete Timestamp: $deleteTimestamp, Current Time: $currentTime", 'DEBUG');
    
    if ($currentTime >= $deleteTimestamp) {
        try {
            logMessage("Attempting to delete message {$item['message_id']} in chat $chatId", 'INFO');
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $item['message_id']
            ]);
            logMessage("Deleted message {$item['message_id']} in chat $chatId", 'INFO');
        } catch (Exception $e) {
            logMessage("Error deleting message {$item['message_id']} in chat $chatId: " . $e->getMessage(), 'ERROR');
        }
    } else {
        $updatedQueue[] = $item;
        logMessage("Keeping message {$item['message_id']} in queue (not yet expired)", 'DEBUG');
    }
}

logMessage("Saving updated queue with " . count($updatedQueue) . " items", 'DEBUG');
saveJsonFile(MEDIA_QUEUE_FILE, $updatedQueue);
logMessage("Media queue processing completed at: " . date('Y-m-d H:i:s'), 'INFO');
?>