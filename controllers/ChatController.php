<?php

/**
 * ChatController - نسخه با بازی
 * چت + بازی حقیقت/جرئت، سنگ کاغذ قیچی، دوز
 */

class ChatController
{
    private Bale $bale;

    public function __construct(Bale $bale) { $this->bale = $bale; }

    public function showMatches(array $user): void
    {
        $chatId = (int) $user['bale_id'];
        if (($user['step'] ?? '') === 'chatting') { User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle'); }
        $matches = MatchModel::getUserMatches($user['id'], 20);
        if (empty($matches)) {
            $this->bale->sendMessage($chatId, "💔 *هنوز مچی نداری*\n\n❤️ پیدا کردن دوست بزن و لایک کن\n🕶 ملاقات مخفیانه هم امتحان کن", $this->bale->mainMenuKeyboard());
            return;
        }
        $buttons = [];
        foreach ($matches as $match) {
            $unread = !empty($match['unread_count']) ? " (🔴{$match['unread_count']})" : "";
            $online = '';
            if (!empty($match['last_seen_at'])) { $diff = time() - strtotime($match['last_seen_at']); if ($diff < 600) $online = '🟢 '; }
            $buttons[] = [['text' => "{$online}💬 {$match['partner_name']} ({$match['age']} ساله){$unread}", 'callback_data' => 'chat_open_' . $match['id']]];
        }
        $buttons[] = [['text' => '🔙 منوی اصلی', 'callback_data' => 'chat_back']];
        $this->bale->sendMessage($chatId, "💬 *گفتگوهای شما (" . count($matches) . "):*", $this->bale->inlineKeyboard($buttons));
    }

    public function openChat(array $user, string $callbackId, int $matchId): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            $match = Database::fetch('SELECT * FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?) AND is_active = 1', [$matchId, $user['id'], $user['id']]);
            if (!$match) { $this->bale->answerCallbackQuery($callbackId, '❌ یافت نشد', true); return; }
            $partnerId = ($match['user1_id'] == $user['id']) ? $match['user2_id'] : $match['user1_id'];
            $partnerProfile = Profile::findByUserId($partnerId);
            $partnerUser = User::findById($partnerId);
            if (!$partnerProfile || !$partnerUser) { $this->bale->answerCallbackQuery($callbackId, '❌ کاربر نیست', true); return; }

            MatchModel::markAsRead($matchId, 'match', $user['id']);
            User::updateStep($user['id'], 'chatting', ['match_id' => $matchId, 'partner_id' => $partnerId]);
            $this->bale->answerCallbackQuery($callbackId);

            $keyboard = $this->bale->matchChatKeyboard($partnerId, $matchId);
            $this->bale->sendMessage($chatId, "💬 *چت با {$partnerProfile['name']}* ({$partnerProfile['age']} ساله)\n\nپیام بنویس یا 🎮 بازی کن:", $keyboard);
        } catch (Throwable $e) {
            Logger::error('openChat failed: ' . $e->getMessage());
            try { $this->bale->answerCallbackQuery($callbackId, '❌ خطا'); } catch (Throwable $e2) {}
        }
    }

    public function sendChatMessage(array $user, string $text, ?array $photo = null): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            if (!RateLimiter::checkMessage($user['id'])) { $this->bale->sendMessage($chatId, '⏳ کمی صبر کن'); return; }

            if ($photo) { $fileId = end($photo)['file_id'] ?? null; if (!$fileId) { $this->bale->sendMessage($chatId, '❌ خطا در عکس'); return; } $content = $fileId; $type = 'photo'; }
            else {
                if ($text === '' || str_starts_with($text, '/')) { $this->bale->sendMessage($chatId, 'پیام بنویس'); return; }
                $validation = Validator::textMessage($text);
                if (!$validation['valid']) { $this->bale->sendMessage($chatId, '❌ ' . $validation['error']); return; }
                $content = $validation['clean']; $type = 'text';
            }

            $stepData = User::getStepData($user);
            $conversationType = null; $conversationId = null; $partnerId = null;

            if (($user['current_status'] ?? 'idle') === 'in_secret_meeting' && !empty($user['current_secret_meeting_room_id'])) {
                $room = SecretMeetingRoom::getRoomById((int) $user['current_secret_meeting_room_id']);
                if ($room && $room['status'] === 'active') { $conversationType = 'secret_meeting'; $conversationId = $room['id']; $partnerId = ($room['user1_id'] == $user['id']) ? $room['user2_id'] : $room['user1_id']; }
            } elseif ($stepData && !empty($stepData['match_id'])) { $conversationType = 'match'; $conversationId = $stepData['match_id']; $partnerId = $stepData['partner_id']; }

            if (!$conversationType || !$conversationId || !$partnerId) { User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle'); $this->bale->sendMessage($chatId, '❌ وضعیت چت نامشخص', $this->bale->mainMenuKeyboard()); return; }
            if (BlockReport::isBlockedEither($user['id'], $partnerId)) { $this->bale->sendMessage($chatId, '🚫 بلاک شده', $this->bale->mainMenuKeyboard()); User::updateStep($user['id'], 'idle'); return; }
            $partnerUser = User::findById($partnerId);
            if (!$partnerUser || User::isBlocked($partnerId)) { User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle'); $this->bale->sendMessage($chatId, '❌ کاربر نیست', $this->bale->mainMenuKeyboard()); return; }

            MatchModel::saveMessage($conversationId, $user['id'], $content, $type, $conversationType);
            $this->ensurePartnerInChatStep($partnerUser, $user, $conversationType, $conversationId);
            $partnerKeyboard = $conversationType === 'secret_meeting' ? $this->bale->secretMeetingChatKeyboard($user['id'], $conversationId) : $this->bale->matchChatKeyboard($user['id'], $conversationId);

            if ($type === 'photo') {
                $myProfile = Profile::findByUserId($user['id']);
                $caption = "📸 از " . ($myProfile['name'] ?? 'کاربر');
                $this->bale->sendPhoto((int) $partnerUser['bale_id'], $content, $caption, $partnerKeyboard);
                $this->bale->sendMessage($chatId, '✅ عکس رفت', $this->resolveActiveChatKeyboard($user, $partnerId));
            } else {
                $this->bale->sendMessage((int) $partnerUser['bale_id'], $text, $partnerKeyboard);
            }
        } catch (Throwable $e) { Logger::error('sendChatMessage failed: ' . $e->getMessage()); }
    }

    private function ensurePartnerInChatStep(array $partner, array $sender, string $conversationType, int $conversationId): void
    {
        try {
            $partnerStep = $partner['step'] ?? 'idle';
            if (str_starts_with($partnerStep, 'register_') || str_starts_with($partnerStep, 'editing_')) return;
            if (!in_array($partnerStep, ['idle', 'chatting'], true)) return;
            if ($conversationType === 'secret_meeting') {
                if (($partner['current_status'] ?? 'idle') !== 'in_secret_meeting') User::updateUserStatus($partner['id'], 'in_secret_meeting', $conversationId);
            } else {
                $existing = User::getStepData($partner) ?? [];
                if (empty($existing['match_id']) || (int) $existing['match_id'] !== $conversationId) {
                    User::updateStep($partner['id'], 'chatting', ['match_id' => $conversationId, 'partner_id' => $sender['id']]);
                } elseif ($partnerStep !== 'chatting') User::updateStep($partner['id'], 'chatting', $existing);
            }
        } catch (Throwable $e) {}
    }

    public function handleCallback(array $user, string $callbackId, string $data): void
    {
        try {
            if (str_starts_with($data, 'game_')) { $this->handleGameCallback($user, $callbackId, $data); return; }

            $chatId = (int) $user['bale_id'];
            if (str_starts_with($data, 'chat_open_')) { $this->openChat($user, $callbackId, (int) substr($data, 10)); }
            elseif (str_starts_with($data, 'chat_block_')) {
                BlockReport::block($user['id'], (int) substr($data, 11));
                User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle');
                $this->bale->answerCallbackQuery($callbackId, '🚫 بلاک شد', true);
                $this->bale->sendMessage($chatId, "🚫 بلاک شد", $this->bale->mainMenuKeyboard());
            }
            elseif (str_starts_with($data, 'chat_delete_')) {
                MatchModel::deleteMatch((int) substr($data, 12), $user['id']);
                User::updateStep($user['id'], 'idle');
                $this->bale->answerCallbackQuery($callbackId, '🗑 حذف شد', true);
                $this->bale->sendMessage($chatId, '🗑 حذف شد', $this->bale->mainMenuKeyboard());
            }
            elseif ($data === 'chat_back' || $data === 'chat_list') {
                User::updateStep($user['id'], 'idle'); User::updateUserStatus($user['id'], 'idle');
                $this->bale->answerCallbackQuery($callbackId);
                if ($data === 'chat_list') $this->showMatches($user); else $this->bale->sendMessage($chatId, 'منو', $this->bale->mainMenuKeyboard());
            }
            elseif (str_starts_with($data, 'sm_view_profile_')) { $this->handleViewProfile($user, $callbackId, (int) substr($data, 16)); }
            elseif (str_starts_with($data, 'sm_gift_')) { $this->handleGiveGift($user, $callbackId, (int) substr($data, 8)); }
            elseif (str_starts_with($data, 'sm_end_chat_')) { $this->handleEndChat($user, $callbackId, (int) substr($data, 12)); }
            elseif (str_starts_with($data, 'sm_delete_messages_')) { $this->handleDeleteMessages($user, $callbackId, (int) substr($data, 19)); }
            elseif (str_starts_with($data, 'sm_report_user_')) { $this->handleReportUser($user, $callbackId, (int) substr($data, 15)); }
            else { $this->bale->answerCallbackQuery($callbackId); }
        } catch (Throwable $e) {
            Logger::error('Chat callback failed: ' . $e->getMessage());
            try { $this->bale->answerCallbackQuery($callbackId, '❌ خطا'); } catch (Throwable $e2) {}
        }
    }

    // ===== GAME HANDLERS =====
    public function handleGameCallback(array $user, string $callbackId, string $data): void
    {
        try {
            $chatId = (int) $user['bale_id'];
            Logger::info("Game callback: user={$user['id']} data=$data");

            if ($data === 'game_close') { $this->bale->answerCallbackQuery($callbackId); return; }

            if ($data === 'game_again') {
                $this->bale->answerCallbackQuery($callbackId);
                $this->showGameMenu($user);
                return;
            }

            if (str_starts_with($data, 'game_menu_')) {
                $this->bale->answerCallbackQuery($callbackId);
                $this->showGameMenu($user);
                return;
            }

            if (str_starts_with($data, 'game_invite_')) {
                // format: game_invite_td_{convId}_{type} or game_invite_rps_... etc
                // td = truth dare, rps, ttt
                $this->handleGameInvite($user, $callbackId, $data);
                return;
            }

            if (str_starts_with($data, 'game_accept_')) {
                $gameId = (int) substr($data, strlen('game_accept_'));
                $this->handleGameAccept($user, $callbackId, $gameId);
                return;
            }

            if (str_starts_with($data, 'game_decline_')) {
                $gameId = (int) substr($data, strlen('game_decline_'));
                $this->handleGameDecline($user, $callbackId, $gameId);
                return;
            }

            if (str_starts_with($data, 'game_end_')) {
                $gameId = (int) substr($data, strlen('game_end_'));
                $this->handleGameEnd($user, $callbackId, $gameId);
                return;
            }

            // Truth Dare
            if (str_starts_with($data, 'game_td_truth_')) {
                $gameId = (int) substr($data, strlen('game_td_truth_'));
                $this->handleTruthDareChoice($user, $callbackId, $gameId, 'truth');
                return;
            }
            if (str_starts_with($data, 'game_td_dare_')) {
                $gameId = (int) substr($data, strlen('game_td_dare_'));
                $this->handleTruthDareChoice($user, $callbackId, $gameId, 'dare');
                return;
            }
            if (str_starts_with($data, 'game_td_next_')) {
                $gameId = (int) substr($data, strlen('game_td_next_'));
                $this->handleTruthDareNext($user, $callbackId, $gameId);
                return;
            }

            // RPS
            if (str_starts_with($data, 'game_rps_rock_')) { $this->handleRPSChoice($user, $callbackId, (int) substr($data, strlen('game_rps_rock_')), 'rock'); return; }
            if (str_starts_with($data, 'game_rps_paper_')) { $this->handleRPSChoice($user, $callbackId, (int) substr($data, strlen('game_rps_paper_')), 'paper'); return; }
            if (str_starts_with($data, 'game_rps_scissors_')) { $this->handleRPSChoice($user, $callbackId, (int) substr($data, strlen('game_rps_scissors_')), 'scissors'); return; }

            // TicTacToe: game_ttt_{0-8}_{gameId}
            if (str_starts_with($data, 'game_ttt_')) {
                $parts = explode('_', $data);
                // format: game ttt {idx} {gameId}
                if (count($parts) >= 4) {
                    $idx = (int) $parts[2];
                    $gameId = (int) $parts[3];
                    $this->handleTicTacToeMove($user, $callbackId, $gameId, $idx);
                }
                return;
            }

            $this->bale->answerCallbackQuery($callbackId);
        } catch (Throwable $e) {
            Logger::error('Game callback failed: ' . $e->getMessage() . ' data=' . $data);
            try { $this->bale->answerCallbackQuery($callbackId, '❌ خطا در بازی'); } catch (Throwable $e2) {}
        }
    }

    public function showGameMenu(array $user): void
    {
        try {
            $conv = $this->getCurrentConversation($user);
            if (!$conv) { $this->bale->sendMessage((int)$user['bale_id'], '❌ اول وارد چت شو', $this->bale->mainMenuKeyboard()); return; }

            $text = "🎮 *منوی بازی‌ها*\n\n"
                  . "با طرف مقابلت بازی کن و یخ‌ها رو بشکن! 😎\n\n"
                  . "🤔 *حقیقت یا جرئت* - سوالای باحال و چالش‌ها\n"
                  . "✂️ *سنگ کاغذ قیچی* - سریع و هیجان‌انگیز\n"
                  . "⭕ *دوز* - نوبتی X O\n\n"
                  . "کدوم بازی؟";

            $this->bale->sendMessage(
                (int)$user['bale_id'],
                $text,
                $this->bale->gameMenuKeyboard($conv['conversation_id'], $conv['conversation_type'])
            );
        } catch (Throwable $e) { Logger::error('showGameMenu failed: ' . $e->getMessage()); }
    }

    private function handleGameInvite(array $user, string $callbackId, string $data): void
    {
        // data: game_invite_{td|rps|ttt}_{convId}_{convType}
        // مثال: game_invite_td_123_match
        $chatId = (int) $user['bale_id'];
        $parts = explode('_', $data);
        // [game, invite, td, 123, match] -> 5 parts
        if (count($parts) < 5) { $this->bale->answerCallbackQuery($callbackId, '❌ خطا'); return; }

        $typeCode = $parts[2];
        $convId = (int) $parts[3];
        $convType = $parts[4];

        $gameType = match($typeCode) {
            'td' => Game::TYPE_TRUTH_DARE,
            'rps' => Game::TYPE_RPS,
            'ttt' => Game::TYPE_TICTACTOE,
            default => null
        };
        if (!$gameType) { $this->bale->answerCallbackQuery($callbackId, '❌ نوع بازی نامعتبر'); return; }

        $conv = $this->getCurrentConversation($user);
        if (!$conv) { $this->bale->answerCallbackQuery($callbackId, '❌ چت پیدا نشد', true); return; }

        // اگر convId با فعلی فرق داره، از همون فعلی استفاده کن
        $convId = $conv['conversation_id'];
        $convType = $conv['conversation_type'];
        $partnerId = $conv['partner_id'];

        $partner = User::findById($partnerId);
        if (!$partner) { $this->bale->answerCallbackQuery($callbackId, '❌ کاربر مقابل نیست', true); return; }

        $gameId = Game::createGame($convType, $convId, $user['id'], $partnerId, $gameType);
        $gameName = Game::getGameTypeName($gameType);
        $gameEmoji = Game::getGameTypeEmoji($gameType);

        $this->bale->answerCallbackQuery($callbackId, "🎮 دعوت به {$gameName} ارسال شد");

        $this->bale->sendMessage(
            $chatId,
            "🎮 *دعوت به بازی {$gameEmoji} {$gameName}* برای طرف مقابل ارسال شد!\n\nمنتظر جواب باش...",
            $this->resolveActiveChatKeyboard($user, $partnerId)
        );

        $myProfile = Profile::findByUserId($user['id']);
        $myName = $myProfile['name'] ?? 'کاربر';

        $this->bale->sendMessage(
            (int) $partner['bale_id'],
            "🎮 *دعوت به بازی!*\n\n*{$myName}* تو رو به بازی *{$gameEmoji} {$gameName}* دعوت کرده!\n\nقبول می‌کنی؟",
            $this->bale->gameInviteKeyboard($gameId)
        );
    }

    private function handleGameAccept(array $user, string $callbackId, int $gameId): void
    {
        $game = Game::getGameById($gameId);
        if (!$game) { $this->bale->answerCallbackQuery($callbackId, '❌ بازی یافت نشد', true); return; }
        if ((int)$game['player2_id'] !== $user['id'] && (int)$game['player1_id'] !== $user['id']) { $this->bale->answerCallbackQuery($callbackId, '❌ دسترسی نداری', true); return; }

        Game::acceptGame($gameId, $user['id']);
        $this->bale->answerCallbackQuery($callbackId, '✅ بازی شروع شد!');

        $otherId = (int)$game['player1_id'] === $user['id'] ? (int)$game['player2_id'] : (int)$game['player1_id'];
        $otherUser = User::findById($otherId);

        $gameName = Game::getGameTypeName($game['game_type']);
        $gameEmoji = Game::getGameTypeEmoji($game['game_type']);

        // پیام به هر دو
        if ($game['game_type'] === Game::TYPE_TRUTH_DARE) {
            $this->startTruthDare($gameId);
        } elseif ($game['game_type'] === Game::TYPE_RPS) {
            $this->startRPS($gameId);
        } elseif ($game['game_type'] === Game::TYPE_TICTACTOE) {
            $this->startTicTacToe($gameId);
        }
    }

    private function handleGameDecline(array $user, string $callbackId, int $gameId): void
    {
        Game::declineGame($gameId);
        $this->bale->answerCallbackQuery($callbackId, '❌ رد شد');
        $game = Game::getGameById($gameId);
        if ($game) {
            $otherId = (int)$game['player1_id'] === $user['id'] ? (int)$game['player2_id'] : (int)$game['player1_id'];
            $otherUser = User::findById($otherId);
            if ($otherUser) {
                $this->bale->sendMessage((int)$otherUser['bale_id'], "❌ طرف مقابل دعوت به بازی رو رد کرد.", $this->resolveActiveChatKeyboard($otherUser, $user['id']));
            }
            $this->bale->sendMessage((int)$user['bale_id'], "❌ دعوت رو رد کردی.", $this->resolveActiveChatKeyboard($user, $otherId));
        }
    }

    private function handleGameEnd(array $user, string $callbackId, int $gameId): void
    {
        Game::finishGame($gameId);
        $this->bale->answerCallbackQuery($callbackId, '🚪 بازی تمام شد');
        $game = Game::getGameById($gameId);
        if ($game) {
            $p1 = User::findById((int)$game['player1_id']);
            $p2 = User::findById((int)$game['player2_id']);
            if ($p1) $this->bale->sendMessage((int)$p1['bale_id'], "🚪 *بازی تمام شد*\n\nامیدوارم لذت برده باشید! 🎮", $this->resolveActiveChatKeyboard($p1, (int)$game['player2_id']));
            if ($p2) $this->bale->sendMessage((int)$p2['bale_id'], "🚪 *بازی تمام شد*", $this->resolveActiveChatKeyboard($p2, (int)$game['player1_id']));
        }
    }

    // ===== Truth or Dare =====
    private function startTruthDare(int $gameId): void
    {
        $game = Game::getGameById($gameId);
        if (!$game) return;
        $p1 = User::findById((int)$game['player1_id']);
        $p2 = User::findById((int)$game['player2_id']);
        $data = $game['data'];
        $turnId = $data['turn'] ?? $game['player1_id'];
        $turnUser = User::findById($turnId);
        $turnName = Profile::findByUserId($turnId)['name'] ?? 'بازیکن';

        $text = "🤔 *حقیقت یا جرئت - شروع!*\n\nنوبت *{$turnName}* هست که انتخاب کنه:\nحقیقت یا جرئت؟";

        $keyboard = $this->bale->truthDareChoiceKeyboard($gameId);

        if ($p1) $this->bale->sendMessage((int)$p1['bale_id'], $text, $keyboard);
        if ($p2 && (int)$p2['id'] !== (int)$p1['id']) $this->bale->sendMessage((int)$p2['bale_id'], $text, $keyboard);
    }

    private function handleTruthDareChoice(array $user, string $callbackId, int $gameId, string $choice): void
    {
        $game = Game::getGameById($gameId);
        if (!$game || $game['status'] !== 'active') { $this->bale->answerCallbackQuery($callbackId, '❌ بازی فعال نیست', true); return; }
        $data = $game['data'];
        if ((int)$data['turn'] !== $user['id']) { $this->bale->answerCallbackQuery($callbackId, '⏳ نوبت تو نیست', true); return; }

        $question = $choice === 'truth' ? Game::getRandomTruth() : Game::getRandomDare();
        $label = $choice === 'truth' ? '🤔 حقیقت' : '😈 جرئت';

        $this->bale->answerCallbackQuery($callbackId, "$label انتخاب شد");

        $otherId = (int)$game['player1_id'] === $user['id'] ? (int)$game['player2_id'] : (int)$game['player1_id'];
        $otherUser = User::findById($otherId);
        $myProfile = Profile::findByUserId($user['id']);
        $myName = $myProfile['name'] ?? 'بازیکن';

        $text = "{$label} برای *{$myName}*:\n\n*{$question}*\n\nنوبت بعدی رو بزن:";

        $data['round'] = ($data['round'] ?? 1) + 1;
        $data['turn'] = $otherId; // نوبت بعدی طرف مقابل
        $data['last_' . $choice] = $question;
        Game::updateGameData($gameId, $data, $otherId);

        $keyboard = $this->bale->truthDareNextKeyboard($gameId);

        if ($otherUser) $this->bale->sendMessage((int)$otherUser['bale_id'], $text, $keyboard);
        $this->bale->sendMessage((int)$user['bale_id'], $text, $keyboard);
    }

    private function handleTruthDareNext(array $user, string $callbackId, int $gameId): void
    {
        $game = Game::getGameById($gameId);
        if (!$game) { $this->bale->answerCallbackQuery($callbackId, '❌ بازی نیست', true); return; }
        $data = $game['data'];
        $turnId = $data['turn'] ?? $game['player1_id'];
        if ((int)$turnId !== $user['id']) { $this->bale->answerCallbackQuery($callbackId, '⏳ نوبت تو نیست', true); return; }

        $turnProfile = Profile::findByUserId($turnId);
        $turnName = $turnProfile['name'] ?? 'بازیکن';
        $text = "🤔 *حقیقت یا جرئت - دور {$data['round']}*\n\nنوبت *{$turnName}*:\nحقیقت یا جرئت؟";
        $this->bale->answerCallbackQuery($callbackId);
        $keyboard = $this->bale->truthDareChoiceKeyboard($gameId);

        $p1 = User::findById((int)$game['player1_id']);
        $p2 = User::findById((int)$game['player2_id']);
        if ($p1) $this->bale->sendMessage((int)$p1['bale_id'], $text, $keyboard);
        if ($p2) $this->bale->sendMessage((int)$p2['bale_id'], $text, $keyboard);
    }

    // ===== RPS =====
    private function startRPS(int $gameId): void
    {
        $game = Game::getGameById($gameId);
        if (!$game) return;
        $p1 = User::findById((int)$game['player1_id']);
        $p2 = User::findById((int)$game['player2_id']);

        $text = "✂️ *سنگ کاغذ قیچی - شروع!*\n\nهر دو همزمان انتخاب کنید:\n🪨 سنگ، 📄 کاغذ، ✂️ قیچی\n\nاولین انتخاب مخفی می‌مونه تا هر دو انتخاب کنن!";

        $keyboard = $this->bale->rpsChoiceKeyboard($gameId);
        if ($p1) $this->bale->sendMessage((int)$p1['bale_id'], $text, $keyboard);
        if ($p2) $this->bale->sendMessage((int)$p2['bale_id'], $text, $keyboard);
    }

    private function handleRPSChoice(array $user, string $callbackId, int $gameId, string $choice): void
    {
        $game = Game::getGameById($gameId);
        if (!$game || $game['status'] !== 'active') { $this->bale->answerCallbackQuery($callbackId, '❌ بازی نیست', true); return; }

        $data = $game['data'];
        $data['choices'][$user['id']] = $choice;
        Game::updateGameData($gameId, $data);

        $this->bale->answerCallbackQuery($callbackId, '✅ انتخاب شد: ' . Game::rpsEmoji($choice));

        $p1Id = (int)$game['player1_id']; $p2Id = (int)$game['player2_id'];
        $choices = $data['choices'];

        if (count($choices) < 2) {
            // منتظر دیگری
            $otherId = $p1Id === $user['id'] ? $p2Id : $p1Id;
            $otherUser = User::findById($otherId);
            if ($otherUser) {
                $this->bale->sendMessage((int)$otherUser['bale_id'], "⏳ طرف مقابل انتخاب کرد، نوبت توئه!", $this->bale->rpsChoiceKeyboard($gameId));
            }
            $this->bale->sendMessage((int)$user['bale_id'], "⏳ منتظر انتخاب طرف مقابل...");
            return;
        }

        // هر دو انتخاب کردن - نتیجه
        $c1 = $choices[$p1Id] ?? null; $c2 = $choices[$p2Id] ?? null;
        if (!$c1 || !$c2) return;

        $winner = Game::checkRPSWinner($c1, $c2);
        $p1Profile = Profile::findByUserId($p1Id);
        $p2Profile = Profile::findByUserId($p2Id);
        $p1Name = $p1Profile['name'] ?? 'بازیکن1';
        $p2Name = $p2Profile['name'] ?? 'بازیکن2';

        $resultText = "✂️ *نتیجه سنگ کاغذ قیچی - دور {$data['round']}*\n\n"
                    . "{$p1Name}: " . Game::rpsEmoji($c1) . "\n"
                    . "{$p2Name}: " . Game::rpsEmoji($c2) . "\n\n";

        if ($winner === 0) {
            $resultText .= "🤝 *مساوی!* دوباره بازی کنید";
        } elseif ($winner === 1) {
            $data['score'][$p1Id] = ($data['score'][$p1Id] ?? 0) + 1;
            $resultText .= "🎉 *{$p1Name} برنده شد!*";
        } else {
            $data['score'][$p2Id] = ($data['score'][$p2Id] ?? 0) + 1;
            $resultText .= "🎉 *{$p2Name} برنده شد!*";
        }

        $resultText .= "\n\n📊 امتیاز: {$p1Name} {$data['score'][$p1Id]} - {$data['score'][$p2Id]} {$p2Name}";

        $data['history'][] = ['p1'=>$c1,'p2'=>$c2,'winner'=>$winner];
        $data['choices'] = [];
        $data['round']++;
        Game::updateGameData($gameId, $data);

        $keyboard = $this->bale->inlineKeyboard([
            [['text'=>'🔄 دوباره','callback_data'=>'game_rps_rock_'.$gameId],['text'=>'🎮 منوی بازی','callback_data'=>'game_menu_0_match']],
            [['text'=>'🚪 پایان','callback_data'=>'game_end_'.$gameId]],
        ]);

        // rps keyboard for next round
        $nextKeyboard = $this->bale->rpsChoiceKeyboard($gameId);

        $p1User = User::findById($p1Id); $p2User = User::findById($p2Id);
        if ($p1User) $this->bale->sendMessage((int)$p1User['bale_id'], $resultText . "\n\nدور بعدی:", $nextKeyboard);
        if ($p2User) $this->bale->sendMessage((int)$p2User['bale_id'], $resultText . "\n\nدور بعدی:", $nextKeyboard);
    }

    // ===== TicTacToe =====
    private function startTicTacToe(int $gameId): void
    {
        $game = Game::getGameById($gameId);
        if (!$game) return;
        $data = $game['data'];
        $board = $data['board'] ?? array_fill(0,9,null);
        $p1Id = (int)$game['player1_id']; $p2Id = (int)$game['player2_id'];
        $p1Profile = Profile::findByUserId($p1Id);
        $p2Profile = Profile::findByUserId($p2Id);
        $p1Name = $p1Profile['name'] ?? 'بازیکن1';
        $p2Name = $p2Profile['name'] ?? 'بازیکن2';

        $current = $data['current'] ?? $p1Id;
        $currentName = $current == $p1Id ? $p1Name : $p2Name;
        $currentSymbol = $current == $data['player_X'] ? '❌' : '⭕';

        $text = "⭕ *دوز - شروع!*\n\n"
              . "❌ {$p1Name} vs ⭕ {$p2Name}\n"
              . "نوبت *{$currentName}* {$currentSymbol}\n\n"
              . "یه خونه انتخاب کن:";

        // برای نمایش board باید X/O بذاریم، نه userId
        $displayBoard = [];
        foreach ($board as $cell) {
            if ($cell === null) $displayBoard[] = null;
            elseif ($cell === $p1Id) $displayBoard[] = $data['player_X'] == $p1Id ? 'X' : 'O'; // نفر اول X
            elseif ($cell === $p2Id) $displayBoard[] = $data['player_X'] == $p2Id ? 'X' : 'O';
            else $displayBoard[] = $cell; // already X/O
        }
        // اگر board اولیه null هست، باید X/O نکنیم - فقط null
        // برای شروع board همه null

        $keyboard = $this->bale->tictactoeBoardKeyboard($board, $gameId);

        $p1User = User::findById($p1Id); $p2User = User::findById($p2Id);
        if ($p1User) $this->bale->sendMessage((int)$p1User['bale_id'], $text, $keyboard);
        if ($p2User) $this->bale->sendMessage((int)$p2User['bale_id'], $text, $keyboard);
    }

    private function handleTicTacToeMove(array $user, string $callbackId, int $gameId, int $idx): void
    {
        $game = Game::getGameById($gameId);
        if (!$game || $game['status'] !== 'active') { $this->bale->answerCallbackQuery($callbackId, '❌ بازی نیست', true); return; }
        $data = $game['data'];
        $board = $data['board'] ?? array_fill(0,9,null);

        if ($idx < 0 || $idx > 8) { $this->bale->answerCallbackQuery($callbackId, '❌ خونه نامعتبر'); return; }
        if ($board[$idx] !== null) { $this->bale->answerCallbackQuery($callbackId, '❌ این خونه پره', true); return; }
        if ((int)$data['current'] !== $user['id']) { $this->bale->answerCallbackQuery($callbackId, '⏳ نوبت تو نیست', true); return; }

        $p1Id = (int)$game['player1_id']; $p2Id = (int)$game['player2_id'];
        $isXTurn = ($data['current'] == $data['player_X']);
        $symbol = $isXTurn ? 'X' : 'O';

        $board[$idx] = $symbol;
        $data['board'] = $board;
        $data['moves'] = ($data['moves'] ?? 0) + 1;

        // چک برنده
        $winnerSymbol = null;
        $lines = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
        foreach ($lines as $line) {
            [$a,$b,$c] = $line;
            if ($board[$a] !== null && $board[$a] === $board[$b] && $board[$a] === $board[$c]) {
                $winnerSymbol = $board[$a];
                break;
            }
        }

        $winnerId = null;
        $finished = false;
        $resultText = '';

        $p1Profile = Profile::findByUserId($p1Id);
        $p2Profile = Profile::findByUserId($p2Id);
        $p1Name = $p1Profile['name'] ?? 'بازیکن1';
        $p2Name = $p2Profile['name'] ?? 'بازیکن2';

        if ($winnerSymbol !== null) {
            $winnerId = $winnerSymbol === 'X' ? $data['player_X'] : $data['player_O'];
            $winnerName = $winnerId == $p1Id ? $p1Name : $p2Name;
            $resultText = "🎉 *{$winnerName} برنده شد!* {$winnerSymbol} برد\n\n";
            $finished = true;
        } elseif (!in_array(null, $board, true)) {
            $resultText = "🤝 *مساوی!* صفحه پر شد\n\n";
            $finished = true;
            $winnerId = 0;
        }

        if ($finished) {
            Game::updateGameData($gameId, $data, null, $winnerId);
            Game::finishGame($gameId, $winnerId ?: null);

            $finalBoardText = $this->formatBoard($board) . "\n\n" . $resultText;

            $keyboard = $this->bale->inlineKeyboard([
                [['text'=>'🔄 دوباره','callback_data'=>'game_invite_ttt_'.$game['conversation_id'].'_'.$game['conversation_type']],['text'=>'🎮 منوی بازی','callback_data'=>'game_menu_'.$game['conversation_id'].'_'.$game['conversation_type']]],
                [['text'=>'🚪 پایان','callback_data'=>'game_end_'.$gameId]],
            ]);

            $p1User = User::findById($p1Id); $p2User = User::findById($p2Id);
            if ($p1User) $this->bale->sendMessage((int)$p1User['bale_id'], $finalBoardText, $keyboard);
            if ($p2User) $this->bale->sendMessage((int)$p2User['bale_id'], $finalBoardText, $keyboard);

            $this->bale->answerCallbackQuery($callbackId, $finished ? '🏁 بازی تمام!' : '✅');
        } else {
            // نوبت بعدی
            $nextPlayer = $p1Id === $user['id'] ? $p2Id : $p1Id;
            $data['current'] = $nextPlayer;
            Game::updateGameData($gameId, $data, $nextPlayer);

            $this->bale->answerCallbackQuery($callbackId, "✅ {$symbol} گذاشتی");

            $nextProfile = Profile::findByUserId($nextPlayer);
            $nextName = $nextProfile['name'] ?? 'حریف';
            $nextSymbol = $nextPlayer == $data['player_X'] ? '❌' : '⭕';

            $text = "⭕ *دوز - دور {$data['moves']}*\n\n"
                  . $this->formatBoard($board) . "\n\n"
                  . "نوبت *{$nextName}* {$nextSymbol}";

            $keyboard = $this->bale->tictactoeBoardKeyboard($board, $gameId);

            $p1User = User::findById($p1Id); $p2User = User::findById($p2Id);
            if ($p1User) $this->bale->sendMessage((int)$p1User['bale_id'], $text, $keyboard);
            if ($p2User) $this->bale->sendMessage((int)$p2User['bale_id'], $text, $keyboard);
        }
    }

    private function formatBoard(array $board): string
    {
        $symbols = ['X'=>'❌','O'=>'⭕',null=>'⬜'];
        $out = "";
        for ($i=0;$i<9;$i++) {
            $cell = $board[$i] ?? null;
            $out .= $symbols[$cell] ?? '⬜';
            if (($i+1)%3===0) $out .= "\n"; else $out .= " ";
        }
        return $out;
    }

    // Helpers
    private function getCurrentConversation(array $user): ?array
    {
        if (($user['current_status'] ?? 'idle') === 'in_secret_meeting' && !empty($user['current_secret_meeting_room_id'])) {
            $room = SecretMeetingRoom::getRoomById((int)$user['current_secret_meeting_room_id']);
            if ($room && $room['status'] === 'active') {
                $partnerId = (int)$room['user1_id'] === (int)$user['id'] ? (int)$room['user2_id'] : (int)$room['user1_id'];
                return ['conversation_type'=>'secret_meeting','conversation_id'=>$room['id'],'partner_id'=>$partnerId];
            }
        }
        $stepData = User::getStepData($user);
        if (!empty($stepData['match_id']) && !empty($stepData['partner_id'])) {
            return ['conversation_type'=>'match','conversation_id'=>(int)$stepData['match_id'],'partner_id'=>(int)$stepData['partner_id']];
        }
        return null;
    }

    private function resolveActiveChatKeyboard(array $user, int $partnerId): array
    {
        $status = $user['current_status'] ?? 'idle';
        if ($status === 'in_secret_meeting' && !empty($user['current_secret_meeting_room_id'])) {
            $roomId = (int) $user['current_secret_meeting_room_id'];
            $room = SecretMeetingRoom::getRoomById($roomId);
            if ($room && $room['status'] === 'active') return $this->bale->secretMeetingChatKeyboard($partnerId, $roomId);
        }
        $stepData = User::getStepData($user);
        if (!empty($stepData['match_id'])) {
            return $this->bale->matchChatKeyboard($partnerId, $stepData['match_id']);
        }
        return $this->bale->mainMenuKeyboard();
    }

    // Old methods kept for compatibility
    private function handleViewProfile(array $user, string $callbackId, int $partnerId): void
    {
        $chatId = (int) $user['bale_id'];
        $partner = User::findById($partnerId);
        if (!$partner) { $this->bale->answerCallbackQuery($callbackId, '❌ کاربر نیست', true); return; }
        $partnerProfile = Profile::findByUserId($partnerId);
        if (!$partnerProfile) { $this->bale->answerCallbackQuery($callbackId, '❌ پروفایل نیست', true); return; }
        $this->bale->answerCallbackQuery($callbackId);
        $cardText = Profile::formatCard($partnerProfile);
        $photoFileId = $partnerProfile['photo_file_id'] ?? '';
        if (!empty($photoFileId)) { $this->bale->sendPhoto($chatId, $photoFileId, $cardText, $this->resolveActiveChatKeyboard($user, $partnerId)); }
        else $this->bale->sendMessage($chatId, $cardText, $this->resolveActiveChatKeyboard($user, $partnerId));
    }

    private function handleGiveGift(array $user, string $callbackId, int $partnerId): void
    {
        $chatId = (int) $user['bale_id'];
        $partner = User::findById($partnerId);
        if (!$partner) { $this->bale->answerCallbackQuery($callbackId, '❌ کاربر نیست', true); return; }
        $balance = Coin::getBalance($user['id']);
        if ($balance < COST_GIFT) { $this->bale->answerCallbackQuery($callbackId, "⚠️ سکه کم: $balance", true); return; }
        if (Coin::transfer($user['id'], $partnerId, COST_GIFT, 'هدیه')) {
            $this->bale->answerCallbackQuery($callbackId, "🎁 هدیه رفت!");
            $myProfile = Profile::findByUserId($user['id']);
            $this->bale->sendMessage($chatId, "🎁 {$myProfile['name']} هدیه داد!", $this->resolveActiveChatKeyboard($user, $partnerId));
            $this->bale->sendMessage((int)$partner['bale_id'], "🎁 از " . ($myProfile['name'] ?? 'کاربر') . " هدیه گرفتی!", $this->resolveActiveChatKeyboard($partner, $user['id']));
        }
    }

    private function handleEndChat(array $user, string $callbackId, int $roomId): void
    {
        $chatId = (int) $user['bale_id'];
        $room = SecretMeetingRoom::getRoomById($roomId);
        if (!$room) { $this->bale->answerCallbackQuery($callbackId, '❌ اتاق نیست', true); return; }
        $isUser1 = (int)$room['user1_id'] === (int)$user['id'];
        $isUser2 = (int)$room['user2_id'] === (int)$user['id'];
        if (!$isUser1 && !$isUser2) { $this->bale->answerCallbackQuery($callbackId, '❌ دسترسی نیست', true); return; }
        if ($room['status'] !== 'active') { User::updateUserStatus($user['id'], 'idle'); User::updateStep($user['id'], 'idle'); $this->bale->answerCallbackQuery($callbackId, 'تمام شده'); $this->bale->sendMessage($chatId, '✅ تمام شد', $this->bale->mainMenuKeyboard()); return; }
        $partnerId = $isUser1 ? (int)$room['user2_id'] : (int)$room['user1_id'];
        $partnerUser = User::findById($partnerId);
        $endStatus = $isUser1 ? 'ended_by_user1' : 'ended_by_user2';
        SecretMeetingRoom::endRoom($roomId, $endStatus);
        User::updateUserStatus($user['id'], 'idle'); User::updateStep($user['id'], 'idle');
        if ($partnerId) { User::updateUserStatus($partnerId, 'idle'); User::updateStep($partnerId, 'idle'); }
        $this->bale->answerCallbackQuery($callbackId, 'پایان');
        $this->bale->sendMessage($chatId, "✅ گفتگو تمام شد", $this->bale->mainMenuKeyboard());
        if ($partnerUser) $this->bale->sendMessage((int)$partnerUser['bale_id'], "👋 طرف مقابل رفت", $this->bale->mainMenuKeyboard());
    }

    private function handleDeleteMessages(array $user, string $callbackId, int $roomId): void
    {
        $chatId = (int) $user['bale_id'];
        $room = SecretMeetingRoom::getRoomById($roomId);
        if (!$room || $room['status'] !== 'active') { $this->bale->answerCallbackQuery($callbackId, '❌ پایان یافته', true); User::updateUserStatus($user['id'], 'idle'); User::updateStep($user['id'], 'idle'); $this->bale->sendMessage($chatId, '✅ پایان', $this->bale->mainMenuKeyboard()); return; }
        $isUser1 = (int)$room['user1_id'] === (int)$user['id'];
        $col = $isUser1 ? 'deleted_by_user1' : 'deleted_by_user2';
        Database::execute("UPDATE messages SET $col = 1 WHERE conversation_type = 'secret_meeting' AND conversation_id = ?", [$roomId]);
        $partnerId = $isUser1 ? (int)$room['user2_id'] : (int)$room['user1_id'];
        $this->bale->answerCallbackQuery($callbackId, '🗑 حذف شد');
        $this->bale->sendMessage($chatId, '🗑 تاریخچه پاک شد', $this->bale->secretMeetingChatKeyboard($partnerId, $roomId));
    }

    private function handleReportUser(array $user, string $callbackId, int $partnerId): void
    {
        User::updateStep($user['id'], 'report_reason', ['reported_id'=>$partnerId,'source'=>'secret_chat','room_id'=>$user['current_secret_meeting_room_id'] ?? null]);
        $this->bale->answerCallbackQuery($callbackId, 'دلیل گزارش بنویس');
        $this->bale->sendMessage((int)$user['bale_id'], "🚨 دلیل گزارش رو بنویس (حداقل 10 کاراکتر):\n/cancel لغو", $this->bale->removeKeyboard());
    }
}
