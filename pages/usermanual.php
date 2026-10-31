<?php
// pages/usermanual.php
$pageTitle = "راهنمای کاربر و کامندها";
?>


<div class="manual-page">
    <div class="manual-hero">
        <i class="fas fa-book-open"></i>
        <h1>راهنمای کاربر و کامندهای سرور CS2</h1>
        <p>آشنایی با مودها، کامندهای کاربردی و تنظیمات سرورهای FragCity</p>
    </div>

    <div class="manual-container">

        <!-- ========== مودهای موجود ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-gamepad"></i>
                <span>مودهای موجود در سرور</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <p>سرور FragCity از <strong>۲۴ مود متنوع</strong> پشتیبانی می‌کند. با کامند <code>!modes</code> می‌توانید مود را تغییر دهید:</p>
                <div class="mode-grid">
                    <span>🏆 Competitive</span><span>👥 Wingman</span><span>💀 Deathmatch</span>
                    <span>🔫 GunGame</span><span>🎯 Prefire Practice</span><span>🔄 Retakes</span>
                    <span>⚡ Executes</span><span>🚩 Capture The Flag</span><span>🎯 One In The Chamber</span>
                    <span>🥊 1v1 Arenas</span><span>🔪 ScoutzKnivez</span><span>🧗 KZ Climb</span>
                    <span>🐇 Bunny Hop</span><span>🏄 Surf</span><span>🎮 Mini Games</span>
                    <span>⚰️ Deathrun</span><span>📐 Course</span><span>🙈 Hide N Seek</span>
                    <span>🎯 Battle Royale</span><span>⚽ Soccer</span><span>🎾 Battle Ball</span>
                    <span>🔭 AWP</span><span>🎯 Aim</span><span>🎲 Random Rounds</span>
                </div>
                <p class="info-note">💡 همچنین می‌توانید با کامند <code>!gamemode</code> یا کامندهای اختصاصی مثل <code>!dm</code>، <code>!gg</code>، <code>!retake</code> مستقیماً مود مورد نظر را فعال کنید.</p>
            </div>
        </div>

        <!-- ========== راهنمای کامل Mix ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-users"></i>
                <span>نحوه اجرای بازی Mix (رقابتی)</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <h3>مراحل اجرای Mix</h3>
                <ol>
                    <li>وارد سرور Mix شوید</li>
                    <li>در چت تایپ کنید: <code>!match</code></li>
                    <li>سرور وارد فاز Warmup می‌شود</li>
                    <li>برای فعال/غیرفعال کردن راند چاقو از <code>!kniferound</code> استفاده کنید</li>
                    <li>همه بازیکنان باید <code>.ready</code> بزنند</li>
                    <li>پس از Ready شدن، بازی خودکار شروع می‌شود</li>
                    <li>برای شروع سریع‌تر: <code>!forcestart</code></li>
                </ol>

                <h3>تغییر مود سرور</h3>
                <div class="command-block"><code>!mix</code> / <code>css_mix</code> – مود میکس و شبیه‌ساز نایف راند</div>
                <div class="command-block"><code>!casual</code> / <code>css_casual</code> – حالت کژوال غیررقابتی</div>
                <div class="command-block"><code>!retakes</code> / <code>css_retakes</code> – حالت ریتیک</div>
                <div class="command-block"><code>!practice</code> / <code>css_practice</code> – حالت تمرین تیمی</div>
                <div class="command-block"><code>!warmup</code> / <code>css_warmup</code> – حالت وارم‌آپ همیشگی</div>
                <div class="command-block"><code>!wingman</code> / <code>css_wingman</code> – حالت وینگمن</div>

                <h3>کامندهای اختصاصی مودها</h3>
                <div class="command-block"><code>!match</code> – فعال‌سازی مود Mix</div>
                <div class="command-block"><code>!prac</code> – فعال‌سازی مود Practice (تمرین گرنید، لاین‌آپ)</div>
                <div class="command-block"><code>!retakes</code> / <code>!retake</code> – فعال‌سازی مود Retakes</div>
                <div class="command-block"><code>!casual</code> – مود Casual</div>
                <div class="command-block"><code>!warmup</code> – مود Warmup</div>

                <h3>کامندهای ادمین مخصوص Mix</h3>
                <div class="command-grid">
                    <div class="command-block"><code>.start</code> – شروع اجباری</div>
                    <div class="command-block"><code>.restart</code> / <code>.endmatch</code> – ریستارت یا پایان مسابقه</div>
                    <div class="command-block"><code>.forcepause</code> / <code>.fp</code> – توقف اجباری</div>
                    <div class="command-block"><code>.forceunpause</code> / <code>.fup</code> – ادامه اجباری</div>
                    <div class="command-block"><code>.restore &lt;round&gt;</code> – بازگردانی راند</div>
                    <div class="command-block"><code>.skipveto</code> / <code>.sv</code> – رد فاز Veto</div>
                    <div class="command-block"><code>.roundknife</code> / <code>.rk</code> – تغییر حالت راند چاقو</div>
                    <div class="command-block"><code>.settings</code> – نمایش تنظیمات فعلی</div>
                    <div class="command-block"><code>!map &lt;mapname&gt;</code> – تغییر نقشه</div>
                    <div class="command-block"><code>!asay &lt;message&gt;</code> – پیام ادمین در چت</div>
                    <div class="command-block"><code>.team1 &lt;name&gt;</code> / <code>.team2 &lt;name&gt;</code> – نام تیم‌ها</div>
                    <div class="command-block"><code>.prac</code> / <code>.tactics</code> – شروع مود Practice</div>
                    <div class="command-block"><code>.exitprac</code> – خروج از Practice</div>
                    <div class="command-block"><code>!kniferound</code> – فعال/غیرفعال کردن راند چاقو</div>
                    <div class="command-block"><code>!forcestart</code> – اجباری کردن شروع سریع راند چاقو</div>
                    <div class="command-block"><code>!end</code> – توقف مود Mix</div>
                    <div class="command-block"><code>!endwarmup</code> – پایان Warmup</div>
                    <div class="command-block"><code>!pausewarmup</code> – فریز زمان Warmup</div>
                    <div class="command-block"><code>!startwarmup</code> – شروع مجدد Warmup</div>
                </div>
            </div>
        </div>

        <!-- ========== کامندهای بازیکنان ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-user-friends"></i>
                <span>کامندهای عمومی بازیکنان</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <div class="command-grid">
                    <div class="command-block"><code>.ready</code> / <code>.r</code> – آمادگی برای شروع</div>
                    <div class="command-block"><code>.unready</code> / <code>.ur</code> – لغو آمادگی</div>
                    <div class="command-block"><code>.forceready</code> – اجباری کردن آمادگی تیم (ادمین)</div>
                    <div class="command-block"><code>.pause</code> – توقف بازی در فریزتایم</div>
                    <div class="command-block"><code>.tech</code> – توقف فنی</div>
                    <div class="command-block"><code>.unpause</code> – درخواست ادامه بازی</div>
                    <div class="command-block"><code>.stay</code> – ماندن در همان سمت بعد از راند چاقو</div>
                    <div class="command-block"><code>.switch</code> / <code>.swap</code> – تعویض سمت</div>
                    <div class="command-block"><code>.stop</code> – بازگردانی بکاپ راند (نیاز به تأیید هر دو تیم)</div>
                    <div class="command-block"><code>.tac</code> – تایم‌اوت تاکتیکال</div>
                    <div class="command-block"><code>.coach &lt;side&gt;</code> – شروع مربی‌گری (مثال: <code>.coach t</code>)</div>
                    <div class="command-block"><code>.uncoach</code> – ترک مربی‌گری</div>
                </div>
            </div>
        </div>

        <!-- ========== کامندهای ادمین ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-shield-alt"></i>
                <span>کامندهای ادمین</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <div class="command-grid-2">
                    <div class="command-block"><code>!rcon &lt;command&gt;</code> – اجرای دستورات کنسول</div>
                    <div class="command-block"><code>!addcash &lt;name&gt; &lt;amount&gt;</code> – اضافه کردن پول</div>
                    <div class="command-block"><code>!ban &lt;name&gt; &lt;minutes&gt; &lt;reason&gt;</code> – بن بازیکن</div>
                    <div class="command-block"><code>!gag &lt;name&gt; &lt;minutes&gt; &lt;reason&gt;</code> – گگ (عدم چت)</div>
                    <div class="command-block"><code>!mute &lt;name&gt; &lt;minutes&gt; &lt;reason&gt;</code> – میوت میکروفون</div>
                    <div class="command-block"><code>!kickid &lt;userid&gt;</code> – اخراج بازیکن</div>
                    <div class="command-block"><code>!map &lt;mapname&gt;</code> – تغییر مپ</div>
                    <div class="command-block"><code>!modes</code> – منوی تغییر مود</div>
                    <div class="command-block"><code>!maps</code> – منوی مپ‌های مود فعلی</div>
                    <div class="command-block"><code>!settings</code> – منوی تنظیمات (BunnyHop, Friendly Fire, Only Headshot, All Talk)</div>
                    <div class="command-block"><code>!lock</code> – قفل تغییر تیم</div>
                    <div class="command-block"><code>!unban &lt;steamid64&gt;</code> – رفع بن آفلاین</div>
                    <div class="command-block"><code>!respawn &lt;@ct/@t&gt;</code> – احیای بازیکن</div>
                    <div class="command-block"><code>!slap &lt;name&gt;</code> – ضربه زدن</div>
                    <div class="command-block"><code>!slay &lt;name&gt;</code> – خودکشی اجباری</div>
                    <div class="command-block"><code>!rr</code> – ریست راند</div>
                    <div class="command-block"><code>!rs</code> – ریست امتیاز</div>
                    <div class="command-block"><code>!ff1</code> / <code>!ff0</code> – فعال/غیرفعال Friendly Fire</div>
                    <div class="command-block"><code>!hs1</code> / <code>!hs0</code> – فعال/غیرفعال Only Headshot</div>
                    <div class="command-block"><code>!bh1</code> / <code>!bh0</code> – Bunny Hop</div>
                    <div class="command-block"><code>!t1</code> / <code>!t0</code> – All Talk</div>
                    <div class="command-block"><code>!move &lt;name&gt; &lt;t/ct/spec&gt;</code> – تغییر تیم</div>
                    <div class="command-block"><code>!rename &lt;old&gt; &lt;new&gt;</code> – تغییر نام بازیکن</div>
                    <div class="command-block"><code>!wmap &lt;workshop_id&gt;</code> – مپ ورکشاپ</div>
                </div>
            </div>
        </div>

        <!-- ========== کامندهای Practice ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-chalkboard-user"></i>
                <span>کامندهای مود Practice</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <p>این کامندها فقط در مود <code>!prac</code> فعال هستند.</p>
                <div class="command-grid-2">
                    <div class="command-block"><code>.spawn &lt;number&gt;</code> – رفتن به اسپان شماره</div>
                    <div class="command-block"><code>.ctspawn</code> / <code>.tspawn</code> – رفتن به اسپان CT/T</div>
                    <div class="command-block"><code>.bot</code> – اضافه کردن بات در موقعیت فعلی</div>
                    <div class="command-block"><code>.crouchbot</code> – بات نشسته</div>
                    <div class="command-block"><code>.boost</code> – بات و بوست شدن</div>
                    <div class="command-block"><code>.nobots</code> – حذف همه بات‌ها</div>
                    <div class="command-block"><code>.clear</code> – پاک کردن دود و آتش</div>
                    <div class="command-block"><code>.god</code> – حالت خدا</div>
                    <div class="command-block"><code>.noflash</code> – ایمنی در برابر فلش</div>
                    <div class="command-block"><code>.dryrun</code> / <code>.dry</code> – حالت Dry‑run</div>
                    <div class="command-block"><code>.savenade &lt;name&gt; &lt;desc&gt;</code> – ذخیره Lineup</div>
                    <div class="command-block"><code>.loadnade &lt;name&gt;</code> – بارگذاری Lineup</div>
                    <div class="command-block"><code>.listnades</code> – لیست Lineupها</div>
                    <div class="command-block"><code>.rethrow</code> – پرتاب مجدد آخرین گرنید</div>
                    <div class="command-block"><code>.timer</code> – تایمر</div>
                    <div class="command-block"><code>.back &lt;number&gt;</code> – بازگشت به موقعیت قبلی</div>
                    <div class="command-block"><code>.fastforward</code> / <code>.ff</code> – جلو بردن زمان</div>
                    <div class="command-block"><code>.solid</code> – تغییر <code>mp_solid_teammates</code></div>
                    <div class="command-block"><code>.impacts</code> – تغییر <code>sv_showimpacts</code></div>
                    <div class="command-block"><code>.traj</code> – تغییر مسیر گرنید</div>
                </div>
            </div>
        </div>

        <!-- ========== کامندهای Retakes ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-arrows-spin"></i>
                <span>کامندهای مود Retakes</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <div class="command-grid-2">
                    <div class="command-block"><code>!teleport &lt;x&gt; &lt;y&gt; &lt;z&gt;</code> – تله‌پورت به مختصات</div>
                    <div class="command-block"><code>!showspawns</code> – نمایش اسپان‌ها</div>
                    <div class="command-block"><code>!removespawn</code> – حذف اسپان نزدیک</div>
                    <div class="command-block"><code>!showqangle</code> – نمایش زاویه بازیکن</div>
                    <div class="command-block"><code>!debugqueues</code> – اطلاعات صف‌ها در کنسول</div>
                </div>
            </div>
        </div>

        <!-- ======================== کارت راهنمای کامل MatchZy ======================== -->
        <div class="manual-card matchzy-card">
            <div class="manual-card-header matchzy-card-header">
                <i class="fas fa-robot"></i>
                <span>راهنمای کامل افزونه MatchZy (Match/Practice/Pug)</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">  <!-- inline style حذف شد -->
                <h3>MatchZy چیست؟</h3>
                <p>MatchZy یک افزونه برای CS2 است که مدیریت مسابقات، تمرینات (Practice)، مود Pug و سیستم‌های مختلف را بسیار آسان می‌کند. با کامندهای ساده مانند <code>.ready</code>، <code>.pause</code>، <code>.tac</code> و بسیاری امکانات دیگر، همه چیز تحت کنترل شماست.</p>

                <h3>امکانات کلیدی</h3>
                <ul>
                    <li>حالت Pug با کامندهای ساده</li>
                    <li>پشتیبانی از Get5 Panel</li>
                    <li>مدیریت سری‌های BO1/BO3/BO5 و Veto</li>
                    <li>قفل کردن بازیکنان در تیم خود هنگام مسابقه</li>
                    <li>حالت تمرین با ده‌ها کامند کاربردی</li>
                    <li>راند چاقو (Knife round) با منطق کامل</li>
                    <li>ضبط خودکار دمو و آپلود خودکار در URL دلخواه</li>
                    <li>لیست سفید بازیکنان (Whitelist)</li>
                    <li>سیستم مربیگری (Coaching)</li>
                    <li>گزارش خسارت پس از هر راند</li>
                    <li>بازگردانی راند (Restore round)</li>
                    <li>ذخیره آمار در پایگاه داده SQLite/MySQL و خروجی CSV</li>
                    <li>و بسیاری امکانات دیگر...</li>
                </ul>

                <h3>مودهای MatchZy</h3>
                <div class="command-block"><strong>Pug mode</strong> – حالت پیش‌فرض، تیم‌ها قفل نیستند</div>
                <div class="command-block"><strong>Practice mode</strong> – با دستور <code>.prac</code> فعال می‌شود</div>
                <div class="command-block"><strong>Scrim mode</strong> – با <code>.playout</code> تمام راندها اجرا می‌شوند</div>
                <div class="command-block"><strong>Match mode</strong> – با بارگذاری یک فایل JSON مسابقه راه‌اندازی می‌شود</div>

                <h3>تنظیم مسابقه (Match Setup)</h3>
                <p>برای برگزاری یک سری مسابقه و قفل کردن بازیکنان در تیم و سمت درست، از یک فایل JSON استفاده کنید. ساختار نمونه:</p>
                <div class="command-block ltr-command-block">
