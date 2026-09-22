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

/**
 * Send a password reset OTP email.
 */
function send_password_reset_email(
    $toEmail,
    $firstName,
    $resetCode
) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mddinar31@gmail.com';
        $mail->Password = 'hrrx wwwm fotp mjmi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('mddinar31@gmail.com', 'Smart Matrimony');
        $mail->addAddress($toEmail, $firstName);
        $mail->isHTML(true);
        $mail->Subject = 'Smart Matrimony - Password Reset Code';

        $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($resetCode, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <div style="margin:0;padding:40px 15px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
            <div style="max-width:520px;margin:auto;background:#ffffff;padding:35px;border-radius:18px;box-shadow:0 10px 35px rgba(15,23,42,.08);">
                <h2 style="margin:0 0 15px;color:#0f766e;">Smart Matrimony</h2>
                <p style="color:#334155;font-size:15px;">Hello ' . $safeFirstName . ',</p>
                <p style="color:#64748b;line-height:1.7;">We received a request to reset the password for your Smart Matrimony account.</p>
                <p style="color:#64748b;line-height:1.7;">Please use the following verification code to continue:</p>
                <div style="margin:25px 0;padding:22px;text-align:center;background:#f0fdf4;border-radius:14px;">
                    <div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#0f766e;">' . $safeCode . '</div>
                </div>
                <p style="color:#64748b;font-size:14px;">This verification code is valid for 2 minutes.</p>
                <p style="color:#94a3b8;font-size:13px;margin-top:25px;">If you did not request a password reset, you can safely ignore this email.</p>
                <hr style="border:0;border-top:1px solid #e2e8f0;margin:25px 0;">
                <p style="margin:0;color:#94a3b8;font-size:12px;">Smart Matrimony</p>
            </div>
        </div>';

        $mail->AltBody =
            "Hello {$firstName},\n\n" .
            "Your Smart Matrimony password reset code is: {$resetCode}\n\n" .
            "This code is valid for 2 minutes.\n\n" .
            "If you did not request a password reset, you can safely ignore this email.\n\n" .
            "Smart Matrimony";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Smart Matrimony Password Reset SMTP Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send a booking status notification to a customer.
 * Returns true only when PHPMailer reports a successful send.
 */
function send_booking_status_email($toEmail, $firstName, array $booking, $status, $cancellationReason = '', $managerName = 'Event Manager') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mddinar31@gmail.com';
        $mail->Password = 'hrrx wwwm fotp mjmi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('mddinar31@gmail.com', 'Smart Matrimony');
        $mail->addAddress($toEmail, $firstName);
        $mail->isHTML(true);

        $isCancelled = $status === 'Cancelled';
        $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeBookingId = (int) ($booking['booking_id'] ?? 0);
        $safeTotal = number_format((float) ($booking['total_price'] ?? 0), 2);
        $safeReason = htmlspecialchars($cancellationReason, ENT_QUOTES, 'UTF-8');
        $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
        $safeManagerName = htmlspecialchars($managerName ?: 'Event Manager', ENT_QUOTES, 'UTF-8');

        $subject = $isCancelled
            ? 'Smart Matrimony - Booking Cancelled'
            : 'Smart Matrimony - Booking Confirmed';
        $mail->Subject = $subject;

        $itemsHtml = '';
        $itemsText = '';
        foreach (($booking['items'] ?? []) as $item) {
            $service = htmlspecialchars($item['service_name'] ?? 'Service', ENT_QUOTES, 'UTF-8');
            $package = htmlspecialchars($item['package_name'] ?? 'Package', ENT_QUOTES, 'UTF-8');
            $quantity = (int) ($item['quantity'] ?? 1);
            $eventDate = !empty($item['event_date']) ? date('d M Y', strtotime($item['event_date'])) : '—';
            $itemsHtml .= '<tr><td style="padding:9px 0;border-bottom:1px solid #eef2f2;color:#334155;">' . $service . '</td><td style="padding:9px 0;border-bottom:1px solid #eef2f2;color:#334155;">' . $package . ' × ' . $quantity . '</td><td style="padding:9px 0;border-bottom:1px solid #eef2f2;color:#64748b;text-align:right;">' . htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            $itemsText .= "- {$item['service_name']} — {$item['package_name']} × {$quantity} — {$eventDate}\n";
        }

        $intro = $isCancelled
            ? 'Your booking request has been cancelled by the Event Manager.'
            : 'Your booking request has been confirmed by the Event Manager.';

        $reasonBlock = $isCancelled ? '
            <div style="margin:20px 0;padding:16px 18px;background:#fff7f7;border:1px solid #f5d7d7;border-radius:12px;">
                <strong style="display:block;color:#9f2f2f;margin-bottom:7px;">Reason for cancellation</strong>
                <div style="color:#5f6470;line-height:1.65;">' . nl2br($safeReason) . '</div>
            </div>' : '';

        $mail->Body = '
        <div style="margin:0;padding:40px 15px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
            <div style="max-width:620px;margin:auto;background:#ffffff;padding:35px;border-radius:18px;box-shadow:0 10px 35px rgba(15,23,42,.08);">
                <h2 style="margin:0 0 15px;color:#0f766e;">Smart Matrimony</h2>
                <p style="color:#334155;font-size:15px;">Hello ' . $safeFirstName . ',</p>
                <p style="color:#64748b;line-height:1.7;">' . $intro . '</p>
                <div style="margin:22px 0;padding:17px 18px;background:#f0fdf4;border-radius:13px;">
                    <div style="color:#64748b;font-size:13px;">Booking ID</div>
                    <strong style="display:block;margin-top:4px;color:#0f172a;font-size:20px;">#' . $safeBookingId . '</strong>
                    <div style="margin-top:10px;color:#64748b;font-size:13px;">Status: <strong style="color:#0f766e;">' . $safeStatus . '</strong></div>
                    <div style="margin-top:8px;color:#64748b;font-size:13px;">Handled by: <strong style="color:#0f766e;">' . $safeManagerName . '</strong></div>
                </div>
                <h3 style="margin:24px 0 10px;color:#17233b;font-size:16px;">Booked services</h3>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr><th style="text-align:left;padding:8px 0;color:#7b8798;border-bottom:1px solid #e2e8f0;">Service</th><th style="text-align:left;padding:8px 0;color:#7b8798;border-bottom:1px solid #e2e8f0;">Package</th><th style="text-align:right;padding:8px 0;color:#7b8798;border-bottom:1px solid #e2e8f0;">Event date</th></tr></thead>
                    <tbody>' . $itemsHtml . '</tbody>
                </table>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;text-align:right;color:#64748b;font-size:13px;">Total booking value <strong style="color:#0f172a;font-size:17px;">৳' . $safeTotal . '</strong></div>
                ' . $reasonBlock . '
                <p style="color:#94a3b8;font-size:13px;line-height:1.6;margin-top:25px;">If you have questions about this booking, please contact Smart Matrimony support.</p>
                <hr style="border:0;border-top:1px solid #e2e8f0;margin:25px 0;">
                <p style="margin:0;color:#94a3b8;font-size:12px;">Smart Matrimony</p>
            </div>
        </div>';

        $mail->AltBody = "Hello {$firstName},\n\n{$intro}\n\nBooking ID: #{$safeBookingId}\nStatus: {$status}\n\nBooked services:\n{$itemsText}\nTotal booking value: ৳{$safeTotal}\n" . ($isCancelled ? "\nReason for cancellation:\n{$cancellationReason}\n" : '') . "\nSmart Matrimony";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Smart Matrimony Booking SMTP Error: ' . $mail->ErrorInfo);
        return false;
    }
}


