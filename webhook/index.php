<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Bale.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Profile.php';
require_once __DIR__ . '/../classes/MatchModel.php';
require_once __DIR__ . '/../classes/Vip.php';
require_once __DIR__ . '/../classes/BlockReport.php';
require_once __DIR__ . '/../classes/Coin.php';
require_once __DIR__ . '/../classes/Schema.php';
require_once __DIR__ . '/../classes/SecretMeetingQueue.php';
require_once __DIR__ . '/../classes/SecretMeetingRoom.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/ProfanityFilter.php';
require_once __DIR__ . '/../classes/Game.php';
require_once __DIR__ . '/../controllers/RegisterController.php';
require_once __DIR__ . '/../controllers/DiscoveryController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/ChatController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/SecretMeetingController.php';

try { Schema::ensureAll(); } catch (Throwable $e) { Logger::error('Schema: ' . $e->getMessage()); }
try { Game::ensureTable(); } catch (Throwable $e) {}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) { http_response_code(200); echo 'OK'; exit; }
$update = json_decode($rawInput, true);
if (!$update) { http_response_code(200); exit; }

$headers = function_exists('getallheaders') ? getallheaders() : [];
$secretHeader = $headers['X-Bale-Bot-Api-Secret-Token'] ?? $headers['x-bale-bot-api-secret-token'] ?? '';
if (!empty($secretHeader) && $secretHeader !== WEBHOOK_SECRET) { http_response_code(403); exit; }

try {
    $botActive = Database::fetch('SELECT value FROM settings WHERE `key` = "bot_active"');
    $baleIdForCheck = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? 0;
    if ($botActive && $botActive['value'] == '0' && (int) $baleIdForCheck !== ADMIN_BALE_ID) {
        $baleTemp = new Bale();
        $msg = Database::fetch('SELECT value FROM settings WHERE `key` = "maintenance_message"');
        $baleTemp->sendMessage((int) $baleIdForCheck, $msg['value'] ?? '🔧 ربات در حال بروزرسانی است.');
        http_response_code(200); exit;
    }
} catch (Throwable $e) {}

$bale = new Bale();
$webhookCallId = uniqid('wh_', true);

