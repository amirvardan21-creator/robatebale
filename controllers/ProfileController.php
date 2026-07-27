<?php

class ProfileController
{
    private Bale $bale;

    public function __construct(Bale $bale)
    {
        $this->bale = $bale;
    }

    public function show(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $profile = Profile::findByUserId($user['id']);
            if (!$profile) {
                $this->bale->sendMessage($chatId, '❌ پروفایل یافت نشد. /start', $this->bale->mainMenuKeyboard());
                return;
            }
            $isVip = Vip::isVip($user['id']);
            $balance = Coin::getBalance($user['id']);
            $level = User::getLevel($user['id']);
            $stats = MatchModel::getLikeStats($user['id']);

            $caption = Profile::formatCard($profile) . "\n\n";
            $caption .= "━━━━━━━━━━━━━━━\n";
            $caption .= "{$level['icon']} {$level['title']} (سطح {$level['level']})\n";
            $caption .= "💰 {$balance} سکه | 🔥 " . ($user['daily_streak'] ?? 0) . " روز\n";
            $caption .= "👁 {$stats['received']} دریافتی | ❤️ {$stats['sent']} ارسالی\n";
            $caption .= "💘 {$stats['matches']} مچ\n";
            $caption .= ($isVip ? "⭐ VIP فعال\n" : "");
            $caption .= ($profile['is_visible'] ? "👁 قابل مشاهده" : "🙈 مخفی");

            $keyboard = $this->bale->inlineKeyboard([
                [['text' => '✏️ ویرایش', 'callback_data' => 'profile_edit'], ['text' => '🖼 عکس', 'callback_data' => 'profile_photo']],
                [['text' => '👁 پیش‌نمایش', 'callback_data' => 'profile_preview'], ['text' => $profile['is_visible'] ? '🙈 مخفی' : '👁 نمایش', 'callback_data' => 'profile_toggle']],
                [['text' => '📊 آمار', 'callback_data' => 'profile_stats'], ['text' => '🚫 بلاک‌ها', 'callback_data' => 'profile_blocked']],
                [['text' => '🗑 حذف پروفایل', 'callback_data' => 'profile_delete']],
            ]);

            if (!empty($profile['photo_file_id'])) {
                $this->bale->sendPhoto($chatId, $profile['photo_file_id'], $caption, $keyboard);
            } else {
                $this->bale->sendMessage($chatId, $caption, $keyboard);
            }
        } catch (Throwable $e) {
            Logger::error('Profile show failed: ' . $e->getMessage());
            try { $this->bale->sendMessage((int) $user['bale_id'], '❌ خطا. /start بزنید', $this->bale->mainMenuKeyboard()); } catch (Throwable $e2) {}
        }
    }

    public function handleCallback(array $user, string $callbackId, string $data): void
    {
        try {
            $chatId = (int) $user['bale_id'];

            if ($data === 'profile_edit') {
                User::updateStep($user['id'], 'edit_field');
                $this->bale->answerCallbackQuery($callbackId);
                $this->bale->sendMessage($chatId, '✏️ کدوم بخش؟', $this->bale->inlineKeyboard([
                    [['text' => '👤 نام', 'callback_data' => 'edit_name'], ['text' => '🎂 سن', 'callback_data' => 'edit_age']],
                    [['text' => '📍 شهر', 'callback_data' => 'edit_city'], ['text' => '📝 بیو', 'callback_data' => 'edit_bio']],
                    [['text' => '🎯 علایق', 'callback_data' => 'edit_interests'], ['text' => '💘 دنبال چی', 'callback_data' => 'edit_looking_for']],
                    [['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']],
                ]));
                return;
            }

            if ($data === 'profile_photo') {
                User::updateStep($user['id'], 'update_photo');
                $this->bale->answerCallbackQuery($callbackId);
                $this->bale->sendMessage($chatId, "📸 عکس جدید بفرست:\nبرای لغو /cancel");
                return;
            }

            if ($data === 'profile_toggle') {
                $profile = Profile::findByUserId($user['id']);
                if ($profile) {
                    $newVis = $profile['is_visible'] ? 0 : 1;
                    Profile::update($user['id'], ['is_visible' => $newVis]);
                    $this->bale->answerCallbackQuery($callbackId, $newVis ? '👁 قابل مشاهده شد' : '🙈 مخفی شد', true);
                } else {
                    $this->bale->answerCallbackQuery($callbackId, '❌ پروفایل یافت نشد', true);
                }
                $this->show($user);
                return;
            }

            if ($data === 'profile_preview') {
                $this->bale->answerCallbackQuery($callbackId);
                $profile = Profile::findByUserId($user['id']);
                if ($profile) {
                    $preview = "👁 *پیش‌نمایش از دید دیگران:*\n\n" . Profile::formatCard($profile);
                    if (!empty($profile['photo_file_id'])) {
                        $this->bale->sendPhoto($chatId, $profile['photo_file_id'], $preview, $this->bale->inlineKeyboard([[['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']]]));
                    } else {
                        $this->bale->sendMessage($chatId, $preview, $this->bale->inlineKeyboard([[['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']]]));
                    }
                }
                return;
            }

            if ($data === 'profile_stats') {
                $this->bale->answerCallbackQuery($callbackId);
                $profile = Profile::findByUserId($user['id']);
                $likeStats = MatchModel::getLikeStats($user['id']);
                $text = "📊 *آمار:*\n\n";
                $text .= "👁 بازدید: " . ($profile['total_views'] ?? 0) . "\n";
                $text .= "❤️ لایک: " . ($profile['total_likes'] ?? 0) . "\n";
                $text .= "💘 مچ: {$likeStats['matches']}\n";
                $text .= "💰 سکه: " . Coin::getBalance($user['id']) . "\n";
                $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard([[['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']]]));
                return;
            }

            if ($data === 'profile_blocked') {
                $this->bale->answerCallbackQuery($callbackId);
                $blocked = BlockReport::getBlockedUsers($user['id']);
                if (empty($blocked)) {
                    $this->bale->sendMessage($chatId, "✅ لیست بلاک خالیه", $this->bale->inlineKeyboard([[['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']]]));
                } else {
                    $text = "🚫 بلاک شده‌ها (" . count($blocked) . "):\n\n";
                    $buttons = [];
                    foreach ($blocked as $b) {
                        $name = $b['name'] ?? 'کاربر ' . $b['blocked_id'];
                        $text .= "• $name\n";
                        $buttons[] = [['text' => "🔓 آنبلاک $name", 'callback_data' => 'unblock_' . $b['blocked_id']]];
                    }
                    $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'profile_back']];
                    $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard($buttons));
                }
                return;
            }

            if ($data === 'profile_back') {
                $this->bale->answerCallbackQuery($callbackId);
                $this->show($user);
                return;
            }

            if ($data === 'profile_delete') {
                $this->bale->answerCallbackQuery($callbackId);
                $this->bale->sendMessage($chatId, "⚠️ مطمئنی حذف کنم؟ برگشت‌ناپذیره!", $this->bale->inlineKeyboard([
                    [['text' => '✅ بله حذف کن', 'callback_data' => 'profile_delete_confirm'], ['text' => '❌ انصراف', 'callback_data' => 'profile_delete_cancel']],
                ]));
                return;
            }

            if ($data === 'profile_delete_confirm') {
                User::delete($user['id']);
                $this->bale->answerCallbackQuery($callbackId, '✅ حذف شد', true);
                $this->bale->sendMessage($chatId, "✅ حذف شد. /start بزنید", $this->bale->removeKeyboard());
                return;
            }

            if ($data === 'profile_delete_cancel') {
                $this->bale->answerCallbackQuery($callbackId, 'انصراف');
                $this->show($user);
                return;
            }

            if (str_starts_with($data, 'edit_')) {
                $field = substr($data, 5);
                $allowed = ['name', 'age', 'city', 'bio', 'interests', 'looking_for'];
                if (!in_array($field, $allowed, true)) {
                    $this->bale->answerCallbackQuery($callbackId, '❌ نامعتبر');
                    return;
                }
                User::updateStep($user['id'], 'editing_' . $field);
                $labels = [
                    'name' => 'نام جدید (2-30 کاراکتر)', 
                    'age' => 'سن جدید (18-80)', 
                    'city' => 'شهر جدید',
                    'bio' => 'بیو جدید (تا 500 کاراکتر)', 
                    'interests' => 'علایق جدید', 
                    'looking_for' => 'دنبال چی؟',
                ];
                $this->bale->answerCallbackQuery($callbackId);
                $this->bale->sendMessage($chatId, "✏️ {$labels[$field]} رو بفرست:\nبرای لغو /cancel");
                return;
            }

            if (str_starts_with($data, 'unblock_')) {
                $blockedId = (int) substr($data, 8);
                if ($blockedId > 0) {
                    BlockReport::unblock($user['id'], $blockedId);
                    $this->bale->answerCallbackQuery($callbackId, '✅ آنبلاک شد', true);
                }
                $this->show($user);
                return;
            }

            if (str_starts_with($data, 'view_liker_')) {
                $likerId = (int) substr($data, strlen('view_liker_'));
                $profile = Profile::findByUserId($likerId);
                if ($profile) {
                    $text = Profile::formatCard($profile);
                    $this->bale->answerCallbackQuery($callbackId);
                    $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard([
                        [['text' => '❤️ لایک (مچ!)', 'callback_data' => 'like_' . $likerId], ['text' => '❌ رد', 'callback_data' => 'dislike_' . $likerId]],
                    ]));
                } else {
                    $this->bale->answerCallbackQuery($callbackId, '❌ یافت نشد', true);
                }
                return;
            }

            $this->bale->answerCallbackQuery($callbackId);
        } catch (Throwable $e) {
            Logger::error('Profile callback failed: ' . $e->getMessage(), ['user' => $user['id'], 'data' => $data]);
            try { $this->bale->answerCallbackQuery($callbackId, '✅'); } catch (Throwable $e2) {}
            try { $this->bale->sendMessage((int) $user['bale_id'], 'از منو انتخاب کنید:', $this->bale->mainMenuKeyboard()); } catch (Throwable $e2) {}
        }
    }

    public function handleEdit(array $user, string $text, ?array $photo): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $step = $user['step'];

            if ($text === '/cancel') {
                User::updateStep($user['id'], 'idle');
                $this->bale->sendMessage($chatId, '✅ لغو شد', $this->bale->mainMenuKeyboard());
                return;
            }

            if ($step === 'edit_field') {
                $this->bale->sendMessage($chatId, 'از دکمه‌ها انتخاب کن');
                return;
            }

            if ($step === 'update_photo') {
                if (!$photo) {
                    $this->bale->sendMessage($chatId, '❌ عکس بفرست یا /cancel');
                    return;
                }
                $fileId = end($photo)['file_id'];
                Profile::update($user['id'], ['photo_file_id' => $fileId]);
                User::updateStep($user['id'], 'idle');
                $this->bale->sendMessage($chatId, '✅ عکس آپدیت شد', $this->bale->mainMenuKeyboard());
                return;
            }

            $field = str_replace('editing_', '', $step);
            $allowed = ['name', 'age', 'city', 'bio', 'interests', 'looking_for'];
            if (!in_array($field, $allowed, true)) {
                User::updateStep($user['id'], 'idle');
                return;
            }

            if ($field === 'name' && !Validator::name($text)) {
                $this->bale->sendMessage($chatId, '❌ نام باید 2-30 حرف بدون کاراکتر خاص');
                return;
            }
            if ($field === 'age') {
                $age = (int) $text;
                if (!Validator::age($age)) {
                    $this->bale->sendMessage($chatId, '❌ سن باید 18-80');
                    return;
                }
                $text = $age;
            }
            if ($field === 'city' && !Validator::city($text)) {
                $this->bale->sendMessage($chatId, '❌ استان معتبر انتخاب کن');
                return;
            }

            $value = $field === 'age' ? (int) $text : htmlspecialchars(mb_substr($text, 0, 500), ENT_QUOTES, 'UTF-8');
            Profile::update($user['id'], [$field => $value]);
            User::updateStep($user['id'], 'idle');
            $this->bale->sendMessage($chatId, '✅ آپدیت شد', $this->bale->mainMenuKeyboard());
        } catch (Throwable $e) {
            Logger::error('Profile edit failed: ' . $e->getMessage());
            try { $this->bale->sendMessage((int) $user['bale_id'], '❌ خطا. /start', $this->bale->mainMenuKeyboard()); } catch (Throwable $e2) {}
        }
    }
}
