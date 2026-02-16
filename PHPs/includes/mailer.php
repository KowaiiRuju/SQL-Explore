<?php
/**
 * Simple email helper.
 * Since most local environments don't have a mail server,
 * this function logs the email content to a file for easy testing.
 */
function send_email($to, $subject, $body) {
    // Attempt to send using PHP's mail function (might fail locally)
    $headers = "From: noreply@sqlexplore.local\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Log for local testing
    $logFile = __DIR__ . '/../../emails.log';
    $logEntry = sprintf(
        "[%s] To: %s | Subject: %s\nBody:\n%s\n%s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $body,
        str_repeat('-', 40)
    );
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // Try actual send (suppressed error)
    return @mail($to, $subject, $body, $headers);
}
