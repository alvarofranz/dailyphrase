<?php
require '../vendor/autoload.php';
require '../includes/functions.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Establish a database connection
try {
    $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// View
$view = 'default_view';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) && isset($_POST['g-recaptcha-response']) && is_recaptcha_token_verification_successful($_POST['g-recaptcha-response'])) {
    $email = $_POST['email'];
    $languages = ['spanish', 'german', 'italian', 'french', 'portuguese', 'norwegian'];
    $last_sent = date('Y-m-d', strtotime('-1 day'));

    // Prepare your statement
    $insertOrUpdateStmt = $pdo->prepare("
    INSERT INTO subscribers (email, last_sent, spanish, german, italian, french, portuguese, norwegian)
    VALUES (:email, :last_sent, :spanish, :german, :italian, :french, :portuguese, :norwegian)
    ON DUPLICATE KEY UPDATE
        last_sent = VALUES(last_sent),
        spanish = VALUES(spanish),
        german = VALUES(german),
        italian = VALUES(italian),
        french = VALUES(french),
        portuguese = VALUES(portuguese),
        norwegian = VALUES(norwegian)
    ");

    // Define your parameters
    $params = [
        ':email' => $email,
        ':last_sent' => $last_sent,
        ':spanish' => isset($_POST['spanish']) ? 1 : 0,
        ':german' => isset($_POST['german']) ? 1 : 0,
        ':italian' => isset($_POST['italian']) ? 1 : 0,
        ':french' => isset($_POST['french']) ? 1 : 0,
        ':portuguese' => isset($_POST['portuguese']) ? 1 : 0,
        ':norwegian' => isset($_POST['norwegian']) ? 1 : 0,
    ];

    // Execute the statement
    if ($insertOrUpdateStmt->execute($params)) {
        // Check if the record was inserted or updated
        // Use a SELECT query to retrieve the ID based on the unique key (email)
        $selectStmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = :email");
        $selectStmt->execute([':email' => $email]);

        // Fetch the ID
        $subscriber = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $subscriber_id = $subscriber ? $subscriber['id'] : null;

        $token = generateToken($subscriber_id, $email);
        $verification_link = $_ENV['SITE_URL'] . '/?email=' . urlencode($email) . '&token=' . urlencode($token) . '&action=verify';

        $welcome_email = '<h1>Welcome to Daily Phrase!</h1>
        <p>You will receive a daily phrase in the languages you selected.</p>
        <p>Please click the link below to verify your email address and start receiving your daily phrases:</p>
        <p><a href="' . $verification_link . '">' . $verification_link . '</a></p><hr><p>dailyphrase.email - Thanks!</p>';

        // Send verification email (Use your own mail function or mail library)
        send_email($email, "Verify your email", $welcome_email);

        $view = 'sent_link';
    } else {
        $view = 'error';
    }
}

// Handle email verification
if (isset($_GET['email']) && isset($_GET['token']) && isset($_GET['action'])) {
    $view = 'verification_completed';

    $email = urldecode($_GET['email']);
    $token = urldecode($_GET['token']);

    $stmt = $pdo->prepare("SELECT id, verified FROM subscribers WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subscriber) {
        $expected_token = generateToken($subscriber['id'], $email);

        if ($_GET['action'] == 'verify') {
            if (!$subscriber['verified']) {
                if ($token === $expected_token) {
                    $update_stmt = $pdo->prepare("UPDATE subscribers SET verified = 1 WHERE id = :id");
                    $update_stmt->execute([':id' => $subscriber['id']]);
                }
            }
        }

        if ($_GET['action'] == 'unsubscribe') {
            if ($subscriber['verified']) {
                if ($token === $expected_token) {
                    $update_stmt = $pdo->prepare("UPDATE subscribers SET verified = 0 WHERE id = :id");
                    $update_stmt->execute([':id' => $subscriber['id']]);
                }
            }
            $view = 'unsubscribed';
        }

    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Phrase via E-mail</title>
    <meta property="og:title" content="Daily Phrase Service">
    <meta property="og:description"
          content="Subscribe to receive daily phrases in various languages to boost your language skills. Simple, free, and effective learning experience.">
    <meta property="og:image" content="https://dailyphrase.email/languages-daily-phrase.jpg">
    <meta property="og:url" content="https://dailyphrase.email">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Daily Phrase">
    <link rel="icon" href="https://dailyphrase.email/icon.png" type="image/png">
    <meta name="theme-color" content="#ffffff">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        main {
            background: #ffffff;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            box-sizing: border-box;
        }

        h1 {
            font-size: 2rem;
            margin-top: 0;
            color: #333;
        }

        p {
            font-size: 1rem;
            color: #666;
            line-height: 1.5;
        }

        form {
            margin-top: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        input[type="email"] {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        button {
            background-color: #007bff;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        button:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(38, 143, 255, 0.5);
        }

        footer {
            position: fixed;
            bottom: 0;
            right: 0;
            padding: 1rem;
            font-size: 12px;
        }

        footer a {
            color: #777;
            text-decoration: none;
        }
    </style>
    <script src="https://www.google.com/recaptcha/api.js"></script>
</head>
<body>
<main>
    <?php
    switch ($view) {
        case 'sent_link':
            echo '<h1>Verification link sent</h1>
        <p>Go to your email and find the verification link. It may be in the spam folder, who knows?</p>';
            break;
        case 'verification_completed':
            echo '<h1>Email verified</h1>
        <p>Your email has been added to the list and you will receive your daily dose of language practice.</p>
        <p>
        <a href="https://wa.me/?text=Look%20at%20this%20cool%20service%20that%20sends%20you%20a%20phrase%20in%20different%20languages%20every%20day!%0A%0Ahttps%3A%2F%2Fdailyphrase.email" target="_blank" rel="noopener noreferrer">
            <img src="/whatsapp-share-button-icon.webp" alt="Share on WhatsApp">
        </a>
        </p>';
            break;
        case 'unsubscribed':
            echo '<h1>Unsubscribed</h1>
        <p>Your email has been removed from the list. You will no longer receive daily phrases.</p>';
            break;
        case 'error':
            echo '<h1>Error</h1>
        <p>There was an error processing your request. Please <a href="/">try again</a>.</p>';
            break;
        default:
            ?>
            <h1>Receive a daily phrase in multiple languages</h1>
            <p>It will take you 30 seconds each day to read the phrases and it will help you get new vocabulary as well
                as
                stay in touch with the languages you love. Simple, free.</p>
            <p>Write your email below, pick your languages, and start receiving your daily dose of language practice for
                free:</p>
            <form method="post" action="">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                <br>
                <label><input type="checkbox" name="spanish"> Spanish</label>
                <label><input type="checkbox" name="german"> German</label>
                <label><input type="checkbox" name="italian"> Italian</label>
                <label><input type="checkbox" name="french"> French</label>
                <label><input type="checkbox" name="portuguese"> Portuguese</label>
                <label><input type="checkbox" name="norwegian"> Norwegian</label>
                <br>
                <div class="g-recaptcha" data-sitekey="<?php echo $_ENV['RECAPTCHA_SITE_KEY']; ?>"></div>
                <br>
                <button type="submit">Subscribe</button>
            </form>
        <?php
    }
    ?>
</main>
<footer>
    dailyphrase.email - <a href="/privacy.txt">Privacy Policy</a>
</footer>
</body>
</html>
