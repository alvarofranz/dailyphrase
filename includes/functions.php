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
    return hash('sha256', $_ENV['SUGAR'] . $id . $email);
}

// Function to verify recaptcha token
function is_recaptcha_token_verification_successful($token)
{
    if (empty($token)) {
        return false;
    }
    // Initialize cURL
    $ch = curl_init();
    // Set the options
    curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify?secret=' . $_ENV['RECAPTCHA_SECRET_KEY'] . '&response=' . $token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return the transfer as a string
    curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required to check for HTTP errors
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification, if needed
    // Execute the request
    $recaptcha_response = curl_exec($ch);
    // Check for errors
    if (curl_errno($ch)) {
        return false;
    }
    // Close the cURL resource
    curl_close($ch);
    $recaptcha_response = json_decode($recaptcha_response);
    if ($recaptcha_response->success) {
        return $recaptcha_response;
    }
    return false;
}