try { handleUpdate($update, $bale, $webhookCallId); } catch (Throwable $e) {
    $errorId = uniqid('err_', true);
    Logger::error("[$errorId] [$webhookCallId] " . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
    $chatId = extractChatId($update);
    if ($chatId) { try { $bale->sendMessage($chatId, "⚠️ خطایی رخ داد. /start بزنید.\nکد: $errorId", $bale->mainMenuKeyboard()); } catch (Throwable $e2) {} }
}
http_response_code(200); echo 'OK';

function handleUpdate(array $update, Bale $bale, string $webhookCallId): void
{
    $registerCtrl = new RegisterController($bale);
    $discoveryCtrl = new DiscoveryController($bale);
    $profileCtrl = new ProfileController($bale);
    $chatCtrl = new ChatController($bale);
    $settingsCtrl = new SettingsController($bale);
    $secretMeetingCtrl = new SecretMeetingController($bale, $chatCtrl);

    if (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $from = $cb['from'];
        $data = trim($cb['data'] ?? '');
        $cbId = $cb['id'] ?? '';
        $messageId = $cb['message']['message_id'] ?? 0;
        if ($cbId === '' || $data === '') return;

        try {
            $user = User::findByBaleId((int)$from['id']);
            if (!$user) { $bale->answerCallbackQuery($cbId, 'برای شروع /start', true); return; }
            if (User::isBlocked($user['id'])) { $bale->answerCallbackQuery($cbId, '🚫 مسدودی', true); return; }
            User::touchLastSeen($user['id']);
            Logger::info("Callback", ['user' => $user['id'], 'data' => $data]);
            $handled = false;

            // Game callbacks - اولویت بالا
            if (!$handled && str_starts_with($data, 'game_')) {
                try { $chatCtrl->handleCallback($user, $cbId, $data); $handled = true; } catch (Throwable $e) { Logger::error('Game cb failed: ' . $e->getMessage()); }
            }

            if (!$handled && (str_starts_with($data, 'like_') || str_starts_with($data, 'dislike_') || str_starts_with($data, 'next_') || str_starts_with($data, 'superlike_') || str_starts_with($data, 'super_') || str_starts_with($data, 'report_') || str_starts_with($data, 'view_liker_') || $data === 'discovery_continue' || $data === 'discovery_next')) {
                try { $discoveryCtrl->handleCallback($user, $cbId, $data, $messageId); $handled = true; } catch (Throwable $e) { Logger::error('Discovery cb failed: ' . $e->getMessage()); }
            }
            if (!$handled && (str_starts_with($data, 'profile_') || str_starts_with($data, 'edit_') || str_starts_with($data, 'unblock_') || str_starts_with($data, 'view_liker_'))) {
                try { $profileCtrl->handleCallback($user, $cbId, $data); $handled = true; } catch (Throwable $e) { Logger::error('Profile cb failed: ' . $e->getMessage()); }
            }
            if (!$handled && (str_starts_with($data, 'chat_') || str_starts_with($data, 'sm_'))) {
                try { $chatCtrl->handleCallback($user, $cbId, $data); $handled = true; } catch (Throwable $e) { Logger::error('Chat cb failed: ' . $e->getMessage()); }
            }
            if (!$handled && (str_starts_with($data, 'vip_') || str_starts_with($data, 'settings_') || str_starts_with($data, 'shop_') || $data === 'daily_bonus' || $data === 'noop')) {
                try { $settingsCtrl->handleCallback($user, $cbId, $data); $handled = true; } catch (Throwable $e) { Logger::error('Settings cb failed: ' . $e->getMessage()); }
            }
            if (!$handled) $bale->answerCallbackQuery($cbId);
        } catch (Throwable $e) {
            Logger::error('Callback outer failed: ' . $e->getMessage());
            try { $bale->answerCallbackQuery($cbId, '✅'); } catch (Throwable $e2) {}
        }
        return;
    }

    if (!isset($update['message'])) return;
    $message = $update['message'];
    $from = $message['from'];
    $chatId = (int) $from['id'];
    $text = trim($message['text'] ?? '');
    $photo = $message['photo'] ?? null;
    $caption = trim($message['caption'] ?? '');
    if ($photo && $caption) $text = $caption;

    $user = User::findByBaleId($chatId);

    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        $refCode = $parts[1] ?? null;
        if ($user) User::touchLastSeen($user['id']);
        $registerCtrl->start($user, $from, $refCode);
        return;
    }
    if (!$user) { $bale->sendMessage($chatId, "برای شروع /start بزنید"); return; }
    if (User::isBlocked($user['id'])) { $bale->sendMessage($chatId, '🚫 حسابت مسدوده'); return; }
    User::touchLastSeen($user['id']);
    $step = $user['step'] ?? 'idle';

    if ($text === '/cancel') {
        User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle');
        try { SecretMeetingQueue::removeFromQueue($user['id']); } catch (Throwable $e) {}
        $bale->sendMessage($chatId, '✅ لغو شد', $bale->mainMenuKeyboard()); return;
    }
    if ($text === '/daily' || $text === '🎁 جایزه روزانه') { $result = User::claimDailyBonus($user['id']); $bale->sendMessage($chatId, $result['message'], $bale->mainMenuKeyboard()); return; }
    if ($text === '/coins' || $text === '💰 ثروت من') { $balance = Coin::getBalance($user['id']); $bale->sendMessage($chatId, "💰 موجودی: {$balance} سکه", $bale->mainMenuKeyboard()); return; }
    if ($text === '/invite' || $text === '👥 دعوت دوستان') { $link = User::getReferralLink($user['id']); $stats = User::getReferralStats($user['id']); $bale->sendMessage($chatId, "👥 دعوت:\n🔗 $link\n\n👥 {$stats['count']} نفر | 💰 {$stats['earned']} سکه", $bale->mainMenuKeyboard()); return; }
    if ($text === '/top' || $text === '🏆 برترین‌ها') { $top = Coin::getTopUsers(5); $msg = "🏆 برترین‌ها:\n\n"; foreach ($top as $i => $u) { $name = $u['name'] ?? 'کاربر'; $msg .= ($i+1) . ". $name - {$u['balance']} سکه\n"; } $bale->sendMessage($chatId, $msg, $bale->mainMenuKeyboard()); return; }
    if ($text === '/help') { $bale->sendMessage($chatId, "📖 راهنما:\n🕶 ملاقات مخفیانه\n❤️ پیدا کردن دوست\n💬 گفتگوها\n🎮 بازی در چت\n🎁 /daily\n👥 /invite\n/help", $bale->mainMenuKeyboard()); return; }

    if ($text === '🎮 بازی') {
        $chatCtrl->showGameMenu($user);
        return;
    }

    if ($step === 'secret_meeting' && ($text === '🔎 جستجوی پیشرفته' || isSecretMeetingCommand($text))) { $secretMeetingCtrl->handleMenu($user, $text); return; }
    if ($step === 'secret_meeting_gender') { $secretMeetingCtrl->handleGenderSelection($user, $text); return; }
    if ($step === 'secret_meeting_province') { $secretMeetingCtrl->handleProvinceSelection($user, $text); return; }
    if (isMainMenuCommand($text)) { if ($step !== 'idle' && $step !== 'chatting') User::updateStep($user['id'], 'idle'); handleMainMenuCommand($text, $user, $bale, $discoveryCtrl, $profileCtrl, $chatCtrl, $settingsCtrl, $secretMeetingCtrl); return; }
    if (isSecretMeetingCommand($text)) { if ($step === 'chatting') User::updateStep($user['id'], 'idle'); $secretMeetingCtrl->handleMenu($user, $text); return; }
    if (str_starts_with($step, 'register_')) { $registerCtrl->handle($user, $text, $photo); return; }
    if (str_starts_with($step, 'editing_') || $step === 'update_photo' || $step === 'edit_field') { $profileCtrl->handleEdit($user, $text, $photo); return; }

    if (isSecretMeetingChatButton($text)) {
        $synthetic = resolveSecretMeetingChatButton($user, $text);
        if ($synthetic !== null) { $chatCtrl->handleCallback($user, '', $synthetic); return; }
        $bale->sendMessage($chatId, '⚠️ وارد گفتگو شو', $bale->mainMenuKeyboard()); return;
    }

    if ($step === 'chatting') {
        if ($text === '🔙 بازگشت') { User::updateStep($user['id'], 'idle'); $bale->sendMessage($chatId, 'بازگشت', $bale->mainMenuKeyboard()); return; }
        if ($photo || $text !== '') { $chatCtrl->sendChatMessage($user, $text, $photo); return; }
        $bale->sendMessage($chatId, 'پیام بنویس'); return;
    }
    if (in_array($step, ['report_id', 'report_reason', 'report_user'])) { $settingsCtrl->handleReport($user, $text); return; }
    $profile = Profile::findByUserId($user['id']);
    if (!$profile) { $registerCtrl->start($user, $from); return; }
    $bale->sendMessage($chatId, 'از منو انتخاب کن:', $bale->mainMenuKeyboard());
}

