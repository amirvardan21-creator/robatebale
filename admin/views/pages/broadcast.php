<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">📢</span> پیام همگانی</div>
        <p class="card-desc">پیام به تمام اعضای فعال و غیرمسدود ارسال می‌شود.</p>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="broadcast">
            <div class="form-group">
                <label>متن پیام</label>
                <textarea name="message" placeholder="پیام همگانی مافیا مچ..." required></textarea>
            </div>
            <div class="broadcast-info">
                <span>📨 مقصد: <strong><?= number_format($broadcastCount) ?></strong> عضو</span>
            </div>
            <button type="submit" name="action" value="broadcast" class="btn btn-primary"
                    onclick="return confirm('پیام برای <?= (int)$broadcastCount ?> نفر ارسال شود؟')">
                📢 ارسال همگانی
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">💡</span> راهنما</div>
        <ul class="tips-list">
            <li>پیام‌ها از طریق ربات بله ارسال می‌شوند</li>
            <li>بین هر پیام ۶۰ms تأخیر وجود دارد تا API محدود نشود</li>
            <li>اعضای مسدود پیام دریافت نمی‌کنند</li>
            <li>از HTML در پیام استفاده نکنید (parse_mode فعال نیست)</li>
        </ul>

        <hr class="divider">

        <div class="card-title" style="margin-top:0"><span class="icon">📝</span> نمونه پیام</div>
        <div class="sample-message">
            🎩 سلام اعضای مافیا مچ!<br><br>
            از این پس امکانات جدید فعال شده.<br>
            برای مشاهده /start را بزنید.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">📨</span> ارسال پیام به یک عضو</div>
    <form method="GET" class="search-bar" style="margin-bottom:16px">
        <input type="hidden" name="page" value="users">
        <input type="text" name="q" placeholder="جستجوی عضو برای ارسال پیام...">
        <button type="submit" class="btn btn-ghost btn-sm">جستجو</button>
    </form>
    <p class="card-desc">برای ارسال پیام تکی، عضو را پیدا کنید و از صفحه «مدیریت» پیام مستقیم بفرستید.</p>
</div>
