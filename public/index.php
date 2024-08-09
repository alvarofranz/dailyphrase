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

if (isset($_GET['phrase'])) {
    // Check if it is a valid Y-m-d date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['phrase'])) {
        header('Location: /');
        exit;
    }
    // Check if date is less than or equal to today
    if ($_GET['phrase'] > date('Y-m-d')) {
        header('Location: /');
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM phrases WHERE date = :phrase");
    $stmt->execute([':phrase' => $_GET['phrase']]);
    $view_phrase = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($view_phrase) {
        $view = 'view_phrase';
    }
}

// Handle form submission
if ($view === 'default_view' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) && isset($_POST['g-recaptcha-response']) && is_recaptcha_token_verification_successful($_POST['g-recaptcha-response'])) {
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
        <p>Please confirm your email clicking the link below, in order to start receiving your daily phrases:</p>
        <p><a href="' . $verification_link . '" style="background-color:#1b74e4;text-decoration:none;display:block;border-radius:5px;font-size: 16px;"><span style="font-family:Helvetica,sans-serif;white-space:nowrap;font-weight:bold;vertical-align:middle;color:white;line-height:16px;">Confirm&nbsp;now</span></a></p><p>You will enjoy the daily phrases :)</p><hr>
        <p style="margin-top:30px;font-size:11px;color:#555;">30 N Gould St Ste N, Sheridan, WY 82801</p>';

        // Send verification email (Use your own mail function or mail library)
        send_email($email, "Verify your email to start receiving your Daily Phrase", $welcome_email);

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
    <?php
    $page_og_image = '/assets/languages-daily-phrase.jpg';
    $page_og_title = 'Daily Phrase Service';
    switch ($view) {
        case 'view_phrase':
            if (!isset($view_phrase)) {
                exit;
            }
            $page_title = '- ' . $view_phrase['phrase'];
            $page_og_title = $view_phrase['phrase'];
            $page_og_image = '/images/' . $view_phrase['date'] . '.jpg';
            break;
        case 'sent_link':
            $page_title = '- Verification link sent';
            break;
        case 'verification_completed':
            $page_title = '- Email verified';
            break;
        case 'unsubscribed':
            $page_title = '- Unsubscribed';
            break;
        case 'error':
            $page_title = '- Error';
            break;
        default:
            $page_title = 'via E-mail | Practice your languages!';
            echo '<script src="https://www.google.com/recaptcha/api.js"></script>';
    }
    ?>
    <title>Daily Phrase <?php echo $page_title; ?></title>
    <meta property="og:title" content="<?php echo $page_og_title; ?>">
    <meta property="og:description"
          content="Subscribe to receive daily phrases in various languages to boost your language skills. Simple, free, and effective learning experience.">
    <meta property="og:image" content="<?php echo $page_og_image; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Daily Phrase">
    <link rel="icon" href="/assets/icon.png" type="image/png">
    <link rel="stylesheet" href="/assets/suppastyle.css">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XP2B88NNS7"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-XP2B88NNS7');
        gtag('config', 'AW-16666605491');
    </script>
</head>
<body>
<main>
    <?php
    switch ($view) {
        case 'view_phrase':
            include 'parts/view_phrase.php';
            break;
        case 'sent_link':
            include 'parts/sent_link.php';
            break;
        case 'verification_completed':
            include 'parts/verification_complete.php';
            break;
        case 'unsubscribed':
            include 'parts/unsubscribed.php';
            break;
        case 'error':
            include 'parts/error.php';
            break;
        default:
            include 'parts/welcome.php';
    }
    ?>
</main>
<aside>
    <?php
    // Pick 5 random phrases
    if ($view === 'view_phrase') {
        $stmt = $pdo->prepare("SELECT * FROM phrases WHERE imaged=1 and date<CURDATE() and date!=:date ORDER BY RAND() LIMIT 4");
        $stmt->bindParam(':date', $_GET['phrase']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM phrases WHERE imaged=1 and date<CURDATE() ORDER BY RAND() LIMIT 4");
    }
    $stmt->execute();
    $phrases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Randomize the position and size of the phrases
    $randomization_power = [
        [
            'top:' . rand(3, 6) . '%',
            'right:' . rand(3, 7) . '%',
            'width:' . rand(210, 280) . 'px'
        ],
        [
            'bottom:' . rand(3, 8) . '%',
            'right:' . rand(3, 7) . '%',
            'width:' . rand(205, 260) . 'px'
        ],
        [
            'bottom:' . rand(3, 6) . '%',
            'left:' . rand(3, 7) . '%',
            'width:' . rand(210, 285) . 'px'
        ],
        [
            'top:' . rand(3, 8) . '%',
            'left:' . rand(3, 7) . '%',
            'width:' . rand(206, 275) . 'px'
        ]
    ];

    // Display the phrases
    $i = 0;
    foreach ($phrases as $phrase) {
        $languages = ['phrase', 'spanish', 'german', 'italian', 'french', 'portuguese', 'norwegian'];
        echo '
        <div class="example-phrase" style="' . implode(';', $randomization_power[$i]) . '">
            <a href="/' . $phrase['date'] . '"><figure>
                <img src="/images/' . $phrase['date'] . '.jpg" alt="Day ' . $phrase['phrase'] . '">
                <figcaption><span>' . $phrase[$languages[array_rand($languages)]] . '</span></figcaption>
            </figure></a>
        </div>';
        $i++;
    }
    ?>
</aside>
</body>
</html>
