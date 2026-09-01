<?php

$page_css = 'assets/css/verify_email.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================
   CHECK PENDING USER
========================= */

if (
    empty($_SESSION['pending_verification_user_id'])
) {

    header('Location: register.php');
    exit;
}

$user_id = (int) $_SESSION[
    'pending_verification_user_id'
];

$error = '';
$success = '';


/* =========================
   GET USER
========================= */

$user_stmt = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        first_name,
        email,
        account_status
     FROM users
     WHERE user_id=?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($user_stmt);

$user_result = mysqli_stmt_get_result(
    $user_stmt
);

$user = mysqli_fetch_assoc(
    $user_result
);

mysqli_stmt_close($user_stmt);


if (!$user) {

    unset(
        $_SESSION['pending_verification_user_id']
    );

    header('Location: register.php');
    exit;
}

/* If already verified/active, do not show the verification form. */
if (($user['account_status'] ?? '') === 'Active') {
    unset($_SESSION['pending_verification_user_id']);
    header('Location: login.php');
    exit;
}


/* =========================
   GET VERIFICATION RECORD
========================= */

$verification_stmt = mysqli_prepare(
    $conn,
    "SELECT
        verification_id,
        email,
        code_hash,
        expires_at,
        last_sent_at,
        verified_at
     FROM email_verifications
     WHERE user_id=?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $verification_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute(
    $verification_stmt
);

$verification_result =
    mysqli_stmt_get_result(
        $verification_stmt
    );

$verification =
    mysqli_fetch_assoc(
        $verification_result
    );

mysqli_stmt_close(
    $verification_stmt
);


if (!$verification) {

    $error =
        "Verification request was not found.";
}


/* =========================
   VERIFY CODE
========================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['verify_code'])
) {

    $code = trim(
        $_POST['verification_code'] ?? ''
    );


    if (!preg_match('/^[0-9]{6}$/', $code)) {

        $error =
            "Please enter the 6-digit verification code.";

    } elseif (!$verification) {

        $error =
            "Verification request was not found.";

    } elseif (
        !empty($verification['verified_at'])
    ) {

        header('Location: login.php');
        exit;

    } elseif (
        strtotime($verification['expires_at']) < time()
    ) {

        $error =
            "This verification code has expired. Please resend a new code.";

    } elseif (
        !password_verify(
            $code,
            $verification['code_hash']
        )
    ) {

        $error =
            "Incorrect verification code. Please try again.";

    } else {

        /*
         * Activate account
         */
        $activate_stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET account_status='Active'
             WHERE user_id=?"
        );

        mysqli_stmt_bind_param(
            $activate_stmt,
            "i",
            $user_id
        );

        $activated =
            mysqli_stmt_execute(
                $activate_stmt
            );

        mysqli_stmt_close(
            $activate_stmt
        );


        if ($activated) {

            /*
             * Mark email verification complete
             */
            $verified_stmt = mysqli_prepare(
                $conn,
                "UPDATE email_verifications
                 SET verified_at=NOW()
                 WHERE verification_id=?"
            );

            $verification_id =
                (int) $verification[
                    'verification_id'
                ];

            mysqli_stmt_bind_param(
                $verified_stmt,
                "i",
                $verification_id
            );

            mysqli_stmt_execute(
                $verified_stmt
            );

            mysqli_stmt_close(
                $verified_stmt );


            unset(
                $_SESSION['pending_verification_user_id']
            );

            header(
                'Location: login.php?registered=1'
            );

            exit;

        } else {

            $error =
                "Verification could not be completed. Please try again.";
        }
    }
}


