<?php
require_once 'Telegram.php';

// Configuration
const TOKEN = '7986275489:AAERlkq-qphmG53IGUPiRmPcgu6JVg0MSfo';
const ADMINS = [1048235644];
const ACTIVE_CHATS_FILE = 'active_chats.json';
const MEDIA_QUEUE_FILE = 'media_queue.json';
const ADMIN_STATE_FILE = 'admin_state.json';
const LOG_FILE = 'bot.log';

// Initialize Telegram bot
$telegram = new Telegram(TOKEN, true);


// Logging function (only critical logs)
function logMessage(string $message, string $level = 'INFO')
{
    if ($level === 'INFO' && !str_contains($message, 'Setting timer')) {
        return; // Skip non-essential INFO logs
    }
    $logEntry = date('Y-m-d H:i:s') . " [$level] $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// File handling
function loadJsonFile(string $file): array {
    try {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logMessage("JSON decode error in $file: " . json_last_error_msg(), 'ERROR');
                return [];
            }
            return $data ?: [];
        }
    } catch (Exception $e) {
        logMessage("Error loading $file: " . $e->getMessage(), 'ERROR');
    }
    return [];
}

function saveJsonFile(string $file, array $data) {
    try {
        $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        if ($result === false) {
            logMessage("Failed to write to $file", 'ERROR');
        }
    } catch (Exception $e) {
        logMessage("Error saving $file: " . $e->getMessage(), 'ERROR');
    }
}

// Admin check for bot admins
function isBotAdmin(int $userId): bool {
    return in_array($userId, ADMINS);
}

// Admin check for group admins
function isAdmin(int $userId, int $chatId, Telegram $telegram): bool {
    if (isBotAdmin($userId)) {
        return true;
    }
    try {
        $response = $telegram->getChatAdministrators(['chat_id' => $chatId]);
        if (isset($response['ok']) && $response['ok'] && isset($response['result']) && is_array($response['result'])) {
            foreach ($response['result'] as $admin) {
                if (isset($admin['user']['id']) && $admin['user']['id'] === $userId) {
                    return true;
                }
            }
        }
    } catch (Exception $e) {
        logMessage("Error checking admin status for chat $chatId: " . $e->getMessage(), 'ERROR');
    }
    return false;
}

// Handle updates
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) {
    exit;
}

$telegram->setData($update);
$chatId = $telegram->ChatID();
$userId = $telegram->UserID();
$messageId = $telegram->MessageID();
$updateType = $telegram->getUpdateType();
$text = $telegram->Text();

// Load active chats and admin state
$activeChats = loadJsonFile(ACTIVE_CHATS_FILE);
$adminState = loadJsonFile(ADMIN_STATE_FILE);

