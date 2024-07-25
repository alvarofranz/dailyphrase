<?php

// Function to send an email using SendGrid
function send_email($email_address, $email_subject, $email_content, $attachments = []): bool
{
    $email = new \SendGrid\Mail\Mail();
    $email->setFrom("info@dailyphrase.email", "Daily Phrase");
    $email->setSubject($email_subject);
    $email->addTo($email_address);
    $email->addContent("text/html", $email_content);
    // Add attachments
    foreach ($attachments as $attachment) {
        $email->addAttachment(
            $attachment['base64'],
            $attachment['type'],
            $attachment['filename'],
            $attachment['disposition']
        );
    }
    $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
    try {
        $sendgrid->send($email);

        // Append attachment filenames to the email content if any
        if (count($attachments) > 0) {
            $email_content .= '<br><strong>Attachments:</strong>';
            foreach ($attachments as $attachment) {
                $email_content .= '<br>' . $attachment['filename'];
            }
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}