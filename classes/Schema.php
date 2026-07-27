<?php

/**
 * Schema - سیستم مهاجرت خودکار بهبود یافته + بازی
 */
class Schema
{
    private static ?bool $secretMeetingReady = null;
    private static ?bool $messagesReady = null;
    private static ?bool $allReady = null;

    public static function columnExists(string $table, string $column): bool
    {
        try {
            $row = Database::fetch("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1", [$table, $column]);
            return (bool) $row;
        } catch (Throwable $e) { return false; }
    }

    public static function tableExists(string $table): bool { return Database::tableExists($table); }

    public static function ensureColumn(string $table, string $column, string $definition): bool
    {
        if (self::columnExists($table, $column)) return true;
        try { Database::execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}"); Logger::info("Schema: added column {$table}.{$column}"); return true; }
        catch (Throwable $e) { Logger::error("Schema: failed {$table}.{$column}: " . $e->getMessage()); return false; }
    }

    public static function ensureIndex(string $table, string $indexName, string $columns): bool
    {
        try {
            $exists = Database::fetch("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1", [$table, $indexName]);
            if ($exists) return true;
            Database::execute("CREATE INDEX `{$indexName}` ON `{$table}` ({$columns})");
            return true;
        } catch (Throwable $e) { return false; }
    }