// Handle commands and button clicks
if ($updateType === Telegram::MESSAGE) {
    $activeChats = loadJsonFile(ACTIVE_CHATS_FILE);
    $isCommand = strpos($text, '/') === 0;
    $commandParts = $isCommand ? explode(' ', ltrim(strtolower($text), '/')) : [$text];
    $command = $commandParts[0];

    // Clear admin state for any command
    if ($isCommand && isset($adminState[$userId])) {
        $originalChatId = $adminState[$userId]['chat_id'];
        unset($adminState[$userId]);
        saveJsonFile(ADMIN_STATE_FILE, $adminState);
        $telegram->sendMessage([
            'chat_id' => $originalChatId,
            'text' => 'عملیات قبلی لغو شد.'
        ]);
    }

    switch ($command) {
        case 'start':
            $keyboard = [
                ['راهنمای فعال‌سازی'],
                ['راهنمای استفاده'],
            ];
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'سلام! من ربات مدیریت رسانه‌ام. می‌تونم عکس‌ها، ویدیوها و بقیه رسانه‌ها رو بعد از زمان مشخص‌شده از گروه حذف کنم. برای شروع، ربات رو به گروه اضافه کن و از دکمه‌های زیر استفاده کن:',
                'reply_markup' => $replyMarkup
            ]);
            break;
        case 'راهنمای فعال‌سازی':
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "*راهنمای فعال‌سازی ربات:*\n\n" .
                          "1. ربات را به گروه خود اضافه کنید.\n" .
                          "2. یکی از ادمین‌های گروه دستور `/activate` را وارد کند.\n" .
                          "3. ربات فعال می‌شود و رسانه‌ها (عکس، ویدیو، استیکر، گیف، ویس) پس از مدت زمان تنظیم‌شده حذف خواهند شد.\n" .
                          "4. برای غیرفعال کردن، از دستور `/deactivate` استفاده کنید.\n\n" .
                          "در صورت نیاز به کمک، با مدیر ربات تماس بگیرید.",
                'parse_mode' => 'Markdown'
            ]);
            break;
        case 'راهنمای استفاده':
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "*راهنمای استفاده از ربات:*\n\n" .
                          "دستورات زیر برای ادمین‌های گروه در دسترس است:\n\n" .
                          "*فعال‌سازی* `/activate`:\n" .
                          "ربات را در گروه فعال می‌کند. پس از فعال‌سازی، رسانه‌ها پس از مدت زمان تنظیم‌شده حذف می‌شوند.\n\n" .
                          "*غیرفعال‌سازی* `/deactivate`:\n" .
                          "ربات را در گروه غیرفعال می‌کند. در این حالت، رسانه‌ها حذف نمی‌شوند.\n\n" .
                          "*تنظیم زمان* `/set_timer`:\n" .
                          "مدت زمان حذف رسانه‌ها را به دقیقه تنظیم می‌کند. مثال: `/set_timer 5` برای ۵ دقیقه.\n\n" .
                          "*حذف همه* `/delete_all`:\n" .
                          "تمام رسانه‌های در صف حذف را فوراً حذف می‌کند.\n\n" .
                          "برای دسترسی به امکانات بیشتر، با مدیر ربات تماس بگیرید.",
                'parse_mode' => 'Markdown'
            ]);
            break;
        case 'activate':
        case 'فعال‌سازی':
            if (!isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط ادمین‌ها می‌توانند ربات را فعال کنند.'
                ]);
                break;
            }
            $activeChats[$chatId] = [
                'active' => true,
                'delete_timer' => 7 // تایمر پیش‌فرض: ۷ دقیقه
            ];
            saveJsonFile(ACTIVE_CHATS_FILE, $activeChats);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'ربات در این چت فعال شد. رسانه‌ها بعد از ۷ دقیقه حذف خواهند شد.'
            ]);
            break;
        case 'deactivate':
        case 'غیرفعال‌سازی':
            if (!isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط ادمین‌ها می‌توانند ربات را غیرفعال کنند.'
                ]);
                break;
            }
            $activeChats[$chatId] = [
                'active' => false,
                'delete_timer' => $activeChats[$chatId]['delete_timer'] ?? 7
            ];
            saveJsonFile(ACTIVE_CHATS_FILE, $activeChats);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'ربات در این چت غیرفعال شد.'
            ]);
            break;
        case 'set_timer':
        case 'تنظیم زمان':
            if (!isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط ادمین‌ها می‌توانند مدت زمان حذف را تنظیم کنند.'
                ]);
                break;
            }
            $timerValue = isset($commandParts[1]) ? trim($commandParts[1]) : null;
            if ($timerValue && is_numeric($timerValue) && (int)$timerValue > 0) {
                $minutes = (int)$timerValue;
                logMessage("Setting timer for chat $chatId to $minutes minutes");
                $activeChats[$chatId] = [
                    'active' => $activeChats[$chatId]['active'] ?? false,
                    'delete_timer' => $minutes
                ];
                saveJsonFile(ACTIVE_CHATS_FILE, $activeChats);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "مدت زمان حذف رسانه‌ها به $minutes دقیقه تنظیم شد."
                ]);
            } elseif (!isset($adminState[$userId])) {
                $adminState[$userId] = ['action' => 'set_timer', 'chat_id' => $chatId];
                saveJsonFile(ADMIN_STATE_FILE, $adminState);
                $keyboard = [['لغو']];
                $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'لطفاً یک عدد مثبت به دقیقه وارد کنید. مثال: 5',
                    'reply_markup' => $replyMarkup
                ]);
            }
            break;
        case 'delete_all':
        case 'حذف همه':
            if (!isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط ادمین‌ها می‌توانند رسانه‌ها را حذف کنند.'
                ]);
                break;
            }
            $mediaQueue = loadJsonFile(MEDIA_QUEUE_FILE);
            $newMediaQueue = [];
            $deletedCount = 0;
            foreach ($mediaQueue as $item) {
                if ($item['chat_id'] == $chatId) {
                    try {
                        $telegram->deleteMessage([
                            'chat_id' => $item['chat_id'],
                            'message_id' => $item['message_id']
                        ]);
                        $deletedCount++;
                    } catch (Exception $e) {
                        logMessage("Error deleting message {$item['message_id']} in chat {$item['chat_id']}: " . $e->getMessage(), 'ERROR');
                    }
                } else {
                    $newMediaQueue[] = $item; // Keep media from other chats
                }
            }
            saveJsonFile(MEDIA_QUEUE_FILE, $newMediaQueue);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "تعداد $deletedCount رسانه از این گروه با موفقیت حذف شد."
            ]);
            break;
        case 'admin_panel':
            if (!isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط مدیر ربات یا ادمین گروه می‌تواند به پنل ادمین دسترسی داشته باشد.'
                ]);
                break;
            }
            $keyboard = [
                ['پیام همگانی', 'فوروارد همگانی'],
                ['لیست چت‌ها']
            ];
            if (!isBotAdmin($userId)) {
                $keyboard = [
                    ['فعال‌سازی', 'غیرفعال‌سازی'],
                    ['تنظیم زمان', 'حذف همه']
                ];
            }
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'پنل ادمین: گزینه مورد نظر را انتخاب کنید.',
                'reply_markup' => $replyMarkup
            ]);
            break;
        case 'پیام همگانی':
            if (!isBotAdmin($userId)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط مدیر ربات می‌تواند پیام همگانی ارسال کند.'
                ]);
                break;
            }
            $adminState[$userId] = ['action' => 'broadcast', 'chat_id' => $chatId];
            saveJsonFile(ADMIN_STATE_FILE, $adminState);
            $keyboard = [['لغو']];
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'لطفاً پیام متنی یا عکس با کپشن را برای ارسال همگانی ارسال کنید.',
                'reply_markup' => $replyMarkup
            ]);
            break;
        case 'فوروارد همگانی':
            if (!isBotAdmin($userId)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط مدیر ربات می‌تواند پیام را فوروارد همگانی کند.'
                ]);
                break;
            }
            $adminState[$userId] = ['action' => 'forward_broadcast', 'chat_id' => $chatId];
            saveJsonFile(ADMIN_STATE_FILE, $adminState);
            $keyboard = [['لغو']];
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'لطفاً به یک پیام (متن یا عکس با کپشن) ریپلای کنید تا فوروارد شود.',
                'reply_markup' => $replyMarkup
            ]);
            break;
        case 'لیست چت‌ها':
            if (!isBotAdmin($userId)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط مدیر ربات می‌تواند لیست گروه‌ها را ببیند.'
                ]);
                break;
            }
            $chatList = loadJsonFile(ACTIVE_CHATS_FILE);
            if (empty($chatList)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'هیچ گروهی در لیست فعال وجود ندارد.'
                ]);
                break;
            }
            $message = "*لیست گروه‌های فعال:*\n\n";
            $chatCount = 0;
            foreach ($chatList as $groupChatId => $chatInfo) {
                if (!is_numeric($groupChatId) || !isset($chatInfo['active'], $chatInfo['delete_timer'])) {
                    logMessage("Invalid chat data for groupChatId $groupChatId: " . json_encode($chatInfo), 'ERROR');
                    continue;
                }
                $status = $chatInfo['active'] ? 'فعال' : 'غیرفعال';
                $timer = $chatInfo['delete_timer'] ?? 7;
                $title = 'Unknown';
                try {
                    $chatInfoResponse = $telegram->getChat(['chat_id' => $groupChatId]);
                    if (isset($chatInfoResponse['ok']) && $chatInfoResponse['ok'] && isset($chatInfoResponse['result']['title'])) {
                        $title = $chatInfoResponse['result']['title'];
                    }
                } catch (Exception $e) {
                    logMessage("Error fetching chat title for $groupChatId: " . $e->getMessage(), 'ERROR');
                }
                $message .= "عنوان: $title\nآیدی چت: `$groupChatId`\nوضعیت: $status\nزمان حذف: $timer دقیقه\n\n";
                $chatCount++;
                if (strlen($message) > 3500) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'Markdown'
                    ]);
                    $message = "*ادامه لیست گروه‌های فعال:*\n\n";
                }
            }
            if ($chatCount === 0) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'هیچ گروه معتبر در لیست فعال وجود ندارد.'
                ]);
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
            }
            break;
        case 'لغو':
            if (!isBotAdmin($userId) && !isAdmin($userId, $chatId, $telegram)) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'فقط مدیر ربات یا ادمین گروه می‌تواند این عملیات را انجام دهد.'
                ]);
                break;
            }
            if (isset($adminState[$userId])) {
                $originalChatId = $adminState[$userId]['chat_id'];
                unset($adminState[$userId]);
                saveJsonFile(ADMIN_STATE_FILE, $adminState);
                $keyboard = [
                    ['پیام همگانی', 'فوروارد همگانی'],
                    ['لیست چت‌ها']
                ];
                if (!isBotAdmin($userId)) {
                    $keyboard = [
                        ['فعال‌سازی', 'غیرفعال‌سازی'],
                        ['تنظیم زمان', 'حذف همه']
                    ];
                }
                $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
                $telegram->sendMessage([
                    'chat_id' => $originalChatId,
                    'text' => 'عملیات لغو شد. به پنل ادمین بازگشتید.',
                    'reply_markup' => $replyMarkup
                ]);
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'هیچ عملیاتی برای لغو وجود ندارد.'
                ]);
            }
            break;
    }
}

