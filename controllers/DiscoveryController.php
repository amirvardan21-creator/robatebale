<?php

/**
 * DiscoveryController - نسخه فیکس شده بدون cb_ error
 */

class DiscoveryController
{
    private Bale $bale;

    public function __construct(Bale $bale)
    {
        $this->bale = $bale;
    }

    public function showNext(array $user, array $filters = []): void
    {
        try {
            $chatId = (int) $user['bale_id'];

            if (($user['step'] ?? '') === 'chatting') {
                User::updateStep($user['id'], 'idle');
            }

            if (!Vip::canView($user['id'])) {
                $limit = FREE_DAILY_VIEWS;
                $this->bale->sendMessage(
                    $chatId,
                    "⚠️ *محدودیت روزانه تمام شد*\n\nشما امروز {$limit} پروفایل دیده‌اید.\nبرای نامحدود ⭐ VIP بخرید یا فردا بیایید.",
                    $this->bale->inlineKeyboard([
                        [['text' => '💎 خرید VIP', 'callback_data' => 'settings_vip']],
                        [['text' => '🎁 جایزه روزانه', 'callback_data' => 'daily_bonus']],
                    ])
                );
                return;
            }

            $stepData = User::getStepData($user) ?? [];
            if (!empty($stepData['discovery_filters'])) {
                $filters = array_merge($stepData['discovery_filters'], $filters);
            }

            $profiles = Profile::getForDiscovery($user['id'], $filters);

            if (empty($profiles)) {
                $this->bale->sendMessage(
                    $chatId,
                    "😔 *کاربر جدیدی پیدا نشد*\n\nهمه رو دیده‌ای یا فیلترهات سخت‌گیرانه است.\nبعداً دوباره امتحان کن.",
                    $this->bale->mainMenuKeyboard()
                );
                return;
            }

            $profile = $profiles[0];
            Profile::incrementViews($user['id']);

            $caption = Profile::formatCard($profile);
            $isOnline = false;
            if (!empty($profile['last_seen_at'])) {
                $diff = time() - strtotime($profile['last_seen_at']);
                $isOnline = $diff < 600;
            }
            if ($isOnline) {
                $caption = "🟢 *آنلاین*\n" . $caption;
            }

            $remainingViews = Vip::getRemainingViews($user['id']);
            $remainingLikes = Vip::getRemainingLikes($user['id']);
            $caption .= "\n\n📊 باقی‌مانده امروز: {$remainingViews} مشاهده، {$remainingLikes} لایک";

            $keyboard = $this->bale->discoveryKeyboard((int) $profile['user_id']);

            if (!empty($profile['photo_file_id'])) {
                $this->bale->sendPhoto($chatId, $profile['photo_file_id'], $caption, $keyboard);
            } else {
                $this->bale->sendMessage($chatId, $caption, $keyboard);
            }
        } catch (Throwable $e) {
            Logger::error('Discovery showNext failed: ' . $e->getMessage(), ['user' => $user['id'] ?? 0]);
            try {
                $this->bale->sendMessage((int) $user['bale_id'], '❌ خطا در نمایش پروفایل بعدی. /start بزنید.', $this->bale->mainMenuKeyboard());
            } catch (Throwable $e2) {}
        }
    }

