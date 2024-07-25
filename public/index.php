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

// Function to generate a token
function generateToken($id, $email)
{
    return hash('sha256', $id . $email);
}

// View
$view = 'default_view';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $email = $_POST['email'];
    $languages = ['spanish', 'german', 'italian', 'french', 'portuguese', 'norwegian'];
    $last_sent = date('Y-m-d', strtotime('-1 day'));

    $stmt = $pdo->prepare("INSERT INTO subscribers (email, last_sent, spanish, german, italian, french, portuguese, norwegian) VALUES (:email, :last_sent, :spanish, :german, :italian, :french, :portuguese, :norwegian)");

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

    if ($stmt->execute($params)) {
        $subscriber_id = $pdo->lastInsertId();
        $token = generateToken($subscriber_id, $email);
        $verification_link = $_ENV['SITE_URL'] . '/?email=' . urlencode($email) . '&token=' . urlencode($token);

        // Send verification email (Use your own mail function or mail library)
        send_email($email, "Verify your email", "Click the link to verify your email: $verification_link");

        $view = 'sent_link';
    } else {
        $view = 'error';
    }
}

// Handle email verification
if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = urldecode($_GET['email']);
    $token = urldecode($_GET['token']);

    $stmt = $pdo->prepare("SELECT id, verified FROM subscribers WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subscriber && !$subscriber['verified']) {
        $expected_token = generateToken($subscriber['id'], $email);
        if ($token === $expected_token) {
            $update_stmt = $pdo->prepare("UPDATE subscribers SET verified = 1 WHERE id = :id");
            $update_stmt->execute([':id' => $subscriber['id']]);
        }
    }

    $view = 'verification_completed';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Phrase via E-mail</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
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
        <p>Your email has been added to the list and you will receive your daily dose of language practice.</p>';
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
