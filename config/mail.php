<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


function send_verification_email(
    $toEmail,
    $firstName,
    $verificationCode
) {

    $mail = new PHPMailer(true);

    try {

        // =========================
        // Gmail SMTP
        // =========================

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'mddinar31@gmail.com';

        /*
         * IMPORTANT:
         * এখানে Gmail App Password বসাও.
         * Normal Gmail password নয়.
         */
        $mail->Password = 'hrrx wwwm fotp mjmi';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';


        // =========================
        // Sender
        // =========================

        $mail->setFrom(
            'mddinar31@gmail.com',
            'Smart Matrimony'
        );


        // =========================
        // Receiver
        // =========================

        $mail->addAddress(
            $toEmail,
            $firstName
        );


        // =========================
        // Email
        // =========================

        $mail->isHTML(true);

        $mail->Subject =
            'Smart Matrimony - Email Verification Code';


        $safeFirstName = htmlspecialchars(
            $firstName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeCode = htmlspecialchars(
            $verificationCode,
            ENT_QUOTES,
            'UTF-8'
        );


        $mail->Body = '

        <div style="
            margin:0;
            padding:40px 15px;
            background:#f8fafc;
            font-family:Arial,Helvetica,sans-serif;
        ">

            <div style="
                max-width:520px;
                margin:auto;
                background:#ffffff;
                padding:35px;
                border-radius:18px;
                box-shadow:0 10px 35px rgba(15,23,42,.08);
            ">

                <h2 style="
                    margin:0 0 15px;
                    color:#0f766e;
                ">
                    Smart Matrimony
                </h2>

                <p style="
                    color:#334155;
                    font-size:15px;
                ">
                    Hello ' . $safeFirstName . ',
                </p>

                <p style="
                    color:#64748b;
                    line-height:1.7;
                ">
                    Thank you for registering with
                    Smart Matrimony.
                </p>

                <p style="
                    color:#64748b;
                    line-height:1.7;
                ">
                    Please use the following code
                    to verify your email address:
                </p>

                <div style="
                    margin:25px 0;
                    padding:22px;
                    text-align:center;
                    background:#f0fdf4;
                    border-radius:14px;
                ">

                    <div style="
                        font-size:32px;
                        font-weight:700;
                        letter-spacing:8px;
                        color:#0f766e;
                    ">
                        ' . $safeCode . '
                    </div>

                </div>

                <p style="
                    color:#64748b;
                    font-size:14px;
                ">
                    This verification code is valid
                    for 2 minutes.
                </p>

                <p style="
                    color:#94a3b8;
                    font-size:13px;
                    margin-top:25px;
                ">
                    If you did not create this account,
                    please ignore this email.
                </p>

                <hr style="
                    border:0;
                    border-top:1px solid #e2e8f0;
                    margin:25px 0;
                ">

                <p style="
                    margin:0;
                    color:#94a3b8;
                    font-size:12px;
                ">
                    Smart Matrimony
                </p>

            </div>

        </div>
        ';


        // Plain-text version
        $mail->AltBody =
            "Hello {$firstName},\n\n" .
            "Your Smart Matrimony verification code is: " .
            "{$verificationCode}\n\n" .
            "This code is valid for 2 minutes.\n\n" .
            "Smart Matrimony";


        // Send email
        $mail->send();

        return true;


    } catch (Exception $e) {

        error_log(
            'Smart Matrimony SMTP Error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}