    public function handleCallback(array $user, string $callbackId, string $data, int $messageId): void
    {
        $chatId = (int) $user['bale_id'];
        try {
            // ---- حالت‌های خاص بدون نیاز به user_id عددی ----
            if ($data === 'discovery_continue' || $data === 'discovery_next') {
                $this->bale->answerCallbackQuery($callbackId);
                try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
                $this->showNext($user);
                return;
            }

            if (str_starts_with($data, 'view_liker_')) {
                $likerId = (int) substr($data, strlen('view_liker_'));
                if ($likerId > 0) {
                    $this->handleViewLiker($user, $callbackId, $likerId, $messageId);
                } else {
                    $this->bale->answerCallbackQuery($callbackId, '❌ خطا', true);
                }
                return;
            }

            // ---- حالت‌های معمولی با user_id ----
            if (!str_contains($data, '_')) {
                $this->bale->answerCallbackQuery($callbackId);
                return;
            }

            $parts = explode('_', $data, 2);
            if (count($parts) < 2) {
                $this->bale->answerCallbackQuery($callbackId);
                return;
            }

            [$action, $targetRaw] = $parts;
            $targetUserId = (int) $targetRaw;

            // report_ می‌تواند متن باشد نه عدد؟ در نسخه قبلی report_123 عدد بود
            // اگر report و target عدد نیست، بذار ProfileController handle کنه
            if ($action === 'report' && $targetUserId <= 0) {
                // ممکن است callback قدیمی باشد
                $this->bale->answerCallbackQuery($callbackId);
                return;
            }

            if ($targetUserId <= 0 && !in_array($action, ['discovery', 'next'], true)) {
                // برای like/dislike باید عدد باشد
                if (in_array($action, ['like', 'dislike', 'superlike'], true)) {
                    $this->bale->answerCallbackQuery($callbackId, '❌ اطلاعات نامعتبر', true);
                    return;
                }
            }

            // Rate limiting
            if ($action === 'like' && !RateLimiter::checkLike($user['id'])) {
                $this->bale->answerCallbackQuery($callbackId, '⏳ کمی صبر کن', true);
                return;
            }

            switch ($action) {
                case 'like':
                    $this->handleLike($user, $callbackId, $targetUserId, $messageId);
                    break;
                case 'superlike':
                case 'super':
                    $this->handleSuperLike($user, $callbackId, $targetUserId, $messageId);
                    break;
                case 'dislike':
                    $this->handleDislike($user, $callbackId, $targetUserId, $messageId);
                    break;
                case 'next':
                    $this->bale->answerCallbackQuery($callbackId);
                    try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
                    $this->showNext($user);
                    break;
                case 'report':
                    $this->handleReport($user, $callbackId, $targetUserId, $messageId);
                    break;
                default:
                    $this->bale->answerCallbackQuery($callbackId);
                    break;
            }
        } catch (Throwable $e) {
            Logger::error('Discovery handleCallback failed: ' . $e->getMessage(), ['user' => $user['id'], 'data' => $data, 'trace' => $e->getTraceAsString()]);
            try { $this->bale->answerCallbackQuery($callbackId, '✅ ادامه می‌دهیم...'); } catch (Throwable $e2) {}
            try {
                $this->bale->deleteMessage($chatId, $messageId);
            } catch (Throwable $e2) {}
            try {
                $this->showNext($user);
            } catch (Throwable $e2) {
                try { $this->bale->sendMessage($chatId, 'از منوی زیر انتخاب کنید:', $this->bale->mainMenuKeyboard()); } catch (Throwable $e3) {}
            }
        }
    }

    private function handleLike(array $user, string $callbackId, int $targetUserId, int $messageId): void
    {
        $chatId = (int) $user['bale_id'];
        if (!Vip::canLike($user['id'])) {
            $this->bale->answerCallbackQuery($callbackId, '⚠️ محدودیت لایک روزانه', true);
            return;
        }
        if (BlockReport::isBlockedEither($user['id'], $targetUserId)) {
            $this->bale->answerCallbackQuery($callbackId, '🚫 بلاک شده', true);
            try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
            $this->showNext($user);
            return;
        }

        MatchModel::addLike($user['id'], $targetUserId, 'like');
        Profile::incrementLikes($user['id']);

        if (MatchModel::checkMutualLike($user['id'], $targetUserId)) {
            $matchId = MatchModel::createMatch($user['id'], $targetUserId);
            $targetUser = User::findById($targetUserId);
            $myProfile = Profile::findByUserId($user['id']);
            $theirProfile = Profile::findByUserId($targetUserId);

            if ($targetUser && $myProfile && $theirProfile) {
                try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
                $this->bale->sendMessage(
                    $chatId,
                    "🎉 *مچ شدید!* شما و *{$theirProfile['name']}* همو پسند کردید 💘",
                    $this->bale->inlineKeyboard([
                        [['text' => "💬 چت با {$theirProfile['name']}", 'callback_data' => 'chat_open_' . $matchId]],
                        [['text' => '❤️ ادامه جستجو', 'callback_data' => 'discovery_continue']],
                    ])
                );
                try {
                    $this->bale->sendMessage(
                        (int) $targetUser['bale_id'],
                        "🎉 *مچ شدید!* *{$myProfile['name']}* هم تو رو پسندید!",
                        $this->bale->inlineKeyboard([
                            [['text' => "💬 چت با {$myProfile['name']}", 'callback_data' => 'chat_open_' . $matchId]],
                        ])
                    );
                } catch (Throwable $e) {}
                $this->bale->answerCallbackQuery($callbackId, '💘 مچ شدید!');
                return;
            }
        }

        $this->bale->answerCallbackQuery($callbackId, '❤️ پسندیده شد');
        try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
        $this->showNext($user);
    }

