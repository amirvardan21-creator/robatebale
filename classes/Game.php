<?php

/**
 * Game - سیستم بازی برای چت‌های دو نفره
 * بازی‌ها: حقیقت یا جرئت، سنگ کاغذ قیچی، دوز
 */

class Game
{
    public const TYPE_TRUTH_DARE = 'truth_dare';
    public const TYPE_RPS = 'rps';
    public const TYPE_TICTACTOE = 'tictactoe';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_DECLINED = 'declined';

    // لیست حقیقت‌ها - فارسی مناسب
    private const TRUTHS = [
        "آخرین بار کی از ته دل خندیدی؟",
        "بزرگ‌ترین ترست چیه؟",
        "اگر می‌تونستی یه روز جای هر کسی باشی، کی رو انتخاب می‌کردی؟",
        "خجالت‌آورترین خاطره‌ت چیه؟",
        "تاکنون عاشق شدی؟",
        "بزرگ‌ترین دروغی که گفتی چی بود؟",
        "اگر 1 میلیارد پول داشتی چیکار می‌کردی؟",
        "چه چیزی تو رو واقعاً خوشحال می‌کنه؟",
        "تا حالا از کسی بدت اومده ولی به روش نیاوردی؟",
        "بهترین نصیحتی که گرفتی چی بود؟",
        "اگر می‌تونستی یه قدرت ماورایی داشته باشی چی انتخاب می‌کردی؟",
        "چه چیزی تو دیگران بیشتر از همه اعصابت رو خورد می‌کنه؟",
        "آخرین باری که گریه کردی کی بود؟",
        "بزرگ‌ترین آرزوت چیه؟",
        "اگر می‌تونستی به گذشته برگردی چی رو تغییر می‌دادی؟",
        "چه کسی تو زندگیت بیشتر از همه الهام‌بخش بوده؟",
        "تا حالا کسی رو از ته دل بخشیدی؟",
        "چه چیزی رو پنهان می‌کنی که دوست داری بگی؟",
        "بهترین روز زندگیت کی بود؟",
        "از چه چیزی بیشتر از همه پشیمونی؟"
    ];

    private const DARES = [
        "یه جوک بگو که حداقل منو بخندونه 😆",
        "تا 3 تا از بهترین ایموجی‌هات رو بفرست با توضیح",
        "یه شعر کوتاه بنویس درباره‌ی همین چت",
        "عکس یه چیز خنده‌دار از گالریت بفرست (بدون چهره)",
        "با صدای بلند بگو: من باحال‌ترین آدم روی زمینم! (و بنویس که گفتی)",
        "یه داستان کوتاه تخیلی 2 خطی بنویس",
        "اسم منو با یه لقب خنده‌دار صدا کن تا آخر بازی",
        "بگو 3 تا از نقاط قوتت چیه",
        "یه چالش: 1 دقیقه فقط با ایموجی حرف بزن",
        "یه حقیقت خجالت‌آور درباره خودت بگو که هنوز نگفتی",
        "وانمود کن که خبرنگار هستی و از من مصاحبه بگیر",
        "یه آهنگ که الان تو ذهنته رو بنویس",
        "بگو اگه الان جلوم بودی چی می‌گفتی؟",
        "یه معمای باحال بپرس",
        "خودت رو در 3 کلمه توصیف کن",
        "یه آرزوی بامزه بکن که محقق شه",
        "تا آخر بازی هر جمله‌ات رو با یه ایموجی قلب تموم کن ❤️",
        "یه تعریف خفن از من بکن",
        "بگو اگه یه روز کامل با هم بودیم کجا می‌رفتیم؟",
        "یه راز کوچیک بگو که کسی نمی‌دونه"
    ];

