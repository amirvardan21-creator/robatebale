<?php

class SettingsController
{
    private Bale $bale;

    public function __construct(Bale $bale) { $this->bale = $bale; }

    public function show(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $profile = Profile::findByUserId($user['id']);
            if (!$profile) { $this->bale->sendMessage($chatId, '❌ پروفایل یافت نشد', $this->bale->mainMenuKeyboard()); return; }
            $isVip = Vip::isVip($user['id']);
            $balance = Coin::getBalance($user['id']);
            $level = User::getLevel($user['id']);
            $text = "⚙ *تنظیمات*\n\n👤 {$profile['name']} | {$profile['age']} ساله\n📍 {$profile['city']}\n{$level['icon']} {$level['title']}\n" . ($isVip ? "⭐ VIP\n" : "💎 عادی\n") . "💰 {$balance} سکه\n\nیک گزینه انتخاب کن:";
            $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard([
                [['text' => '💎 VIP', 'callback_data' => 'settings_vip'], ['text' => '💰 فروشگاه', 'callback_data' => 'settings_shop']],
                [['text' => '🔍 فیلتر', 'callback_data' => 'settings_filter'], ['text' => '👁 حریم خصوصی', 'callback_data' => 'settings_privacy']],
                [['text' => '📜 قوانین', 'callback_data' => 'settings_rules'], ['text' => '📞 پشتیبانی', 'callback_data' => 'settings_support']],
                [['text' => '🚨 گزارش', 'callback_data' => 'settings_report'], ['text' => '🔙 بازگشت', 'callback_data' => 'settings_back']],
            ]));
        } catch (Throwable $e) {
            Logger::error('Settings show failed: ' . $e->getMessage());
            try { $this->bale->sendMessage((int) $user['bale_id'], 'از منو انتخاب کنید', $this->bale->mainMenuKeyboard()); } catch (Throwable $e2) {}
        }
    }

    public function showVip(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $isVip = Vip::isVip($user['id']);
            $balance = Coin::getBalance($user['id']);
            if ($isVip) {
                $vipInfo = Vip::getInfo($user['id']);
                $expires = $vipInfo['expires_at'] ?? 'نامشخص';
                $this->bale->sendMessage($chatId, "⭐ *VIP فعال!*\n\nانقضا: {$expires}\n\nمزایا: مشاهده و لایک نامحدود + اولویت", $this->bale->mainMenuKeyboard());
                return;
            }
            $plans = Vip::getPlans();
            $buttons = [];
            foreach ($plans as $key => $plan) {
                $pop = !empty($plan['popular']) ? ' 🔥' : '';
                $discount = !empty($plan['discount']) ? " ({$plan['discount']})" : '';
                $buttons[] = [['text' => "{$plan['icon']} {$plan['title']} - {$plan['price_coins']} سکه{$discount}{$pop}", 'callback_data' => "vip_buy_{$key}"]];
            }
            $buttons[] = [['text' => '💰 موجودی: ' . $balance . ' سکه', 'callback_data' => 'noop']];
            $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'settings_back']];
            $this->bale->sendMessage($chatId, "💎 *خرید VIP*\n\n💰 موجودی: {$balance} سکه\nپلن انتخاب کن:", $this->bale->inlineKeyboard($buttons));
        } catch (Throwable $e) {
            Logger::error('showVip failed: ' . $e->getMessage());
        }
    }

    public function showShop(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $balance = Coin::getBalance($user['id']);
            $items = Coin::getShopItems();
            $text = "🛒 *فروشگاه* 💰 {$balance} سکه\n\n";
            $buttons = [];
            foreach ($items as $item) {
                $text .= "{$item['icon']} {$item['name']} - {$item['price']}\n";
                $buttons[] = [['text' => "{$item['icon']} {$item['name']} ({$item['price']})", 'callback_data' => "shop_{$item['id']}"]];
            }
            $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'settings_back']];
            $this->bale->sendMessage($chatId, $text, $this->bale->inlineKeyboard($buttons));
        } catch (Throwable $e) { Logger::error('showShop failed: ' . $e->getMessage()); }
    }

    public function startReport(array $user): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            User::updateStep($user['id'], 'report_id');
            $this->bale->sendMessage($chatId, "🚨 *گزارش کاربر*\n\nآیدی عددی بله کاربر را بفرست:\nمثلا 123456789\nبرای لغو /cancel", $this->bale->removeKeyboard());
        } catch (Throwable $e) { Logger::error('startReport failed: ' . $e->getMessage()); }
    }

    public function handleReport(array $user, string $text): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $step = $user['step'];
            $stepData = User::getStepData($user) ?? [];
            if ($text === '/cancel') { User::updateStep($user['id'], 'idle'); $this->bale->sendMessage($chatId, '✅ لغو شد', $this->bale->mainMenuKeyboard()); return; }
            if ($step === 'report_id') {
                $targetBaleId = (int) trim($text);
                if ($targetBaleId <= 0) { $this->bale->sendMessage($chatId, '❌ عدد بفرست'); return; }
                $targetUser = User::findByBaleId($targetBaleId);
                if (!$targetUser) { $this->bale->sendMessage($chatId, '❌ یافت نشد. دوباره یا /cancel'); return; }
                if ($targetUser['id'] === $user['id']) { $this->bale->sendMessage($chatId, '❌ خودت رو نمیشه!'); User::updateStep($user['id'], 'idle'); $this->bale->sendMessage($chatId, 'منو', $this->bale->mainMenuKeyboard()); return; }
                $stepData['reported_id'] = $targetUser['id'];
                User::updateStep($user['id'], 'report_reason', $stepData);
                $this->bale->sendMessage($chatId, "✅ یافت شد\nدلیل گزارش (حداقل 10 کاراکتر):");
            } elseif ($step === 'report_reason') {
                $reason = trim($text);
                if (mb_strlen($reason) < 10) { $this->bale->sendMessage($chatId, '❌ خیلی کوتاه'); return; }
                try {
                    BlockReport::report($user['id'], $stepData['reported_id'], $reason);
                    User::updateStep($user['id'], 'idle');
                    $this->bale->sendMessage($chatId, "✅ گزارش ثبت شد. ممنون!", $this->bale->mainMenuKeyboard());
                } catch (Throwable $e) { $this->bale->sendMessage($chatId, '❌ خطا: ' . $e->getMessage(), $this->bale->mainMenuKeyboard()); User::updateStep($user['id'], 'idle'); }
            } elseif ($step === 'report_user') {
                $reportedId = $stepData['reported_id'] ?? 0;
                if ($reportedId <= 0) { User::updateStep($user['id'], 'idle'); return; }
                $reason = trim($text);
                if (mb_strlen($reason) < 5) { $this->bale->sendMessage($chatId, '❌ کوتاه'); return; }
                try {
                    BlockReport::report($user['id'], $reportedId, $reason);
                    User::updateStep($user['id'], 'idle');
                    if (!empty($stepData['message_id'])) { try { $this->bale->deleteMessage($chatId, (int) $stepData['message_id']); } catch (Throwable $e) {} }
                    $this->bale->sendMessage($chatId, "✅ ثبت شد", $this->bale->mainMenuKeyboard());
                } catch (Throwable $e) { $this->bale->sendMessage($chatId, '❌ ' . $e->getMessage(), $this->bale->mainMenuKeyboard()); User::updateStep($user['id'], 'idle'); }
            }
        } catch (Throwable $e) { Logger::error('handleReport failed: ' . $e->getMessage()); }
    }

    public function handleCallback(array $user, string $callbackId, string $data): void
    {
        try {
            $chatId = (int) $user['bale_id'];

            if (str_starts_with($data, 'vip_buy_')) {
                $plan = substr($data, 8);
                $result = Vip::purchaseWithCoins($user['id'], $plan);
                $this->bale->answerCallbackQuery($callbackId, $result['message'], true);
                if ($result['success']) { $this->bale->sendMessage($chatId, $result['message'], $this->bale->mainMenuKeyboard()); } else { $this->showVip($user); }
                return;
            }
            if ($data === 'vip_buy' || $data === 'settings_vip') { $this->bale->answerCallbackQuery($callbackId); $this->showVip($user); return; }
            if ($data === 'settings_shop') { $this->bale->answerCallbackQuery($callbackId); $this->showShop($user); return; }
            if (str_starts_with($data, 'shop_')) { $this->handleShopPurchase($user, $callbackId, substr($data, 5)); return; }

            switch ($data) {
                case 'settings_back':
                    User::updateStep($user['id'], 'idle'); $this->bale->answerCallbackQuery($callbackId); $this->bale->sendMessage($chatId, 'منوی اصلی', $this->bale->mainMenuKeyboard()); break;
                case 'settings_filter':
                    $this->bale->answerCallbackQuery($callbackId); $this->bale->sendMessage($chatId, "🔍 فیلتر پیشرفته به زودی", $this->bale->mainMenuKeyboard()); break;
                case 'settings_privacy':
                    $this->bale->answerCallbackQuery($callbackId);
                    $profile = Profile::findByUserId($user['id']);
                    $isVisible = $profile['is_visible'] ?? 1;
                    $this->bale->sendMessage($chatId, "👁 وضعیت: " . ($isVisible ? "قابل مشاهده" : "مخفی"), $this->bale->inlineKeyboard([[['text' => $isVisible ? '🙈 مخفی' : '👁 نمایش', 'callback_data' => 'profile_toggle']],[['text' => '🔙 بازگشت', 'callback_data' => 'settings_back']]]));
                    break;
                case 'settings_rules':
                    $this->bale->answerCallbackQuery($callbackId); $this->bale->sendMessage($chatId, "⚖ قوانین:\n1. احترام\n2. بدون محتوای نامناسب\n3. بدون تبلیغ\nتخلف = بن", $this->bale->mainMenuKeyboard()); break;
                case 'settings_support':
                    $this->bale->answerCallbackQuery($callbackId); $this->bale->sendMessage($chatId, "🎩 پشتیبانی: @support_username", $this->bale->mainMenuKeyboard()); break;
                case 'settings_report':
                    $this->bale->answerCallbackQuery($callbackId); $this->startReport($user); break;
                case 'daily_bonus':
                    $this->bale->answerCallbackQuery($callbackId); $result = User::claimDailyBonus($user['id']); $this->bale->sendMessage($chatId, $result['message'], $this->bale->mainMenuKeyboard()); break;
                case 'noop':
                    $this->bale->answerCallbackQuery($callbackId); break;
                default:
                    $this->bale->answerCallbackQuery($callbackId); break;
            }
        } catch (Throwable $e) {
            Logger::error('Settings callback failed: ' . $e->getMessage(), ['data' => $data]);
            try { $this->bale->answerCallbackQuery($callbackId, '✅'); } catch (Throwable $e2) {}
        }
    }

    private function handleShopPurchase(array $user, string $callbackId, string $itemId): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $balance = Coin::getBalance($user['id']);
            switch ($itemId) {
                case 'vip_monthly':
                    $result = Vip::purchaseWithCoins($user['id'], 'monthly'); $this->bale->answerCallbackQuery($callbackId, $result['message'], true); $this->bale->sendMessage($chatId, $result['message'], $this->bale->mainMenuKeyboard()); break;
                case 'boost_profile':
                    if ($balance < 20) { $this->bale->answerCallbackQuery($callbackId, "⚠️ 20 سکه نیاز", true); return; }
                    if (Coin::deduct($user['id'], 20, 'بوست')) { Database::execute('UPDATE profiles SET boost_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id = ?', [$user['id']]); $this->bale->answerCallbackQuery($callbackId, '🚀 بوست شد!', true); $this->bale->sendMessage($chatId, "🚀 بوست 24 ساعته فعال", $this->bale->mainMenuKeyboard()); }
                    break;
                case 'super_like':
                    if ($balance < 15) { $this->bale->answerCallbackQuery($callbackId, "⚠️ 15 سکه", true); return; }
                    if (Coin::deduct($user['id'], 15, 'سوپر لایک')) { $this->bale->answerCallbackQuery($callbackId, '💘 5 سوپر لایک خریداری شد', true); $this->bale->sendMessage($chatId, "💘 5 سوپر لایک", $this->bale->mainMenuKeyboard()); }
                    break;
                default: $this->bale->answerCallbackQuery($callbackId, '❌ نامعتبر'); break;
            }
        } catch (Throwable $e) { Logger::error('Shop purchase failed: ' . $e->getMessage()); try { $this->bale->answerCallbackQuery($callbackId, '❌ خطا'); } catch (Throwable $e2) {} }
    }
}