    private function handleSuperLike(array $user, string $callbackId, int $targetUserId, int $messageId): void
    {
        if (Coin::getBalance($user['id']) < 5) {
            $this->bale->answerCallbackQuery($callbackId, '⚠️ 5 سکه نیاز است', true);
            return;
        }
        if (MatchModel::addSuperLike($user['id'], $targetUserId)) {
            $this->bale->answerCallbackQuery($callbackId, '💘 سوپر لایک رفت!', true);
            $this->bale->deleteMessage((int) $user['bale_id'], $messageId);
            $this->showNext($user);
        } else {
            $this->bale->answerCallbackQuery($callbackId, '❌ خطا', true);
        }
    }

    private function handleDislike(array $user, string $callbackId, int $targetUserId, int $messageId): void
    {
        $chatId = (int) $user['bale_id'];
        MatchModel::addLike($user['id'], $targetUserId, 'dislike');
        $this->bale->answerCallbackQuery($callbackId, '⏭ رد شد');
        try { $this->bale->deleteMessage($chatId, $messageId); } catch (Throwable $e) {}
        $this->showNext($user);
    }

    private function handleReport(array $user, string $callbackId, int $targetUserId, int $messageId): void
    {
        $chatId = (int) $user['bale_id'];
        $this->bale->answerCallbackQuery($callbackId);
        $this->bale->sendMessage(
            $chatId,
            "🚨 دلیل گزارش را بنویس (حداقل 5 کاراکتر):\nبرای لغو /cancel",
            $this->bale->removeKeyboard()
        );
        User::updateStep($user['id'], 'report_user', ['reported_id' => $targetUserId, 'message_id' => $messageId]);
    }

    private function handleViewLiker(array $user, string $callbackId, int $likerId, int $messageId): void
    {
        $chatId = (int) $user['bale_id'];
        $profile = Profile::findByUserId($likerId);
        if (!$profile) {
            $this->bale->answerCallbackQuery($callbackId, '❌ پروفایل یافت نشد', true);
            return;
        }
        $text = Profile::formatCard($profile);
        $this->bale->answerCallbackQuery($callbackId);
        $this->bale->sendMessage(
            $chatId,
            $text,
            $this->bale->inlineKeyboard([
                [['text' => '❤️ لایک کن (مچ!)', 'callback_data' => 'like_' . $likerId], ['text' => '❌ رد', 'callback_data' => 'dislike_' . $likerId]],
            ])
        );
    }

    public function showWhoLikedMe(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $likes = MatchModel::whoLikedMe($user['id'], 10);
            if (empty($likes)) {
                $this->bale->sendMessage($chatId, "💔 کسی لایکت نکرده. فعال باش!", $this->bale->mainMenuKeyboard());
                return;
            }
            $text = "❤️ *کسانی که لایکت کردن (" . count($likes) . "):*\n\n";
            $buttons = [];
            foreach ($likes as $like) {
                $text .= "• {$like['name']}، {$like['age']} ساله\n";
                $buttons[] = [['text' => "👁 {$like['name']}", 'callback_data' => 'view_liker_' . $like['from_user_id']]];
            }
            $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard($buttons));
        } catch (Throwable $e) {
            Logger::error('showWhoLikedMe failed: ' . $e->getMessage());
        }
    }

    public function applyFilters(array $user, array $filters): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $stepData = User::getStepData($user) ?? [];
            $stepData['discovery_filters'] = $filters;
            User::updateStep($user['id'], 'idle', $stepData);
            $this->bale->sendMessage($chatId, "🔎 فیلتر اعمال شد", $this->bale->mainMenuKeyboard());
            $this->showNext($user, $filters);
        } catch (Throwable $e) {
            Logger::error('applyFilters failed: ' . $e->getMessage());
        }
    }
}
