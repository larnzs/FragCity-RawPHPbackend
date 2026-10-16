<?php

require_once __DIR__ . '/../settings/loader.php';
// pages/forgot_password.php



// این صفحه نیاز به لاگین ندارد



$message = '';

$error = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $steamid = trim($_POST['steamid64'] ?? '');



    if (!preg_match('/^[0-9]{17}$/', $steamid)) {



        $error = 'SteamID معتبر نیست.';



    } else {



        try {



            $cfg=fragcity_settings('files.pages.forgot_password',array());
            $profile=isset($cfg['database_profile'])?$cfg['database_profile']:'legacy_services';
            $pdo=fragcity_create_pdo($profile);
            if(!$pdo)throw new PDOException('Database unavailable or not configured.');



            $pdo->setAttribute(

                PDO::ATTR_ERRMODE,

                PDO::ERRMODE_EXCEPTION

            );



            $stmt = $pdo->prepare(

                "SELECT steamid64, email

                 FROM users

                 WHERE steamid64 = ?"

            );



            $stmt->execute([

                $steamid

            ]);



            $user = $stmt->fetch(

                PDO::FETCH_ASSOC

            );



            if ($user) {



                // تولید توکن بازنشانی

                $token = bin2hex(

                    random_bytes(32)

                );



                $expires = date(

                    'Y-m-d H:i:s',

                    strtotime('+1 hour')

                );



                $stmt = $pdo->prepare(

                    "INSERT INTO password_resets

                    (steamid, token, expires_at)

                    VALUES (?, ?, ?)"

                );



                $stmt->execute([

                    $steamid,

                    $token,

                    $expires

                ]);



                // لینک بازنشانی

                $resetLink =

                    "http://" .

                    $_SERVER['HTTP_HOST'] .

                    "/index.php?page=reset_password&token=" .

                    urlencode($token);



                /*

                 * فعلاً لینک مستقیماً نمایش داده می‌شود.

                 * در نسخه نهایی می‌توان آن را با Email ارسال کرد.

                 */



                $message =

                    'لینک بازنشانی رمز عبور شما ایجاد شد. '

                    . 'در نسخه نهایی این لینک از طریق ایمیل ارسال خواهد شد.';



                $resetLinkForDisplay =

                    $resetLink;



            } else {



                $error =

                    'کاربری با این SteamID یافت نشد.';



            }



        } catch (PDOException $e) {



            $error =

                'خطایی در ارتباط با دیتابیس رخ داد.';



        }



    }



}

?>



<div class="auth-page">



    <div class="auth-container">



        <!-- FragCity Logo -->

        <div class="auth-logo">



            <img

                src="assets/images/logo/main.png"

                alt="FragCity Logo"

            >



        </div>





        <!-- Title -->

        <h2>

            فراموشی رمز عبور

        </h2>





        <p class="auth-subtitle">

            برای بازنشانی رمز عبور، SteamID64 خود را وارد کنید

        </p>





        <!-- ==========================

             Success Message

        =========================== -->



        <?php if ($message): ?>



            <div class="auth-message success">



                <i class="fas fa-check-circle"></i>



                <div class="auth-message-content">



                    <span>

                        <?= htmlspecialchars($message) ?>

                    </span>



                    <?php if (!empty($resetLinkForDisplay)): ?>



                        <a

                            href="<?= htmlspecialchars($resetLinkForDisplay) ?>"

                            class="auth-reset-link"

                        >

                            <?= htmlspecialchars($resetLinkForDisplay) ?>

                        </a>



                    <?php endif; ?>



                </div>



            </div>





        <!-- ==========================

             Error Message

        =========================== -->



        <?php elseif ($error): ?>



            <div class="auth-message error">



                <i class="fas fa-exclamation-circle"></i>



                <span>

                    <?= htmlspecialchars($error) ?>

                </span>



            </div>



        <?php endif; ?>





        <!-- ==========================

             Reset Request Form

        =========================== -->



        <form

            id="forgotPasswordForm"

            method="post"

            action=""

        >



            <div class="form-group">



                <label for="forgotSteamId">



                    SteamID64



                </label>



                <input

                    id="forgotSteamId"

                    type="text"

                    name="steamid64"

                    placeholder="7656119xxxxxxxxx"

                    autocomplete="off"

                    inputmode="numeric"

                    maxlength="17"

                    pattern="[0-9]{17}"

                    required

                >



            </div>





            <button

                type="submit"

                class="btn-auth-submit"

            >



                <i class="fas fa-paper-plane"></i>



                ارسال لینک بازنشانی



            </button>



        </form>





        <!-- ==========================

             Back To Login

        =========================== -->



        <p class="auth-switch">



            <a

                href="?page=login"

                data-ajax-nav

                data-slug="login"

            >



                <i class="fas fa-arrow-right"></i>



                بازگشت به ورود



            </a>



        </p>



    </div>



</div>