    public static function ensureTable(): void
    {
        if (Database::tableExists('games')) return;
        try {
            Database::execute("
                CREATE TABLE IF NOT EXISTS `games` (
                  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  `conversation_type` ENUM('match','secret_meeting') NOT NULL DEFAULT 'match',
                  `conversation_id` INT UNSIGNED NOT NULL,
                  `game_type` ENUM('truth_dare','rps','tictactoe') NOT NULL,
                  `player1_id` INT UNSIGNED NOT NULL,
                  `player2_id` INT UNSIGNED NOT NULL,
                  `status` ENUM('pending','active','finished','declined') DEFAULT 'pending',
                  `current_turn` INT UNSIGNED NULL DEFAULT NULL,
                  `winner_id` INT UNSIGNED NULL DEFAULT NULL,
                  `data` JSON DEFAULT NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  INDEX `idx_conversation` (`conversation_type`, `conversation_id`),
                  INDEX `idx_players` (`player1_id`, `player2_id`),
                  INDEX `idx_status` (`status`),
                  FOREIGN KEY (`player1_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                  FOREIGN KEY (`player2_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            Logger::info('Game table created');
        } catch (Throwable $e) {
            Logger::error('Game table creation failed: ' . $e->getMessage());
        }
    }

    public static function createGame(string $conversationType, int $conversationId, int $player1Id, int $player2Id, string $gameType): int
    {
        self::ensureTable();

        // اگر بازی فعال دیگری در این مکالمه هست، آن را ببند
        self::closeActiveGames($conversationType, $conversationId);

        $initialData = match($gameType) {
            self::TYPE_TRUTH_DARE => json_encode([
                'turn' => $player1Id,
                'score' => [$player1Id => 0, $player2Id => 0],
                'round' => 1,
                'last_truth' => null,
                'last_dare' => null,
            ], JSON_UNESCAPED_UNICODE),
            self::TYPE_RPS => json_encode([
                'choices' => [],
                'round' => 1,
                'score' => [$player1Id => 0, $player2Id => 0],
                'history' => [],
            ], JSON_UNESCAPED_UNICODE),
            self::TYPE_TICTACTOE => json_encode([
                'board' => array_fill(0, 9, null),
                'player_X' => $player1Id,
                'player_O' => $player2Id,
                'current' => $player1Id,
                'moves' => 0,
            ], JSON_UNESCAPED_UNICODE),
            default => json_encode([])
        };

        return Database::insert(
            'INSERT INTO games (conversation_type, conversation_id, game_type, player1_id, player2_id, status, current_turn, data) VALUES (?, ?, ?, ?, ?, "pending", ?, ?)',
            [$conversationType, $conversationId, $gameType, $player1Id, $player2Id, $player1Id, $initialData]
        );
    }

    public static function getGameById(int $gameId): ?array
    {
        self::ensureTable();
        $game = Database::fetch('SELECT * FROM games WHERE id = ?', [$gameId]);
        if ($game && isset($game['data']) && is_string($game['data'])) {
            $game['data'] = json_decode($game['data'], true) ?: [];
        }
        return $game;
    }

    public static function getActiveGame(string $conversationType, int $conversationId): ?array
    {
        self::ensureTable();
        $game = Database::fetch(
            'SELECT * FROM games WHERE conversation_type = ? AND conversation_id = ? AND status IN ("pending","active") ORDER BY created_at DESC LIMIT 1',
            [$conversationType, $conversationId]
        );
        if ($game && isset($game['data']) && is_string($game['data'])) {
            $game['data'] = json_decode($game['data'], true) ?: [];
        }
        return $game;
    }

    public static function acceptGame(int $gameId, int $playerId): bool
    {
        $game = self::getGameById($gameId);
        if (!$game || $game['status'] !== self::STATUS_PENDING) return false;
        if ((int)$game['player2_id'] !== $playerId && (int)$game['player1_id'] !== $playerId) return false;

        Database::execute('UPDATE games SET status="active", updated_at=NOW() WHERE id=?', [$gameId]);
        return true;
    }

    public static function declineGame(int $gameId): void
    {
        Database::execute('UPDATE games SET status="declined", updated_at=NOW() WHERE id=?', [$gameId]);
    }

    public static function finishGame(int $gameId, ?int $winnerId = null): void
    {
        Database::execute('UPDATE games SET status="finished", winner_id=?, updated_at=NOW() WHERE id=?', [$winnerId, $gameId]);
    }

    public static function closeActiveGames(string $conversationType, int $conversationId): void
    {
        self::ensureTable();
        Database::execute(
            'UPDATE games SET status="finished", updated_at=NOW() WHERE conversation_type=? AND conversation_id=? AND status IN ("pending","active")',
            [$conversationType, $conversationId]
        );
    }

    public static function updateGameData(int $gameId, array $data, ?int $currentTurn = null, ?int $winnerId = null): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($currentTurn !== null && $winnerId !== null) {
            Database::execute('UPDATE games SET data=?, current_turn=?, winner_id=?, updated_at=NOW() WHERE id=?', [$json, $currentTurn, $winnerId, $gameId]);
        } elseif ($currentTurn !== null) {
            Database::execute('UPDATE games SET data=?, current_turn=?, updated_at=NOW() WHERE id=?', [$json, $currentTurn, $gameId]);
        } else {
            Database::execute('UPDATE games SET data=?, updated_at=NOW() WHERE id=?', [$json, $gameId]);
        }
    }

    // ===== Truth or Dare Logic =====
    public static function getRandomTruth(): string
    {
        return self::TRUTHS[array_rand(self::TRUTHS)];
    }

    public static function getRandomDare(): string
    {
        return self::DARES[array_rand(self::DARES)];
    }

    // ===== RPS Logic =====
    public static function checkRPSWinner(string $choice1, string $choice2): int
    {
        // 0 = tie, 1 = player1 wins, 2 = player2 wins
        if ($choice1 === $choice2) return 0;
        $wins = [
            'rock' => 'scissors',
            'scissors' => 'paper',
            'paper' => 'rock',
        ];
        return (isset($wins[$choice1]) && $wins[$choice1] === $choice2) ? 1 : 2;
    }

    public static function rpsEmoji(string $choice): string
    {
        return match($choice) {
            'rock' => '🪨 سنگ',
            'paper' => '📄 کاغذ',
            'scissors' => '✂️ قیچی',
            default => '❓'
        };
    }

    // ===== TicTacToe Logic =====
    public static function checkTicTacToeWinner(array $board): ?int
    {
        $lines = [
            [0,1,2], [3,4,5], [6,7,8], // rows
            [0,3,6], [1,4,7], [2,5,8], // cols
            [0,4,8], [2,4,6] // diag
        ];
        foreach ($lines as $line) {
            [$a,$b,$c] = $line;
            if ($board[$a] !== null && $board[$a] === $board[$b] && $board[$a] === $board[$c]) {
                return $board[$a]; // returns player id
            }
        }
        // draw?
        if (!in_array(null, $board, true)) return 0; // draw
        return null; // no winner yet
    }

    public static function getGameTypeName(string $type): string
    {
        return match($type) {
            self::TYPE_TRUTH_DARE => 'حقیقت یا جرئت',
            self::TYPE_RPS => 'سنگ کاغذ قیچی',
            self::TYPE_TICTACTOE => 'دوز',
            default => 'بازی'
        };
    }

    public static function getGameTypeEmoji(string $type): string
    {
        return match($type) {
            self::TYPE_TRUTH_DARE => '🤔',
            self::TYPE_RPS => '✂️',
            self::TYPE_TICTACTOE => '⭕',
            default => '🎮'
        };
    }
}
