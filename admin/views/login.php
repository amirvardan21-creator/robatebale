<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود | مافیا مچ - خانواده</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">
            <span class="skull">♠️</span>
            <h1>مافیا <span style="color:var(--red)">مچ</span></h1>
            <p>اتاق فرماندهی خانواده • BLACK & RED EDITION</p>
            <div class="brand-tag">MAFIA FAMILY • قدرت در سایه</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">🩸 <?= h($error) ?> - ورود ممنوع، فقط خانواده!</div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=login">
            <?= csrfField() ?>
            <div class="form-group">
                <label>♠️ نام مستعار - نام کاربری</label>
                <input type="text" name="username" placeholder="پدرخوانده..." required autofocus>
            </div>
            <div class="form-group">
                <label>🔑 رمز مخفی - کلمه عبور</label>
                <input type="password" name="password" placeholder="••••••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="padding:14px;font-size:15px;letter-spacing:1px">🎩 ورود به عمارت مافیا</button>
        </form>

        <div style="margin-top:20px;padding:16px;background:rgba(225,6,0,0.06);border:1px solid var(--border-red);border-radius:10px;text-align:center">
            <p style="font-size:12px;color:var(--text2)">⚠️ <strong style="color:var(--red)">هشدار امنیتی:</strong><br>این پنل فقط برای اعضای شورای عالی خانواده است.<br>هر ورود غیرمجاز ثبت و پیگیری می‌شود.</p>
        </div>

        <p class="login-footer">🔒 محافظت شده با خون • MAFIA MATCH v2.0 • <?= date('Y') ?></p>
    </div>
</div>
</body>
</html>
