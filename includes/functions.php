<?php

// Function to send an email using SendGrid
function send_email($email_address, $email_subject, $email_content): bool
{
    $email = new \SendGrid\Mail\Mail();
    $email->setFrom("info@dailyphrase.email", "Daily Phrase");
    $email->setSubject($email_subject);
    $email->addTo($email_address);
    $email->addContent("text/html", $email_content);
    $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
    try {
        $sendgrid->send($email);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to generate a token
function generateToken($id, $email): string
{
    return hash('sha256', $id . $email);
}