// Handle admin state (broadcast, forward_broadcast, set_timer)
if (isset($adminState[$userId]) && $updateType === Telegram::MESSAGE) {
    $action = $adminState[$userId]['action'];
    $originalChatId = $adminState[$userId]['chat_id'];

    // If message is from a different chat, cancel admin state
    if ($chatId != $originalChatId) {
        unset($adminState[$userId]);
        saveJsonFile(ADMIN_STATE_FILE, $adminState);
        $telegram->sendMessage([
            'chat_id' => $originalChatId,
            'text' => 'عملیات لغو شد چون پیام از چت دیگری ارسال شد.'
        ]);
        exit;
    }

    if (!isBotAdmin($userId) && !isAdmin($userId, $originalChatId, $telegram)) {
        unset($adminState[$userId]);
        saveJsonFile(ADMIN_STATE_FILE, $adminState);
        $telegram->sendMessage([
            'chat_id' => $originalChatId,
            'text' => 'فقط مدیر ربات یا ادمین گروه می‌تواند این عملیات را انجام دهد.'
        ]);
        exit;
    }

    // Check for cancel command
    if ($text === 'لغو') {
        unset($adminState[$userId]);
        saveJsonFile(ADMIN_STATE_FILE, $adminState);
        $keyboard = [
            ['پیام همگانی', 'فوروارد همگانی'],
            ['لیست چت‌ها']
        ];
        if (!isBotAdmin($userId)) {
            $keyboard = [
                ['فعال‌سازی', 'غیرفعال‌سازی'],
                ['تنظیم زمان', 'حذف همه']
            ];
        }
        $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
        $telegram->sendMessage([
            'chat_id' => $originalChatId,
            'text' => 'عملیات لغو شد. به پنل ادمین بازگشتید.',
            'reply_markup' => $replyMarkup
        ]);
        exit;
    }

    if ($action === 'broadcast') {
        $commandsAndButtons = [
            'پیام همگانی', 'فوروارد همگانی', 'لیست چت‌ها',
            'فعال‌سازی', 'غیرفعال‌سازی', 'تنظیم زمان', 'حذف همه', 'لغو'
        ];
        if (!in_array($text, $commandsAndButtons) && (isset($update['message']['text']) || isset($update['message']['photo']))) {
            $successful = 0;
            $failed = 0;
            foreach (array_keys($activeChats) as $groupChatId) {
                try {
                    if (isset($update['message']['photo'])) {
                        $photo = end($update['message']['photo'])['file_id'];
                        $caption = $update['message']['caption'] ?? '';
                        $telegram->sendPhoto([
                            'chat_id' => $groupChatId,
                            'photo' => $photo,
                            'caption' => $caption,
                            'parse_mode' => 'Markdown'
                        ]);
                    } else {
                        $messageText = $update['message']['text'];
                        $telegram->sendMessage([
                            'chat_id' => $groupChatId,
                            'text' => $messageText,
                            'parse_mode' => 'Markdown'
                        ]);
                    }
                    $successful++;
                } catch (Exception $e) {
                    logMessage("Error broadcasting to chat $groupChatId: " . $e->getMessage(), 'ERROR');
                    $failed++;
                }
            }
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => "پیام همگانی ارسال شد.\nموفق: $successful\nناموفق: $failed"
            ]);
            unset($adminState[$userId]);
            saveJsonFile(ADMIN_STATE_FILE, $adminState);
            // Show admin panel again
            $keyboard = [
                ['پیام همگانی', 'فوروارد همگانی'],
                ['لیست چت‌ها']
            ];
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => 'پنل ادمین: گزینه مورد نظر را انتخاب کنید.',
                'reply_markup' => $replyMarkup
            ]);
        }
    } elseif ($action === 'forward_broadcast') {
        if (isset($update['message']['reply_to_message'])) {
            $replyMessage = $update['message']['reply_to_message'];
            $fromChatId = $replyMessage['chat']['id'];
            $fromMessageId = $replyMessage['message_id'];
            $successful = 0;
            $failed = 0;
            foreach (array_keys($activeChats) as $groupChatId) {
                try {
                    $telegram->forwardMessage([
                        'chat_id' => $groupChatId,
                        'from_chat_id' => $fromChatId,
                        'message_id' => $fromMessageId
                    ]);
                    $successful++;
                } catch (Exception $e) {
                    logMessage("Error forwarding to chat $groupChatId: " . $e->getMessage(), 'ERROR');
                    $failed++;
                }
            }
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => "پیام فوروارد همگانی انجام شد.\nموفق: $successful\nناموفق: $failed"
            ]);
            unset($adminState[$userId]);
            saveJsonFile(ADMIN_STATE_FILE, $adminState);
            // Show admin panel again
            $keyboard = [
                ['پیام همگانی', 'فوروارد همگانی'],
                ['لیست چت‌ها']
            ];
            $replyMarkup = $telegram->buildKeyBoard($keyboard, true, true);
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => 'پنل ادمین: گزینه مورد نظر را انتخاب کنید.',
                'reply_markup' => $replyMarkup
            ]);
        }
    } elseif ($action === 'set_timer') {
        $input = trim($update['message']['text'] ?? '');
        if (is_numeric($input) && (int)$input > 0) {
            $minutes = (int)$input;
            logMessage("Setting timer for chat $originalChatId to $minutes minutes");
            $activeChats[$originalChatId] = [
                'active' => $activeChats[$originalChatId]['active'] ?? false,
                'delete_timer' => $minutes
            ];
            saveJsonFile(ACTIVE_CHATS_FILE, $activeChats);
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => "مدت زمان حذف رسانه‌ها به $minutes دقیقه تنظیم شد."
            ]);
            unset($adminState[$userId]);
            saveJsonFile(ADMIN_STATE_FILE, $adminState);
        } else {
            $telegram->sendMessage([
                'chat_id' => $originalChatId,
                'text' => 'لطفاً یک عدد مثبت به دقیقه وارد کنید. مثال: 5',
                'reply_markup' => $telegram->buildKeyBoard([['لغو']], true, true)
            ]);
        }
    }
    exit;
}

// Handle media
$mediaTypes = [
    Telegram::PHOTO,
    Telegram::VIDEO,
    Telegram::STICKER,
    Telegram::ANIMATION,
    Telegram::VOICE
];


if (in_array($updateType, $mediaTypes) && isset($activeChats[$chatId]) && is_array($activeChats[$chatId]) && $activeChats[$chatId]['active']) {
    $deleteTimer = $activeChats[$chatId]['delete_timer'] ?? 7; // تایمر پیش‌فرض
    $mediaQueue = loadJsonFile(MEDIA_QUEUE_FILE);
    $mediaQueue[] = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'timestamp' => time() + ($deleteTimer * 60) // تبدیل دقیقه به ثانیه
    ];
    saveJsonFile(MEDIA_QUEUE_FILE, $mediaQueue);
}

// Respond to Telegram
$telegram->respondSuccess();
?>