<?php

require_once __DIR__ . '/../settings/loader.php';
// pages/reset_password.php



$token = $_GET['token'] ?? '';



$message = '';

$error = '';

$reset = null;



if (empty($token)) {



    $error = 'توکن نامعتبر است.';



} else {



    try {



        $cfg=fragcity_settings('files.pages.reset_password',array());
        $profile=isset($cfg['database_profile'])?$cfg['database_profile']:'legacy_services';
        $pdo=fragcity_create_pdo($profile);
        if(!$pdo)throw new PDOException('Database unavailable or not configured.');



        $pdo->setAttribute(

            PDO::ATTR_ERRMODE,

            PDO::ERRMODE_EXCEPTION

        );



        /*

         * بررسی توکن

         */

        $stmt = $pdo->prepare(

            "SELECT steamid

             FROM password_resets

             WHERE token = ?

             AND expires_at > NOW()"

        );



        $stmt->execute([

            $token

        ]);



        $reset = $stmt->fetch(

            PDO::FETCH_ASSOC

        );



        if (!$reset) {



            $error =

                'توکن نامعتبر یا منقضی شده است.';



        } else {



            /*

             * ثبت رمز جدید

             */

            if (

                $_SERVER['REQUEST_METHOD'] === 'POST'

            ) {



                $password =

                    $_POST['password'] ?? '';



                $password_confirm =

                    $_POST['password_confirm'] ?? '';





                /*

                 * بررسی تطابق رمزها

                 */

                if (

                    $password !==

                    $password_confirm

                ) {



                    $error =

                        'رمزهای عبور مطابقت ندارند.';



                }



                /*

                 * بررسی حداقل طول رمز

                 */

                elseif (

                    strlen($password) < 6

                ) {



                    $error =

                        'رمز عبور باید حداقل ۶ کاراکتر باشد.';



                }



                /*

                 * ذخیره رمز جدید

                 */

                else {



                    $hash =

                        password_hash(

                            $password,

                            PASSWORD_BCRYPT

                        );





                    $stmt =

                        $pdo->prepare(

                            "UPDATE users

                             SET password_hash = ?

                             WHERE steamid64 = ?"

                        );



                    $stmt->execute([

                        $hash,

                        $reset['steamid']

                    ]);





                    /*

                     * حذف توکن پس از استفاده

                     */

                    $pdo

                        ->prepare(

                            "DELETE FROM password_resets

                             WHERE token = ?"

                        )

                        ->execute([

                            $token

                        ]);





                    $message =

                        'رمز عبور شما با موفقیت تغییر کرد.';

                }



            }



        }



    } catch (

        PDOException $e

    ) {



        $error =

            'خطایی در ارتباط با دیتابیس رخ داد.';



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





        <!-- عنوان -->

        <h2>

            بازنشانی رمز عبور

        </h2>





        <p class="auth-subtitle">

            یک رمز عبور جدید برای حساب خود انتخاب کنید

        </p>





        <!-- ==========================

             Success Message

        =========================== -->



        <?php if ($message): ?>



            <div class="auth-message success">



                <i class="fas fa-check-circle"></i>



                <span>

                    <?= htmlspecialchars($message) ?>

                </span>



            </div>





            <a

                href="?page=login"

                class="btn-auth-submit auth-message-button"

                data-ajax-nav

                data-slug="login"

            >



                <i class="fas fa-sign-in-alt"></i>



                ورود به حساب



            </a>





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





            <a

                href="?page=login"

                class="auth-message-button-secondary"

                data-ajax-nav

                data-slug="login"

            >



                <i class="fas fa-arrow-right"></i>



                بازگشت به ورود



            </a>





        <!-- ==========================

             Reset Password Form

        =========================== -->



        <?php elseif ($reset): ?>



            <form

                id="resetPasswordForm"

                method="post"

                action=""

            >



                <!-- رمز جدید -->

                <div class="form-group">



                    <label for="resetPassword">



                        رمز عبور جدید



                    </label>



                    <div class="password-box">



                        <input

                            id="resetPassword"

                            type="password"

                            name="password"

                            autocomplete="new-password"

                            minlength="6"

                            required

                        >



                        <button

                            type="button"

                            class="toggle-password"

                            data-password-toggle

                            data-password-target="resetPassword"

                            aria-label="نمایش رمز عبور"

                        >



                            <i class="fas fa-eye"></i>



                        </button>



                    </div>



                </div>





                <!-- تکرار رمز جدید -->

                <div class="form-group">



                    <label for="resetPasswordConfirm">



                        تکرار رمز عبور



                    </label>



                    <div class="password-box">



                        <input

                            id="resetPasswordConfirm"

                            type="password"

                            name="password_confirm"

                            autocomplete="new-password"

                            minlength="6"

                            required

                        >



                        <button

                            type="button"

                            class="toggle-password"

                            data-password-toggle

                            data-password-target="resetPasswordConfirm"

                            aria-label="نمایش رمز عبور"

                        >



                            <i class="fas fa-eye"></i>



                        </button>



                    </div>



                </div>





                <!-- Submit -->

                <button

                    type="submit"

                    class="btn-auth-submit"

                >



                    <i class="fas fa-save"></i>



                    ذخیره رمز جدید



                </button>



            </form>



        <?php endif; ?>





        <!-- ==========================

             Bottom Navigation

        =========================== -->



        <?php if (!$message): ?>



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



        <?php endif; ?>



    </div>



</div>