<?php
/**
 * Admin Password Setup
 * Run once to set admin password
 * Access: https://yourdomain.com/bale-dating-bot/admin/setup_password.php
 * DELETE THIS FILE AFTER USE!
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $message = '❌ رمز عبور باید حداقل ۸ کاراکتر باشد.';
    } elseif ($password !== $confirm) {
        $message = '❌ رمزهای عبور مطابقت ندارند.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::execute(
            'INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?',
            [$username, $hash, $hash]
        );
        $message = '✅ رمز عبور با موفقیت تنظیم شد. این فایل را حذف کنید!';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>تنظیم رمز ادمین</title>
<style>body{font-family:Tahoma;max-width:400px;margin:80px auto;padding:20px}input{width:100%;padding:8px;margin:8px 0;border:1px solid #ddd;border-radius:4px}button{width:100%;padding:10px;background:#e94560;color:#fff;border:none;border-radius:4px;cursor:pointer}.msg{padding:10px;border-radius:4px;margin-bottom:15px;background:#d4edda;color:#155724}</style>
</head>
<body>
<h2>🔐 تنظیم رمز ادمین</h2>
<?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>
<form method="POST">
    <input type="text" name="username" value="admin" placeholder="نام کاربری">
    <input type="password" name="password" placeholder="رمز عبور (حداقل ۸ کاراکتر)">
    <input type="password" name="confirm" placeholder="تکرار رمز عبور">
    <button type="submit">تنظیم رمز عبور</button>
</form>
<p style="color:red;margin-top:15px;font-size:13px">⚠️ بعد از تنظیم، این فایل را حذف کنید!</p>
</body>
</html>
