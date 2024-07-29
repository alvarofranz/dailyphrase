<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/functions.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Establish a database connection
try {
    $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch today's date
$today = date('Y-m-d');

// Fetch today's phrase
$stmt = $pdo->prepare("SELECT * FROM phrases WHERE date = :today LIMIT 1");
$stmt->execute([':today' => $today]);
$phrase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$phrase) {
    exit;
}

// Fetch up to 20 verified subscribers whose last_sent is less than today
$stmt = $pdo->prepare("SELECT * FROM subscribers WHERE verified = 1 AND last_sent < :today LIMIT 20");
$stmt->execute([':today' => $today]);
$subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no subscribers are found, stop the script
if (!$subscribers) {
    exit;
}

foreach ($subscribers as $subscriber) {
    $email = $subscriber['email'];
    $message = "<h1 style='color: #004574;'>Today's Phrase</h1>

    <p style='font-size:16px;padding:15px;background-color:#004574;color:#FFF;border-radius:8px;'>" . htmlspecialchars($phrase['phrase']) . "</p><hr>";

    // Add translations based on subscriber's preferences
    if ($subscriber['spanish']) {
        $message .= "<p><strong>Spanish:</strong> " . htmlspecialchars($phrase['spanish']) . "</p>";
    }
    if ($subscriber['german']) {
        $message .= "<p><strong>German:</strong> " . htmlspecialchars($phrase['german']) . "</p>";
    }
    if ($subscriber['italian']) {
        $message .= "<p><strong>Italian:</strong> " . htmlspecialchars($phrase['italian']) . "</p>";
    }
    if ($subscriber['french']) {
        $message .= "<p><strong>French:</strong> " . htmlspecialchars($phrase['french']) . "</p>";
    }
    if ($subscriber['portuguese']) {
        $message .= "<p><strong>Portuguese:</strong> " . htmlspecialchars($phrase['portuguese']) . "</p>";
    }
    if ($subscriber['norwegian']) {
        $message .= "<p><strong>Norwegian:</strong> " . htmlspecialchars($phrase['norwegian']) . "</p>";
    }

    $message .= "<hr><p><i>Don't just ignore this. Take your time to learn the new vocabulary, a small step a day makes wonders!</i></p>";

    // Add image if exists
    $image_path = __DIR__ . '/public/images/' . $phrase['date'] . '.jpg';
    if (file_exists($image_path)) {
        $message .= "
        <img src='" . $_ENV['SITE_URL'] . '/images/' . $phrase['date'] . '.jpg' . "' alt='Image' style='width:500px;max-width:100%;height:auto;border-radius:8px;'>";
    }

    // Generate the unsubscribe link
    $unsubscribe_token = generateToken($subscriber['id'], $email);
    $unsubscribe_link = $_ENV['SITE_URL'] . '/?email=' . urlencode($email) . '&token=' . urlencode($unsubscribe_token) . '&action=unsubscribe';

    $message .= "<hr>
    <p>dailyphrase.email | <i>" . $today . "</i></p>
    <p style='margin-top:30px;font-size:11px;color:#555;'>30 N Gould St Ste N, Sheridan, WY 82801 - <a href='" . $unsubscribe_link . "' title='Unsubscribe from Daily Phrase'>Unsubscribe</a></p>";

    // Send the email (Use your own mail function or mail library)
    $subject = "Daily Phrase: " . $phrase['phrase'];
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    send_email($email, $encoded_subject, $message);

    // Update the subscriber's last_sent date to today
    $update_stmt = $pdo->prepare("UPDATE subscribers SET last_sent = :today WHERE id = :id");
    $update_stmt->execute([':today' => $today, ':id' => $subscriber['id']]);
}