<code>{
  "matchid": 27,
  "team1": { "name": "Astralis", "players": {...} },
  "team2": { "name": "NaVi", "players": {...} },
  "num_maps": 3,
  "maplist": ["de_mirage","de_overpass","de_inferno"],
  "map_sides": ["team1_ct","team2_ct","knife"],
  "spectators": {...},
  "clinch_series": true,
  "players_per_team": 5,
  "cvars": { "hostname": "MatchZy Game", "mp_friendlyfire": "0" }
}</code>
                </div>
                <p>بارگذاری فایل:</p>
                <div class="command-block"><code>matchzy_loadmatch &lt;filepath&gt;</code> – بارگذاری فایل JSON از مسیر سرور (مثلاً <code>matchzy_loadmatch astralis_vs_navi_27.json</code>)</div>
                <div class="command-block"><code>matchzy_loadmatch_url &lt;url&gt; [header name] [header value]</code> – دریافت فایل از یک آدرس اینترنتی</div>

                <h3>کامندهای عمومی بازیکنان (Player Commands)</h3>
                <p>بیشتر این کامندها را می‌توان با علامت <code>!</code> نیز به‌جای <code>.</code> استفاده کرد.</p>
                <div class="command-grid-2">
                    <div class="command-block"><code>.ready</code> / <code>.r</code> – اعلام آمادگی</div>
                    <div class="command-block"><code>.unready</code> / <code>.ur</code> – لغو آمادگی</div>
                    <div class="command-block"><code>.forceready</code> – آماده‌سازی اجباری تیم (فقط هنگام مسابقه JSON)</div>
                    <div class="command-block"><code>.pause</code> – توقف تاکتیکال/عادی در فریزتایم</div>
                    <div class="command-block"><code>.tech</code> – توقف فنی</div>
                    <div class="command-block"><code>.unpause</code> – درخواست ادامه (نیاز به تأیید هر دو تیم)</div>
                    <div class="command-block"><code>.stay</code> – ماندن در همان سمت پس از راند چاقو</div>
                    <div class="command-block"><code>.switch</code> / <code>.swap</code> – تعویض سمت</div>
                    <div class="command-block"><code>.stop</code> – بازگردانی بکاپ راند (هر دو تیم باید تأیید کنند)</div>
                    <div class="command-block"><code>.tac</code> – تایم‌اوت تاکتیکال</div>
                    <div class="command-block"><code>.coach &lt;side&gt;</code> – ورود به حالت مربی‌گری (مثال: <code>.coach t</code>)</div>
                    <div class="command-block"><code>.uncoach</code> – خروج از مربی‌گری</div>
                </div>

                <h3>کامندهای ادمین (Admin Commands)</h3>
                <div class="command-grid-2">
                    <div class="command-block"><code>.start</code> – شروع اجباری بازی</div>
                    <div class="command-block"><code>.restart</code> / <code>.endmatch</code> – ریست/پایان مسابقه</div>
                    <div class="command-block"><code>.forcepause</code> / <code>.fp</code> – توقف اجباری (قابل بازگشت فقط توسط ادمین)</div>
                    <div class="command-block"><code>.forceunpause</code> / <code>.fup</code> – ادامه اجباری</div>
                    <div class="command-block"><code>.restore &lt;round&gt;</code> – بازگردانی راند مشخص</div>
                    <div class="command-block"><code>.skipveto</code> / <code>.sv</code> – رد کردن Veto</div>
                    <div class="command-block"><code>.roundknife</code> / <code>.rk</code> – فعال/غیرفعال راند چاقو</div>
                    <div class="command-block"><code>.playout</code> – فعال/غیرفعال حالت Scrim (اجرای تمام راندها)</div>
                    <div class="command-block"><code>.whitelist</code> – فعال/غیرفعال لیست سفید (تنظیم در whitelist.cfg)</div>
                    <div class="command-block"><code>.readyrequired &lt;number&gt;</code> – تعداد بازیکن لازم برای آمادگی (0 یعنی همه)</div>
                    <div class="command-block"><code>.settings</code> – نمایش تنظیمات جاری</div>
                    <div class="command-block"><code>.map &lt;mapname&gt;</code> – تغییر مپ</div>
                    <div class="command-block"><code>.asay &lt;message&gt;</code> – ارسال پیام در چت به‌عنوان ادمین</div>
                    <div class="command-block"><code>.reload_admins</code> – بارگذاری مجدد لیست ادمین‌ها</div>
                    <div class="command-block"><code>.team1 &lt;name&gt;</code> / <code>.team2 &lt;name&gt;</code> – تنظیم نام تیم‌ها</div>
                    <div class="command-block"><code>.prac</code> / <code>.tactics</code> – شروع حالت تمرین</div>
                    <div class="command-block"><code>.exitprac</code> – خروج از حالت تمرین و بازگشت به مود مسابقه</div>
                    <div class="command-block"><code>.rcon &lt;command&gt;</code> – ارسال دستور مستقیم به سرور</div>
                </div>

                <h3>کامندهای حالت تمرین (Practice Mode)</h3>
                <p>این کامندها فقط در مود Practice فعال هستند.</p>
                <div class="command-grid-2">
                    <div class="command-block"><code>.spawn &lt;number&gt;</code> – رفتن به اسپان رقابتی شماره</div>
                    <div class="command-block"><code>.ctspawn</code> / <code>.tspawn</code> – رفتن به اسپان CT/T</div>
                    <div class="command-block"><code>.bestspawn</code> / <code>.worstspawn</code> – نزدیک‌ترین/دورترین اسپان تیم خودتان</div>
                    <div class="command-block"><code>.bestctspawn</code> / <code>.worstctspawn</code> – نزدیک‌ترین/دورترین اسپان CT</div>
                    <div class="command-block"><code>.besttspawn</code> / <code>.worsttspawn</code> – نزدیک‌ترین/دورترین اسپان T</div>
                    <div class="command-block"><code>.showspawns</code> / <code>.hidespawns</code> – نمایش/مخفی کردن اسپان‌ها</div>
                    <div class="command-block"><code>.bot</code> – اضافه کردن بات ایستاده</div>
                    <div class="command-block"><code>.crouchbot</code> / <code>.cbot</code> – بات نشسته</div>
                    <div class="command-block"><code>.boost</code> / <code>.crouchboost</code> – بات + بوست</div>
                    <div class="command-block"><code>.nobots</code> – حذف همه بات‌ها</div>
                    <div class="command-block"><code>.clear</code> – پاک کردن دود و آتش</div>
                    <div class="command-block"><code>.fastforward</code> / <code>.ff</code> – جلو بردن زمان به 20 ثانیه</div>
                    <div class="command-block"><code>.noflash</code> – ایمنی در برابر فلش</div>
                    <div class="command-block"><code>.dryrun</code> / <code>.dry</code> – حالت خشک (بدون آسیب)</div>
                    <div class="command-block"><code>.god</code> – حالت خدا</div>
                    <div class="command-block"><code>.break</code> – شکستن تمام اشیاء شکستنی</div>
                    <div class="command-block"><code>.rethrow</code> / <code>.rt</code> – پرتاب مجدد آخرین گرنید</div>
                    <div class="command-block"><code>.rethrowsmoke</code> / <code>.rethrownade</code> / ... – پرتاب مجدد بر اساس نوع</div>
                    <div class="command-block"><code>.timer</code> – شروع/توقف تایمر</div>
                    <div class="command-block"><code>.last</code> – بازگشت به محل پرتاب آخرین گرنید</div>
                    <div class="command-block"><code>.back &lt;number&gt;</code> – بازگشت به موقعیت شماره x در تاریخچه گرنید</div>
                    <div class="command-block"><code>.delay &lt;seconds&gt;</code> – تنظیم تأخیر برای پرتاب مجدد</div>
                    <div class="command-block"><code>.throwindex &lt;index1&gt; &lt;index2&gt; ...</code> – پرتاب گرنیدهای ذخیره‌شده با شماره</div>
                    <div class="command-block"><code>.lastindex</code> – نمایش شماره آخرین گرنید</div>
                    <div class="command-block"><code>.savenade &lt;name&gt; &lt;desc&gt;</code> / <code>.loadnade</code> / <code>.deletenade</code> – مدیریت لاین‌آپ‌ها</div>
                    <div class="command-block"><code>.listnades &lt;filter&gt;</code> – لیست لاین‌آپ‌های ذخیره شده</div>
                    <div class="command-block"><code>.importnade &lt;code&gt;</code> – وارد کردن لاین‌آپ از کد</div>
                    <div class="command-block"><code>.solid</code> – تغییر برخورد فیزیکی هم‌تیمی‌ها</div>
                    <div class="command-block"><code>.impacts</code> – نمایش نقاط برخورد گلوله</div>
                    <div class="command-block"><code>.traj</code> – نمایش مسیر پرتاب گرنید</div>
                    <div class="command-block"><code>.ct</code> / <code>.t</code> / <code>.spec</code> – تغییر تیم</div>
                    <div class="command-block"><code>.fas</code> / <code>.watchme</code> – همه به تماشاگر جز فراخوان‌دهنده</div>
                </div>

                <p class="info-note">📌 برای اطلاعات بیشتر به مستندات رسمی MatchZy و کانال Discord آن مراجعه کنید.</p>
            </div>
        </div>

        <!-- ======================== کارت جدید: راهنمایی ادمینی ======================== -->
        <div class="manual-card admin-guide-card">
            <div class="manual-card-header admin-guide-header">
                <i class="fas fa-user-shield"></i>
                <span>راهنمایی ادمینی</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">  <!-- inline style حذف شد -->
                <p>کامندهای مدیریتی سرور (با پیشوند <code>!</code>) که قبلاً با <code>css_</code> شناخته می‌شدند:</p>
                <div class="command-grid-2">
                    <div class="command-block"><code>!ban &lt;نام&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – بن کردن بازیکن</div>
                    <div class="command-block"><code>!addban &lt;steamid&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – اضافه کردن بن آفلاین</div>
                    <div class="command-block"><code>!banip &lt;ip&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – بن کردن IP</div>
                    <div class="command-block"><code>!unban &lt;steamid/ip&gt;</code> – رفع بن</div>
                    <div class="command-block"><code>!warn &lt;نام&gt; &lt;دلیل&gt;</code> – اخطار دادن</div>
                    <div class="command-block"><code>!unwarn &lt;نام&gt;</code> – حذف اخطار</div>
                    <div class="command-block"><code>!asay &lt;متن&gt;</code> – پیام به همه (ادمین)</div>
                    <div class="command-block"><code>!cssay &lt;متن&gt;</code> – پیام رنگی در وسط صفحه</div>
                    <div class="command-block"><code>!say &lt;متن&gt;</code> – پیام با نام ادمین</div>
                    <div class="command-block"><code>!psay &lt;نام&gt; &lt;متن&gt;</code> – پیام خصوصی</div>
                    <div class="command-block"><code>!csay &lt;متن&gt;</code> – پیام در وسط صفحه</div>
                    <div class="command-block"><code>!hsay &lt;متن&gt;</code> – پیام در HUD</div>
                    <div class="command-block"><code>!penalties</code> / <code>!mypenalties</code> / <code>!comms</code> – نمایش جریمه‌ها/محدودیت‌های ارتباطی</div>
                    <div class="command-block"><code>!admin</code> – باز کردن منوی ادمین</div>
                    <div class="command-block"><code>!adminhelp</code> – راهنمای دستورات ادمین</div>
                    <div class="command-block"><code>!addadmin &lt;steamid&gt; &lt;گروه&gt; &lt;مدت&gt;</code> – اضافه کردن ادمین</div>
                    <div class="command-block"><code>!deladmin &lt;steamid&gt;</code> – حذف ادمین</div>
                    <div class="command-block"><code>!addgroup &lt;نام گروه&gt; &lt;پرچم‌ها&gt;</code> – اضافه کردن گروه</div>
                    <div class="command-block"><code>!delgroup &lt;نام گروه&gt;</code> – حذف گروه</div>
                    <div class="command-block"><code>!reloadadmins</code> – بارگذاری مجدد لیست ادمین‌ها</div>
                    <div class="command-block"><code>!reloadbans</code> – بارگذاری مجدد لیست بن‌ها</div>
                    <div class="command-block"><code>!hide</code> / <code>!stealth</code> – مخفی شدن (نامرئی)</div>
                    <div class="command-block"><code>!hidecomms</code> – مخفی کردن ارتباطات ادمین</div>
                    <div class="command-block"><code>!who</code> – نمایش اطلاعات بازیکنان (نام، IP، احراز هویت)</div>
                    <div class="command-block"><code>!disconnected</code> / <code>!last</code> – نمایش بازیکنانی که اخیراً قطع شده‌اند</div>
                    <div class="command-block"><code>!warns &lt;نام&gt;</code> – نمایش اخطارهای یک بازیکن</div>
                    <div class="command-block"><code>!players</code> – لیست بازیکنان حاضر در سرور</div>
                    <div class="command-block"><code>!kick &lt;نام&gt;</code> – اخراج بازیکن</div>
                    <div class="command-block"><code>!map &lt;نام مپ&gt;</code> / <code>!changemap</code> – تغییر مپ</div>
                    <div class="command-block"><code>!wsmap &lt;workshop id&gt;</code> / <code>!workshop</code> – تغییر مپ از Workshop</div>
                    <div class="command-block"><code>!cvar &lt;نام&gt; &lt;مقدار&gt;</code> – تغییر ConVar</div>
                    <div class="command-block"><code>!rcon &lt;دستور&gt;</code> – اجرای دستور سرور</div>
                    <div class="command-block"><code>!rr</code> / <code>!restart</code> / <code>!restartgame</code> – ریست راند/بازی</div>
                    <div class="command-block"><code>!gag &lt;نام&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – گگ (محدودیت چت)</div>
                    <div class="command-block"><code>!addgag &lt;steamid&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – گگ آفلاین</div>
                    <div class="command-block"><code>!ungag &lt;نام&gt;</code> – رفع گگ</div>
                    <div class="command-block"><code>!mute &lt;نام&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – میوت (قطع میکروفون)</div>
                    <div class="command-block"><code>!addmute &lt;steamid&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – میوت آفلاین</div>
                    <div class="command-block"><code>!unmute &lt;نام&gt;</code> – رفع میوت</div>
                    <div class="command-block"><code>!silence &lt;نام&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – سایلنت (گگ + میوت همزمان)</div>
                    <div class="command-block"><code>!addsilence &lt;steamid&gt; &lt;دقیقه&gt; &lt;دلیل&gt;</code> – سایلنت آفلاین</div>
                    <div class="command-block"><code>!unsilence &lt;نام&gt;</code> – رفع سایلنت</div>
                    <div class="command-block"><code>!vote &lt;نوع&gt; &lt;گزینه‌ها&gt;</code> – ایجاد رأی‌گیری</div>
                    <div class="command-block"><code>!slay &lt;نام&gt;</code> – کشتن بازیکن</div>
                    <div class="command-block"><code>!slap &lt;نام&gt; &lt;قدرت&gt;</code> – سیلی زدن</div>
                    <div class="command-block"><code>!team &lt;نام&gt; &lt;t/ct/spec&gt;</code> – تغییر تیم</div>
                    <div class="command-block"><code>!rename &lt;نام فعلی&gt; &lt;نام جدید&gt;</code> – تغییر نام</div>
                    <div class="command-block"><code>!prename &lt;نام&gt; &lt;نام جدید&gt;</code> – تغییر نام دائمی (ماندگار)</div>
                    <div class="command-block"><code>!resize &lt;نام&gt; &lt;سایز&gt;</code> / <code>!size</code> – تغییر سایز بازیکن</div>
                    <div class="command-block"><code>!tp &lt;مقصد&gt;</code> / <code>!tpto</code> / <code>!goto</code> – تله‌پورت به بازیکن</div>
                    <div class="command-block"><code>!bring &lt;نام&gt;</code> / <code>!tphere</code> – آوردن بازیکن به نزد خود</div>
                    <div class="command-block"><code>!pluginsmanager</code> / <code>!pluginmanager</code> – مدیریت پلاگین‌ها</div>
                    <div class="command-block"><code>!adminvoice</code> / <code>!listenall</code> – شنود صدای همه (حتی میوت‌شده‌ها)</div>
                </div>
                <p class="info-note">🔰 تمامی این دستورات نیاز به پرچم ادمین مناسب دارند. برای مشاهده جزئیات بیشتر از <code>!adminhelp</code> استفاده کنید.</p>
            </div>
        </div>

        <!-- ========== سوالات متداول ========== -->
        <div class="manual-card">
            <div class="manual-card-header">
                <i class="fas fa-circle-question"></i>
                <span>سوالات متداول</span>
                <i class="fas fa-chevron-down accordion-icon"></i>
            </div>
            <div class="manual-card-body">
                <div class="faq-item">
                    <strong>❓ چگونه مود سرور را تغییر دهم؟</strong>
                    <p>از کامند <code>!modes</code> استفاده کنید یا مستقیماً کامند مود مورد نظر را تایپ کنید (<code>!dm</code>, <code>!retake</code> و...).</p>
                </div>
                <div class="faq-item">
                    <strong>❓ آیا می‌توانم اسلحه یا اسکین خود را تغییر دهم؟</strong>
                    <p>بله، از طریق سایت <a href="https://inventory.cstrike.app" target="_blank" class="manual-external-link">inventory.cstrike.app</a> اسکین‌ها را تنظیم کنید و در سرور <code>!ws</code> بزنید.</p>
                </div>
                <div class="faq-item">
                    <strong>❓ چگونه به RCON وصل شوم؟</strong>
                    <p>از کامند <code>!rcon</code> در چت استفاده کنید یا از نرم‌افزارهایی مثل SourceAdminTool.</p>
                </div>
                <div class="faq-item">
                    <strong>❓ چطور بات اضافه کنم؟</strong>
                    <p><code>!rcon bot_add_ct</code> یا <code>!rcon bot_add_t</code>. برای حذف <code>!rcon bot_kick</code>.</p>
                </div>
                <div class="faq-item">
                    <strong>❓ چرا بعضی مپ‌ها کار نمی‌کنند؟</strong>
                    <p>برخی مودها نیاز به مپ‌های Workshop دارند. از DNS شکن یا کلاینت کرک‌شده CS2 استفاده کنید.</p>
                </div>
                <div class="faq-item">
                    <strong>❓ چگونه از کلیدهای عددی برای منو استفاده کنم؟</strong>
                    <p>این بایندها را به کانفیگ خود اضافه کنید:</p>
                    <div class="command-block">
                        <code>bind "1" "slot1; css_1"</code><br>
                        <code>bind "2" "slot2; css_2"</code><br>
                        ...<br>
                        <code>bind "0" "slot10; css_0"</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== نکات مهم ========== -->
        <div class="warning-card">
            <div class="warning-card-header">
                <i class="fas fa-triangle-exclamation"></i>
                <span>نکات مهم</span>
            </div>
            <div class="warning-card-body">
                <ul>
                    <li>به جای نقطه (<code>.</code>) می‌توانید از علامت تعجب (<code>!</code>) استفاده کنید (بجز برخی کامندهای خاص).</li>
                    <li>کامندهای ادمین فقط برای کاربران دارای دسترسی ادمین قابل استفاده است.</li>
                    <li>کامندهای Practice فقط در مود Practice و کامندهای Retakes فقط در مود Retakes کار می‌کنند.</li>
                    <li>تغییر مکرر مود توصیه نمی‌شود؛ بهتر است بین هر تغییر، سرور را ریستارت کنید.</li>
                    <li>برای فعال‌سازی دائمی برخی افزونه‌ها از بخش custom_files استفاده کنید.</li>
                </ul>
            </div>
        </div>

        <div class="warning-card workshop-warning">
            <div class="warning-card-header">
                <i class="fas fa-exclamation-circle"></i>
                <span>هشدار Workshop</span>
            </div>
            <div class="warning-card-body">
                اکثر مودهای سرور به مپ‌های Workshop وابسته هستند. برای دسترسی به آن‌ها، از DNS شکن یا کلاینت کرک‌شده CS2 که دارای فایل‌های Workshop است استفاده کنید. در غیر این‌صورت پس از تغییر مود، موفق به ورود نخواهید شد.
            </div>
        </div>
    </div>
</div>