function isMainMenuCommand(string $text): bool { return in_array($text, getMainMenuCommands(), true); }
function isSecretMeetingCommand(string $text): bool { foreach (getSecretMeetingCommands() as $cmd) if (str_starts_with($text, $cmd)) return true; return false; }
function getMainMenuCommands(): array { return ['🕶 ملاقات مخفیانه','🔎 جستجوی پیشرفته','❤️ پیدا کردن دوست','💬 گفتگوهای من','📁 پرونده من','💰 ثروت من','🎁 جایزه روزانه','👥 دعوت دوستان','🏆 برترین‌ها','💎 خرید اشتراک مافیایی','💎 خرید اشتراک','💎 خرید VIP','⚙ تنظیمات','📖 دفترچه راهنما','⚖ قوانین مافیا','🎩 پشتیبانی','🕴 کاربران مافیایی','🌃 اکسپلور','🏰 اتاق مافیایی من','👤 پروفایل من','⭐ عضویت ویژه','🚨 گزارش کاربر','/daily','/coins','/invite','/top','/help','/start',]; }
function getSecretMeetingCommands(): array { return ['🎲 جستجوی شانسی','👨 جستجوی پسر','👩 جستجوی دختر','🗺 جستجوی بر اساس استان','🔙 بازگشت',]; }
function handleMainMenuCommand(string $text, array $user, Bale $bale, DiscoveryController $discoveryCtrl, ProfileController $profileCtrl, ChatController $chatCtrl, SettingsController $settingsCtrl, SecretMeetingController $secretMeetingCtrl): void {
    $chatId = (int) $user['bale_id'];
    switch ($text) {
        case '🕶 ملاقات مخفیانه': $secretMeetingCtrl->showMenu($user); break;
        case '🔎 جستجوی پیشرفته': $secretMeetingCtrl->showComingSoon($user, true); break;
        case '📁 پرونده من': case '👤 پروفایل من': $profileCtrl->show($user); break;
        case '💎 خرید اشتراک مافیایی': case '💎 خرید اشتراک': case '💎 خرید VIP': case '⭐ عضویت ویژه': $settingsCtrl->showVip($user); break;
        case '⚙ تنظیمات': case '🎩 پشتیبانی': $settingsCtrl->show($user); break;
        case '💰 ثروت من': $balance = Coin::getBalance($user['id']); $bale->sendMessage($chatId, "💰 موجودی: {$balance} سکه", $bale->mainMenuKeyboard()); break;
        case '🎁 جایزه روزانه': $result = User::claimDailyBonus($user['id']); $bale->sendMessage($chatId, $result['message'], $bale->mainMenuKeyboard()); break;
        case '👥 دعوت دوستان': $link = User::getReferralLink($user['id']); $stats = User::getReferralStats($user['id']); $bale->sendMessage($chatId, "👥 $link\n👥 {$stats['count']} | 💰 {$stats['earned']}", $bale->mainMenuKeyboard()); break;
        case '🏆 برترین‌ها': $top = Coin::getTopUsers(5); $msg = "🏆 برترین‌ها:\n\n"; foreach ($top as $i => $u) { $name = $u['name'] ?? 'کاربر'; $msg .= ($i+1) . ". $name - {$u['balance']}\n"; } $bale->sendMessage($chatId, $msg, $bale->mainMenuKeyboard()); break;
        case '📖 دفترچه راهنما': $bale->sendMessage($chatId, "📖 راهنما: /help\n🎮 بازی: در چت دکمه 🎮 بازی", $bale->mainMenuKeyboard()); break;
        case '⚖ قوانین مافیا': $bale->sendMessage($chatId, "⚖ قوانین: احترام، بدون اسپم", $bale->mainMenuKeyboard()); break;
        case '❤️ پیدا کردن دوست': $discoveryCtrl->showNext($user); break;
        case '💬 گفتگوهای من': $chatCtrl->showMatches($user); break;
        case '🚨 گزارش کاربر': $settingsCtrl->startReport($user); break;
        default: $bale->sendMessage($chatId, 'از منو انتخاب کن:', $bale->mainMenuKeyboard()); break;
    }
}
function extractChatId(array $update): ?int { if (isset($update['message']['from']['id'])) return (int) $update['message']['from']['id']; if (isset($update['callback_query']['from']['id'])) return (int) $update['callback_query']['from']['id']; return null; }
function isSecretMeetingChatButton(string $text): bool { 
    return in_array($text, [
        '👤 مشاهده پرونده مخاطب','🎁 هدیه دادن','❌ پایان گفت‌وگو','🗑 حذف پیام‌ها','🚨 گزارش کاربر','🎮 بازی'
    ], true); 
}
function resolveSecretMeetingChatButton(array $user, string $text): ?string {
    $partnerId = 0; $roomId = 0;
    if (($user['current_status'] ?? 'idle') === 'in_secret_meeting' && !empty($user['current_secret_meeting_room_id'])) {
        try { $room = SecretMeetingRoom::getRoomById((int) $user['current_secret_meeting_room_id']); if ($room && $room['status'] === 'active') { $roomId = (int) $room['id']; $partnerId = (int) $room['user1_id'] === (int) $user['id'] ? (int) $room['user2_id'] : (int) $room['user1_id']; } } catch (Throwable $e) {}
    }
    if ($partnerId === 0) { $stepData = User::getStepData($user); if (!empty($stepData['partner_id'])) $partnerId = (int) $stepData['partner_id']; if (!empty($stepData['match_id'])) $roomId = (int) $stepData['match_id']; }
    if ($partnerId === 0) return null;
    return match ($text) {
        '👤 مشاهده پرونده مخاطب' => 'sm_view_profile_' . $partnerId,
        '🎁 هدیه دادن' => 'sm_gift_' . $partnerId,
        '🚨 گزارش کاربر' => 'sm_report_user_' . $partnerId,
        '🗑 حذف پیام‌ها' => $roomId > 0 ? 'sm_delete_messages_' . $roomId : null,
        '❌ پایان گفت‌وگو' => $roomId > 0 ? 'sm_end_chat_' . $roomId : null,
        '🎮 بازی' => 'game_menu_' . $roomId,
        default => null,
    };
}