function send_verification_status_email($toEmail, $firstName, $status, $remarks, $authenticatorName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mddinar31@gmail.com';
        $mail->Password = 'hrrx wwwm fotp mjmi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('mddinar31@gmail.com', 'Smart Matrimony');
        $mail->addAddress($toEmail, $firstName);
        $mail->isHTML(true);
        $safeFirst = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
        $safeRemarks = nl2br(htmlspecialchars(trim($remarks) !== '' ? $remarks : 'No additional comment was provided.', ENT_QUOTES, 'UTF-8'));
        $safeAuth = htmlspecialchars($authenticatorName, ENT_QUOTES, 'UTF-8');
        $mail->Subject = 'Smart Matrimony - Profile Verification '.$status;
        $mail->Body = '<div style="margin:0;padding:40px 15px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif"><div style="max-width:560px;margin:auto;background:#fff;padding:35px;border-radius:18px;box-shadow:0 10px 35px rgba(15,23,42,.08)"><h2 style="margin:0 0 15px;color:#0f766e">Smart Matrimony</h2><p style="color:#334155">Hello '.$safeFirst.',</p><p style="color:#64748b;line-height:1.7">Your member profile verification has been <strong>'.$safeStatus.'</strong>.</p><div style="margin:22px 0;padding:18px;background:#f0fdf4;border-radius:14px"><strong>Authenticator:</strong> '.$safeAuth.'<br><strong>Comment:</strong><div style="margin-top:8px;color:#475569;line-height:1.7">'.$safeRemarks.'</div></div><p style="color:#64748b;line-height:1.7">Please log in to your Smart Matrimony account to review your profile and take any necessary action.</p><hr style="border:0;border-top:1px solid #e2e8f0;margin:25px 0"><p style="margin:0;color:#94a3b8;font-size:12px">Smart Matrimony</p></div></div>';
        $mail->AltBody = "Hello {$firstName},\n\nYour Smart Matrimony member profile verification has been {$status}.\n\nAuthenticator: {$authenticatorName}\nComment: " . (trim($remarks) !== '' ? trim($remarks) : 'No additional comment was provided.') . "\n\nSmart Matrimony";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Smart Matrimony Verification Status SMTP Error: '.$mail->ErrorInfo);
        return false;
    }
}
