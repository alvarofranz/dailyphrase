<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/functions.php';

use Orhanerday\OpenAi\OpenAi;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$open_ai = new OpenAi($_ENV['OPENAI_API_KEY']);

// Establish a database connection
try {
    $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Prepare the SQL statement to get the most recent date from the phrases table
$stmt = $pdo->prepare("SELECT MAX(date) as last_date FROM phrases");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result && $result['last_date']) {
    // If a date is found, increment it by one day to get the next date
    $phrase_date = date('Y-m-d', strtotime($result['last_date'] . ' +1 day'));
} else {
    // If the table is empty, use tomorrow's date
    $phrase_date = date('Y-m-d', strtotime('+1 day'));
}

try {
    $chat = $open_ai->chat([
        'model' => 'gpt-4o',
        'messages' => [
            [
                "role" => "system",
                "content" => "I created an app that sends a daily phrase in multiple languages to practice. Your task is to populate the MySQL database with phrases and translations each day. So your output will be an SQL query for a single insert like the following, nothing more, nothing less. Make sure to escape the values properly for the insert so it does not fail. This is automated and everything depends on your output being correct: INSERT INTO `phrases` (`date`, `phrase`, `spanish`, `german`, `italian`, `french`, `portuguese`, `norwegian`) VALUES ('" . $phrase_date . "', 'Hello', 'Hola', 'Hallo', 'Ciao', 'Bonjour', 'Olá', 'Hallo')"
            ],
            [
                "role" => "user",
                "content" => "Give me the SQL query for today: " . $phrase_date
            ],
            [
                "role" => "assistant",
                "content" => "INSERT INTO `phrases` (`date`, `phrase`, `spanish`, `german`, `italian`, `french`, `portuguese`, `norwegian`) VALUES ('2024-07-27', 'The future belongs to those who believe in the beauty of their dreams', 'El futuro pertenece a aquellos que creen en la belleza de sus sueños', 'Die Zukunft gehört denen, die an die Schönheit ihrer Träume glauben', 'Il futuro appartiene a coloro che credono nella bellezza dei loro sogni', 'L\'avenir appartient à ceux qui croient en la beauté de leurs rêves', 'O futuro pertence àqueles que acreditam na beleza de seus sonhos', 'Framtiden tilhører de som tror på skjønnheten i drømmene sine')"
            ],
            [
                "role" => "user",
                "content" => "Good, you only returned the SQL, nothing else. That is what I wanted. You also used the correct date that I provided and escaped the values properly. But the phrase is too philosophical. I need something creative talking about random stuff, make sure to be random, because every day it has to be unique, and pay attention to the translations, they have to be perfect, I don't like mediocre translations. And escaping values for SQL is very important too to avoid SQL injection."
            ],
        ],
        'temperature' => 1,
        'max_tokens' => 10000,
        'frequency_penalty' => 0,
        'presence_penalty' => 0,
    ]);

    // decode response
    $d = json_decode($chat);

    try {
        // Execute the SQL query
        $stmt = $pdo->prepare($d->choices[0]->message->content);
        $stmt->execute();

        // Get the last inserted ID
        $generatedPhraseId = $pdo->lastInsertId();

        // Prepare the email content
        $emailContent = "<pre>" . $d->choices[0]->message->content . "</pre>";
        $emailContent .= "<p>Generated Phrase ID: " . $generatedPhraseId . "</p>";

        // Send email to the admin with the generated phrase and ID
        send_email($_ENV['ADMIN_EMAIL'], 'A daily phrase was generated for ' . $phrase_date, $emailContent);
    } catch (PDOException $e) {
        // Send email to the admin with the MySQL error
        send_email($_ENV['ADMIN_EMAIL'], 'Error generating daily phrase for ' . $phrase_date, '<pre>' . $e->getMessage() . '</pre>');
    }

} catch (Exception $e) {
    echo 'Error: ', $e->getMessage();
}


