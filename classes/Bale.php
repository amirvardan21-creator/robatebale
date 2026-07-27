<?php

/**
 * Bale API Client - نسخه بهبود یافته + بازی
 */

class Bale
{
    private string $apiUrl;
    private int $timeout = 15;
    private int $maxRetries = 2;

    public function __construct() { $this->apiUrl = BALE_API_URL; }

    private function request(string $method, array $params = [], bool $isMultipart = false): ?array
    {
        $url = $this->apiUrl . $method;
        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $params['reply_markup'] = json_encode($params['reply_markup'], JSON_UNESCAPED_UNICODE);
        }
        $attempt = 0;
        while ($attempt <= $this->maxRetries) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'BaleDatingBot/2.0',
            ];
            if ($isMultipart) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = $params; }
            else { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = http_build_query($params); }
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            if ($curlError) {
                Logger::warning("Bale API cURL error (attempt $attempt): $curlError", ['method' => $method]);
                $attempt++;
                if ($attempt <= $this->maxRetries) { usleep(500000 * $attempt); continue; }
                Logger::error("[$method] cURL failed: $curlError");
                return null;
            }
            $data = json_decode($response, true);
            if (!$data) { Logger::error("[$method] Invalid JSON: $response"); return null; }
            if (!$data['ok']) {
                $errorCode = $data['error_code'] ?? 0;
                $description = $data['description'] ?? 'Unknown';
                if (in_array($errorCode, [429, 500, 502, 503, 504]) && $attempt < $this->maxRetries) {
                    $attempt++; sleep($errorCode===429?2:1); continue;
                }
                if (str_contains($description, 'message to delete not found') || str_contains($description, 'message is not modified')) return null;
                Logger::error("[$method] API error $errorCode: $description");
                return null;
            }
            return $data['result'] ?? null;
        }
        return null;
    }

    public function sendMessage(int $chatId, string $text, array $replyMarkup = [], string $parseMode = 'Markdown'): ?array
    {
        if (mb_strlen($text) > 4000) $text = mb_substr($text, 0, 4000) . '...';
        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($parseMode) $params['parse_mode'] = $parseMode;
        if (!empty($replyMarkup)) $params['reply_markup'] = $replyMarkup;
        $result = $this->request('sendMessage', $params);
        if ($result === null && $parseMode !== '') { unset($params['parse_mode']); $result = $this->request('sendMessage', $params); }
        if ($result === null && $parseMode === 'Markdown') {
            $params['text'] = str_replace(['`','[',']'], ["'","(",")"], $text);
            $params['parse_mode'] = 'Markdown';
            $result = $this->request('sendMessage', $params);
            if ($result === null) { unset($params['parse_mode']); $result = $this->request('sendMessage', $params); }
        }
        return $result;
    }

    public function sendPhoto(int $chatId, string $photoFileId, string $caption = '', array $replyMarkup = []): ?array
    {
        if (mb_strlen($caption) > 1000) $caption = mb_substr($caption, 0, 1000) . '...';
        $params = ['chat_id' => $chatId, 'photo' => $photoFileId, 'caption' => $caption];
        if (!empty($caption)) $params['parse_mode'] = 'Markdown';
        if (!empty($replyMarkup)) $params['reply_markup'] = $replyMarkup;
        $result = $this->request('sendPhoto', $params);
        if ($result === null && !empty($caption)) { unset($params['parse_mode']); $result = $this->request('sendPhoto', $params); }
        if ($result === null) { return $this->sendMessage($chatId, $caption ?: '🖼 عکس', $replyMarkup); }
        return $result;
    }

    public function sendChatAction(int $chatId, string $action = 'typing'): ?array { return $this->request('sendChatAction', ['chat_id'=>$chatId,'action'=>$action]); }
    public function editMessageText(int $chatId, int $messageId, string $text, array $replyMarkup = []): ?array { $p=['chat_id'=>$chatId,'message_id'=>$messageId,'text'=>$text]; if (!empty($replyMarkup)) $p['reply_markup']=$replyMarkup; return $this->request('editMessageText', $p); }
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): ?array { if ($callbackQueryId==='') return null; return $this->request('answerCallbackQuery', ['callback_query_id'=>$callbackQueryId,'text'=>$text,'show_alert'=>$showAlert]); }
    public function deleteMessage(int $chatId, int $messageId): ?array { return $this->request('deleteMessage', ['chat_id'=>$chatId,'message_id'=>$messageId]); }
    public function setWebhook(string $url): ?array { return $this->request('setWebhook', ['url'=>$url]); }
    public function deleteWebhook(): ?array { return $this->request('deleteWebhook'); }
    public function getWebhookInfo(): ?array { return $this->request('getWebhookInfo'); }
    public function getMe(): ?array { return $this->request('getMe'); }
    public function getFile(string $fileId): ?array { return $this->request('getFile', ['file_id'=>$fileId]); }
    public function getUpdate(): ?array { $input = file_get_contents('php://input'); if (empty($input)) return null; $data = json_decode($input, true); return $data ?: null; }

    // Keyboards
    public function mainMenuKeyboard(): array {
        return ['keyboard'=>[[['text'=>'🕶 ملاقات مخفیانه']],[['text'=>'🔎 جستجوی پیشرفته'],['text'=>'❤️ پیدا کردن دوست']],[['text'=>'💰 ثروت من'],['text'=>'📁 پرونده من'],['text'=>'💬 گفتگوهای من']],[['text'=>'🎁 جایزه روزانه'],['text'=>'👥 دعوت دوستان'],['text'=>'🏆 برترین‌ها']],[['text'=>'💎 خرید اشتراک'],['text'=>'⚙ تنظیمات']]],'resize_keyboard'=>true,'one_time_keyboard'=>false];
    }
    public function inlineKeyboard(array $buttons): array { return ['inline_keyboard'=>$buttons]; }
    public function removeKeyboard(): array { return ['remove_keyboard'=>true]; }

    public function secretMeetingKeyboard(): array {
        $randomCost = COST_RANDOM > 0 ? COST_RANDOM . ' سکه' : 'رایگان';
        return ['keyboard'=>[[['text'=>"🎲 جستجوی شانسی ($randomCost)"]],[['text'=>'👨 جستجوی پسر (' . COST_GENDER . ' سکه)'],['text'=>'👩 جستجوی دختر (' . COST_GENDER . ' سکه)']],[['text'=>'🗺 جستجوی بر اساس استان (' . COST_PROVINCE . ' سکه)']],[['text'=>'🔙 بازگشت']]],'resize_keyboard'=>true,'one_time_keyboard'=>false];
    }

    public function secretMeetingChatKeyboard(int $partnerId, int $roomId): array {
        return [
            'keyboard'=>[
                [['text'=>'👤 مشاهده پرونده مخاطب'],['text'=>'🎮 بازی']],
                [['text'=>'🎁 هدیه دادن'],['text'=>'🚨 گزارش کاربر']],
                [['text'=>'🗑 حذف پیام‌ها'],['text'=>'❌ پایان گفت‌وگو']],
            ],
            'resize_keyboard'=>true,'one_time_keyboard'=>false,
        ];
    }

    public function matchChatKeyboard(int $partnerId, int $matchId): array {
        return $this->inlineKeyboard([
            [['text'=>'🎮 بازی','callback_data'=>'game_menu_'.$matchId],['text'=>'👤 پروفایل','callback_data'=>'sm_view_profile_'.$partnerId]],
            [['text'=>'🚫 بلاک','callback_data'=>'chat_block_'.$partnerId],['text'=>'🗑 حذف','callback_data'=>'chat_delete_'.$matchId],['text'=>'🔙 بازگشت','callback_data'=>'chat_back']],
        ]);
    }

    public function discoveryKeyboard(int $targetUserId): array {
        return $this->inlineKeyboard([
            [['text'=>'❤️ پسندیدن','callback_data'=>'like_'.$targetUserId],['text'=>'❌ رد کردن','callback_data'=>'dislike_'.$targetUserId]],
            [['text'=>'⏭ بعدی','callback_data'=>'next_'.$targetUserId],['text'=>'🚨 گزارش','callback_data'=>'report_'.$targetUserId]],
        ]);
    }

    public function genderKeyboard(): array { return ['keyboard'=>[[['text'=>'👨 مرد'],['text'=>'👩 زن']],[['text'=>'🔙 بازگشت']]],'resize_keyboard'=>true,'one_time_keyboard'=>true]; }
    public function ageKeyboard(): array { $rows=[];$row=[];for($i=18;$i<=50;$i++){$row[]=['text'=>(string)$i];if(count($row)===5){$rows[]=$row;$row=[];}}if($row)$rows[]=$row;$rows[]=[['text'=>'🔙 بازگشت']];return ['keyboard'=>$rows,'resize_keyboard'=>true,'one_time_keyboard'=>true]; }
    public function provinceKeyboard(): array {
        $provinces=$this->getProvinces();$rows=[];$row=[];foreach($provinces as $p){$row[]=['text'=>$p];if(count($row)===2){$rows[]=$row;$row=[];}}if($row)$rows[]=$row;$rows[]=[['text'=>'🔙 بازگشت']];
        return ['keyboard'=>$rows,'resize_keyboard'=>true,'one_time_keyboard'=>true];
    }
    public function getProvinces(): array { return ['تهران','اصفهان','فارس','خراسان رضوی','آذربایجان شرقی','آذربایجان غربی','اردبیل','البرز','ایلام','بوشهر','چهارمحال و بختیاری','خراسان جنوبی','خراسان شمالی','خوزستان','زنجان','سمنان','سیستان و بلوچستان','گیلان','گلستان','همدان','هرمزگان','کردستان','کرمان','کرمانشاه','کهگیلویه و بویراحمد','لرستان','مازندران','مرکزی','قزوین','قم','یزد',]; }

    // ===== Game Keyboards =====
    public function gameMenuKeyboard(int $conversationId, string $conversationType): array
    {
        return $this->inlineKeyboard([
            [['text'=>'🤔 حقیقت یا جرئت','callback_data'=>'game_invite_td_'.$conversationId.'_'.$conversationType]],
            [['text'=>'✂️ سنگ کاغذ قیچی','callback_data'=>'game_invite_rps_'.$conversationId.'_'.$conversationType]],
            [['text'=>'⭕ دوز (X O)','callback_data'=>'game_invite_ttt_'.$conversationId.'_'.$conversationType]],
            [['text'=>'🔙 بازگشت','callback_data'=>'game_close']],
        ]);
    }

    public function gameInviteKeyboard(int $gameId): array
    {
        return $this->inlineKeyboard([
            [['text'=>'✅ قبول','callback_data'=>'game_accept_'.$gameId],['text'=>'❌ رد','callback_data'=>'game_decline_'.$gameId]],
        ]);
    }

    public function truthDareChoiceKeyboard(int $gameId): array
    {
        return $this->inlineKeyboard([
            [['text'=>'🤔 حقیقت','callback_data'=>'game_td_truth_'.$gameId],['text'=>'😈 جرئت','callback_data'=>'game_td_dare_'.$gameId]],
            [['text'=>'🚪 خروج از بازی','callback_data'=>'game_end_'.$gameId]],
        ]);
    }

    public function truthDareNextKeyboard(int $gameId): array
    {
        return $this->inlineKeyboard([
            [['text'=>'🔄 بعدی','callback_data'=>'game_td_next_'.$gameId],['text'=>'🚪 پایان','callback_data'=>'game_end_'.$gameId]],
        ]);
    }

    public function rpsChoiceKeyboard(int $gameId): array
    {
        return $this->inlineKeyboard([
            [['text'=>'🪨 سنگ','callback_data'=>'game_rps_rock_'.$gameId],['text'=>'📄 کاغذ','callback_data'=>'game_rps_paper_'.$gameId],['text'=>'✂️ قیچی','callback_data'=>'game_rps_scissors_'.$gameId]],
            [['text'=>'🚪 خروج','callback_data'=>'game_end_'.$gameId]],
        ]);
    }

    public function tictactoeBoardKeyboard(array $board, int $gameId, bool $finished = false): array
    {
        $buttons = [];
        $symbols = [null => '⬜', 'X' => '❌', 'O' => '⭕'];
        // اگر board شامل userId باشه، تبدیل کن
        for ($row=0;$row<3;$row++) {
            $rowButtons = [];
            for ($col=0;$col<3;$col++) {
                $idx = $row*3+$col;
                $val = $board[$idx] ?? null;
                $display = '⬜';
                $canClick = !$finished && $val === null;
                if ($val !== null) {
                    // val می‌تونه 'X','O' یا userId باشه - برای نمایش X/O
                    if ($val === 'X' || $val === 'O') $display = $val === 'X' ? '❌' : '⭕';
                    else $display = is_string($val) ? $val : '❓';
                    // اگر val userId باشه، نمی‌دونیم X یا O - برای سادگی چک کن
                    // در دیتای ما board شامل 'X'/'O' هست، پس همین کافیه
                }
                // اگر board مقدار userId داشته باشه، باید بفهمیم X یا O - برای نمایش از data استفاده می‌کنیم
                // فعلا ساده: اگر null، قابل کلیک
                if ($canClick) {
                    $rowButtons[] = ['text'=>$display, 'callback_data'=>'game_ttt_'.$idx.'_'.$gameId];
                } else {
                    // اگر پر شده، دکمه غیرفعال با display
                    // برای اینکه کلیک نشه، callback_data بی‌اثر
                    if ($val === null) $display = '⬜';
                    elseif ($val === 'X') $display = '❌';
                    elseif ($val === 'O') $display = '⭕';
                    else {
                        // اگر val عدد (userId) باشه، باید ببینیم کدوم بازیکن
                        $display = '⬜';
                    }
                    $rowButtons[] = ['text'=>$display, 'callback_data'=>'noop'];
                }
            }
            $buttons[] = $rowButtons;
        }
        $buttons[] = [['text'=>'🚪 پایان بازی','callback_data'=>'game_end_'.$gameId]];
        return $this->inlineKeyboard($buttons);
    }

    public function gameEndKeyboard(): array
    {
        return $this->inlineKeyboard([
            [['text'=>'🔄 بازی دوباره','callback_data'=>'game_again'],['text'=>'🎮 منوی بازی','callback_data'=>'game_menu_0_match']],
            [['text'=>'🔙 بازگشت به چت','callback_data'=>'game_close']],
        ]);
    }
}