/* =========================
   RESEND CODE
========================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['resend_code'])
) {

    if (!$verification) {

        $error =
            "Verification request was not found.";

    } else {

        $last_sent_timestamp =
            strtotime(
                $verification['last_sent_at']
            );

        $seconds_passed =
            time() - $last_sent_timestamp;

        $remaining =
            120 - $seconds_passed;


        if ($remaining > 0) {

            $error =
                "Please wait {$remaining} seconds before requesting another code.";

        } else {

            /*
             * Generate new code
             */
            $new_code =
                str_pad(
                    (string) random_int(0, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $new_hash =
                password_hash(
                    $new_code,
                    PASSWORD_DEFAULT
                );

            $new_expires =
                date(
                    'Y-m-d H:i:s',
                    time() + 120
                );

            $new_last_sent =
                date(
                    'Y-m-d H:i:s'
                );


            /*
             * Send the new verification code through the same
             * PHPMailer/Gmail SMTP configuration used at registration.
             */
            if (
                send_verification_email(
                    $user['email'],
                    $user['first_name'],
                    $new_code
                )
            ) {

                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE email_verifications
                     SET
                        code_hash=?,
                        expires_at=?,
                        last_sent_at=?,
                        verified_at=NULL
                     WHERE verification_id=?"
                );

                $verification_id =
                    (int) $verification[
                        'verification_id'
                    ];

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $new_hash,
                    $new_expires,
                    $new_last_sent,
                    $verification_id
                );

                if (
                    mysqli_stmt_execute(
                        $update_stmt
                    )
                ) {

                    $success =
                        "A new verification code has been sent to your email.";

                    /*
                     * Refresh verification data
                     */
                    $verification[
                        'code_hash'
                    ] = $new_hash;

                    $verification[
                        'expires_at'
                    ] = $new_expires;

                    $verification[
                        'last_sent_at'
                    ] = $new_last_sent;

                } else {

                    $error =
                        "The new code could not be saved.";
                }

                mysqli_stmt_close(
                    $update_stmt
                );

            } else {

                $error =
                    "We could not send the verification email.";
            }
        }
    }
}


/* =========================
   DISPLAY DATA
========================= */

$masked_email = $user['email'];

$email_parts =
    explode('@', $masked_email);

if (
    count($email_parts) === 2
) {

    $name = $email_parts[0];
    $domain = $email_parts[1];

    if (strlen($name) > 2) {

        $masked_name =
            substr($name, 0, 2) .
            str_repeat(
                '*',
                max(1, strlen($name) - 2)
            );

    } else {

        $masked_name =
            substr($name, 0, 1) . '*';
    }

    $masked_email =
        $masked_name . '@' . $domain;
}

$last_sent_timestamp =
    strtotime(
        $verification['last_sent_at'] ?? 'now'
    );

$resend_remaining =
    max(
        0,
        120 - (time() - $last_sent_timestamp)
    );


include 'includes/header.php';
include 'includes/navbar.php';

?>

<div class="verify-page">

    <div class="verify-card">

        <div class="verify-icon">

            <i class="fa-solid fa-envelope-circle-check"></i>

        </div>

        <h1>Verify Your Email</h1>

        <p class="verify-description">

            We sent a 6-digit verification code to

            <strong>
                <?= htmlspecialchars($masked_email); ?>
            </strong>

        </p>


        <?php if ($error !== '') : ?>

            <div class="verify-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>
                    <?= htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if ($success !== '') : ?>

            <div class="verify-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <span>
                    <?= htmlspecialchars($success); ?>
                </span>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="verification-form"
        >

            <label
                for="verification_code"
                class="form-label"
            >
                Verification Code
            </label>

            <input
                type="text"
                name="verification_code"
                id="verification_code"
                class="verification-input"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
            >

            <button
                type="submit"
                name="verify_code"
                class="verify-button"
            >

                <i class="fa-solid fa-shield-check"></i>

                Verify Email

            </button>

        </form>


        <div class="resend-section">

            <p id="resendMessage">

                Didn't receive the code?

            </p>

            <form
                method="POST"
                id="resendForm"
            >

                <button
                    type="submit"
                    name="resend_code"
                    id="resendButton"
                    class="resend-button"
                    disabled
                >
                    Resend Code
                </button>

            </form>

        </div>


        <a
            href="register.php"
            class="back-link"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Back to Registration
        </a>

    </div>

</div>


<script>

const resendButton =
    document.getElementById('resendButton');

const resendMessage =
    document.getElementById('resendMessage');

let remaining =
    <?= (int) $resend_remaining; ?>;


function updateResendTimer()
{
    if (remaining <= 0) {

        resendButton.disabled = false;

        resendMessage.textContent =
            "Didn't receive the code?";

        return;
    }

    const minutes =
        Math.floor(remaining / 60);

    const seconds =
        remaining % 60;

    resendButton.disabled = true;

    resendMessage.textContent =
        `Resend available in ${
            minutes
        }:${
            String(seconds).padStart(2, '0')
        }`;

    remaining--;

    setTimeout(
        updateResendTimer,
        1000
    );
}


updateResendTimer();


document
    .getElementById('verification_code')
    .addEventListener(
        'input',
        function () {

            this.value =
                this.value
                    .replace(/\D/g, '')
                    .slice(0, 6);

        }
    );

</script>

<?php include 'includes/footer.php'; ?>