    public static function ensureSecretMeetingSchema(): bool
    {
        if (self::$secretMeetingReady !== null) return self::$secretMeetingReady;
        $ok = true;
        if (!self::tableExists('secret_meeting_rooms')) {
            try {
                Database::execute("CREATE TABLE `secret_meeting_rooms` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user1_id` INT UNSIGNED NOT NULL, `user2_id` INT UNSIGNED NOT NULL, `status` ENUM('active','ended_by_user1','ended_by_user2','ended_mutually','aborted','expired') DEFAULT 'active', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `ended_at` TIMESTAMP NULL DEFAULT NULL, `search_type` ENUM('random','gender_male','gender_female','province') NULL DEFAULT NULL, `province_filter` VARCHAR(255) NULL DEFAULT NULL, `gender_filter` ENUM('male','female') NULL DEFAULT NULL, `last_message_at` TIMESTAMP NULL DEFAULT NULL, INDEX `idx_status` (`status`), INDEX `idx_users` (`user1_id`, `user2_id`), INDEX `idx_created` (`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Throwable $e) { $ok = false; }
        }
        if (!self::tableExists('secret_meeting_queue')) {
            try {
                Database::execute("CREATE TABLE `secret_meeting_queue` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL UNIQUE, `search_preference` ENUM('random','gender_male','gender_female','province') NOT NULL, `gender_preference` ENUM('male','female') NULL DEFAULT NULL, `province_preference` VARCHAR(255) NULL DEFAULT NULL, `vip_priority` TINYINT(1) DEFAULT 0, `queued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `last_ping_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE, INDEX `idx_search_preference` (`search_preference`), INDEX `idx_vip_priority` (`vip_priority`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Throwable $e) { $ok = false; }
        }
        self::ensureColumn('users', 'current_status', "ENUM('idle','in_queue','in_secret_meeting') DEFAULT 'idle'");
        self::ensureColumn('users', 'current_secret_meeting_room_id', 'INT UNSIGNED NULL DEFAULT NULL');
        self::ensureColumn('users', 'last_seen_at', 'TIMESTAMP NULL DEFAULT NULL');
        self::ensureColumn('users', 'referred_by', 'INT UNSIGNED NULL DEFAULT NULL');
        self::ensureColumn('users', 'daily_streak', 'INT DEFAULT 0');
        self::ensureColumn('users', 'last_daily_bonus', 'DATE NULL DEFAULT NULL');
        self::ensureColumn('users', 'is_verified', 'TINYINT(1) DEFAULT 0');
        self::ensureColumn('users', 'language', 'VARCHAR(10) DEFAULT "fa"');
        self::ensureIndex('users', 'idx_last_seen', '`last_seen_at`');
        self::ensureIndex('users', 'idx_current_status', '`current_status`');
        self::$secretMeetingReady = $ok;
        return $ok;
    }

    public static function ensureMessagesSchema(): bool
    {
        if (self::$messagesReady !== null) return self::$messagesReady;
        if (!self::tableExists('messages')) {
            try {
                Database::execute("CREATE TABLE `messages` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `conversation_type` ENUM('match','secret_meeting') NOT NULL DEFAULT 'match', `conversation_id` INT UNSIGNED NOT NULL, `sender_id` INT UNSIGNED NOT NULL, `content` TEXT NOT NULL, `type` ENUM('text','photo','gift') DEFAULT 'text', `deleted_by_user1` TINYINT(1) DEFAULT 0, `deleted_by_user2` TINYINT(1) DEFAULT 0, `is_read` TINYINT(1) DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX `idx_conversation` (`conversation_type`, `conversation_id`), INDEX `idx_sender` (`sender_id`), INDEX `idx_created` (`created_at`), FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                self::$messagesReady = true; return true;
            } catch (Throwable $e) { self::$messagesReady = false; return false; }
        }
        self::ensureColumn('messages', 'conversation_type', "ENUM('match','secret_meeting') DEFAULT 'match'");
        self::ensureColumn('messages', 'conversation_id', 'INT UNSIGNED NULL DEFAULT NULL');
        self::ensureColumn('messages', 'deleted_by_user1', 'TINYINT(1) DEFAULT 0');
        self::ensureColumn('messages', 'deleted_by_user2', 'TINYINT(1) DEFAULT 0');
        self::ensureColumn('messages', 'is_read', 'TINYINT(1) DEFAULT 0');
        if (self::columnExists('messages', 'match_id')) {
            try {
                Database::execute("UPDATE `messages` SET `conversation_type` = 'match', `conversation_id` = `match_id` WHERE `conversation_id` IS NULL");
                $fks = Database::fetchAll("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'match_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
                foreach ($fks as $fk) { try { Database::execute("ALTER TABLE `messages` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`"); } catch (Throwable $e) {} }
                Database::execute("ALTER TABLE `messages` DROP COLUMN `match_id`");
            } catch (Throwable $e) {}
        }
        self::ensureIndex('messages', 'idx_conversation', '`conversation_type`, `conversation_id`');
        self::$messagesReady = true;
        return true;
    }

    public static function ensureCoinsSchema(): void
    {
        if (!self::tableExists('coins')) {
            try { Database::execute("CREATE TABLE `coins` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL UNIQUE, `balance` INT NOT NULL DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
        }
        if (!self::tableExists('coin_transactions')) {
            try { Database::execute("CREATE TABLE `coin_transactions` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL, `amount` INT NOT NULL, `type` ENUM('add','deduct','gift_sent','gift_received','set') NOT NULL DEFAULT 'add', `description` VARCHAR(500) NULL, `admin_id` INT UNSIGNED NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX `idx_user` (`user_id`), INDEX `idx_created` (`created_at`), FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
        }
    }

    public static function ensureExtraTables(): void
    {
        if (!self::tableExists('referrals')) {
            try { Database::execute("CREATE TABLE `referrals` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `referrer_id` INT UNSIGNED NOT NULL, `referred_id` INT UNSIGNED NOT NULL UNIQUE, `bonus` INT NOT NULL DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE, FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE, INDEX `idx_referrer` (`referrer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
        }
        if (!self::tableExists('rate_limits')) {
            try { Database::execute("CREATE TABLE `rate_limits` ( `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `identifier` VARCHAR(100) NOT NULL, `action` VARCHAR(50) NOT NULL, `count` INT UNSIGNED DEFAULT 1, `first_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY `idx_identifier_action` (`identifier`, `action`), INDEX `idx_last_attempt` (`last_attempt`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
        }
        if (self::tableExists('likes')) self::ensureColumn('likes', 'type', "ENUM('normal','super') DEFAULT 'normal'");
        if (self::tableExists('profiles')) {
            self::ensureColumn('profiles', 'total_views', 'INT UNSIGNED DEFAULT 0');
            self::ensureColumn('profiles', 'total_likes', 'INT UNSIGNED DEFAULT 0');
            self::ensureColumn('profiles', 'is_verified', 'TINYINT(1) DEFAULT 0');
            self::ensureColumn('profiles', 'boost_until', 'TIMESTAMP NULL DEFAULT NULL');
        }
        if (self::tableExists('matches')) self::ensureColumn('matches', 'last_message_at', 'TIMESTAMP NULL DEFAULT NULL');
        if (self::tableExists('reports')) self::ensureColumn('reports', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
    }

    public static function ensureGameSchema(): void
    {
        if (self::tableExists('games')) return;
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
            Logger::info('Schema: created games table');
        } catch (Throwable $e) {
            Logger::error('Schema games failed: ' . $e->getMessage());
        }
    }

    public static function ensureAll(): void
    {
        if (self::$allReady) return;
        try { self::ensureSecretMeetingSchema(); } catch (Throwable $e) { Logger::error('secretMeeting: ' . $e->getMessage()); }
        try { self::ensureMessagesSchema(); } catch (Throwable $e) { Logger::error('messages: ' . $e->getMessage()); }
        try { self::ensureCoinsSchema(); } catch (Throwable $e) { Logger::error('coins: ' . $e->getMessage()); }
        try { self::ensureExtraTables(); } catch (Throwable $e) { Logger::error('extra: ' . $e->getMessage()); }
        try { self::ensureGameSchema(); } catch (Throwable $e) { Logger::error('games: ' . $e->getMessage()); }
        self::$allReady = true;
    }
}
