<?php

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function adminPageTitle(string $page): string
{
    return match ($page) {
        'dashboard'   => 'داشبورد فرماندهی',
        'users'       => 'مدیریت اعضا',
        'user_detail' => 'پرونده عضو',
        'reports'     => 'گزارشات امنیتی',
        'broadcast'   => 'پیام همگانی',
        'settings'    => 'تنظیمات امپراتوری',
        'coins'       => 'خزانه سکه',
        'secret'      => 'ملاقات مخفیانه',
        'referrals'   => 'شبکه دعوت',
        'vip'         => 'اشتراک ویژه',
        'analytics'   => 'آمار و تحلیل',
        'logs'        => 'لاگ‌ها و وقایع',
        default       => 'مافیا مچ',
    };
}

function formatDate(?string $date): string { if (!$date) return '—'; try { return date('Y/m/d H:i', strtotime($date)); } catch (Throwable $e) { return $date; } }
function formatRelativeTime(?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date); $diff = time() - $time;
    if ($diff < 60) return 'همین الان';
    if ($diff < 3600) return floor($diff/60) . ' دقیقه پیش';
    if ($diff < 86400) return floor($diff/3600) . ' ساعت پیش';
    if ($diff < 604800) return floor($diff/86400) . ' روز پیش';
    return formatDate($date);
}
function genderLabel(?string $gender): string { return match ($gender) { 'male' => '👨 مرد', 'female' => '👩 زن', default => '—', }; }
function csrfToken(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">'; }
function csrfVerify(): bool { $token = $_POST['csrf_token'] ?? ''; $sessionToken = $_SESSION['csrf_token'] ?? ''; if (empty($token) || empty($sessionToken)) return false; return hash_equals($sessionToken, $token); }
function tableExists(string $table): bool { try { return Database::tableExists($table); } catch (Throwable) { return false; } }
function safeCoinBalance(int $userId): int { if (!tableExists('coins')) return 0; try { return Coin::getBalance($userId); } catch (Throwable) { return 0; } }
function safeTotalCoins(): int { if (!tableExists('coins')) return 0; try { $row = Database::fetch('SELECT COALESCE(SUM(balance), 0) AS total FROM coins'); return (int) ($row['total'] ?? 0); } catch (Throwable) { return 0; } }
function safeTopCoinUsers(int $limit = 20): array { if (!tableExists('coins')) return []; try { return Coin::getTopUsers($limit); } catch (Throwable) { return []; } }
function isUserOnline(?string $lastSeen, int $minutes = 10): bool { if (!$lastSeen) return false; return (time() - strtotime($lastSeen)) < ($minutes * 60); }
