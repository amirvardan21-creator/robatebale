<div class="card">
    <div class="card-title"><span class="icon">⚙</span> تنظیمات ربات</div>

    <?php foreach ($settings as $setting): ?>
    <form method="POST" class="setting-row">
        <?= csrfField() ?>
        <input type="hidden" name="page" value="settings">
        <div class="form-group" style="flex:1;margin:0">
            <label><?= h($settingLabels[$setting['key']] ?? $setting['key']) ?></label>
            <?php if ($setting['key'] === 'bot_active'): ?>
                <select name="value">
                    <option value="1" <?= $setting['value'] == '1' ? 'selected' : '' ?>>✅ فعال</option>
                    <option value="0" <?= $setting['value'] == '0' ? 'selected' : '' ?>>🔧 حالت تعمیر</option>
                </select>
            <?php elseif (in_array($setting['key'], ['maintenance_message', 'welcome_message'])): ?>
                <textarea name="value" rows="2"><?= h($setting['value']) ?></textarea>
            <?php else: ?>
                <input type="text" name="value" value="<?= h($setting['value']) ?>">
            <?php endif; ?>
        </div>
        <input type="hidden" name="key" value="<?= h($setting['key']) ?>">
        <button type="submit" name="action" value="update_setting" class="btn btn-primary btn-sm">💾</button>
    </form>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-title"><span class="icon">🔧</span> وضعیت سیستم</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="lbl">آدرس ربات</div>
            <div class="val" style="font-size:12px"><?= h(BASE_URL) ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">یوزرنیم ربات</div>
            <div class="val">@<?= h(BOT_USERNAME) ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">شناسه ادمین بله</div>
            <div class="val"><?= h(ADMIN_BALE_ID) ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">جدول سکه</div>
            <div class="val"><?= tableExists('coins') ? '✅ موجود' : '❌ نصب نشده' ?></div>
        </div>
    </div>
</div>
