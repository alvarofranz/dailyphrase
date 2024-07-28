<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/functions.php';

use Orhanerday\OpenAi\OpenAi;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$open_ai = new OpenAi($_ENV['OPENAI_API_KEY']);

// Establish a database connection
try {
    $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get next phrase where imaged = 0
$statement = $pdo->prepare("SELECT * FROM phrases WHERE imaged = 0 ORDER BY id ASC LIMIT 1");
$statement->execute();
$phrase = $statement->fetch(PDO::FETCH_ASSOC);

if ($phrase) {
    // Generate an image based on the phrase
    $json_response = $open_ai->image([
        "model" => "dall-e-3",
        "style" => "vivid",
        "quality" => "hd",
        "prompt" => 'Creative, funny, fantastic, without text, representing this topic:' . $phrase['phrase'],
        "n" => 1,
        "size" => "1024x1024",
        "response_format" => "url",
    ]);

    // Decode the JSON response to an associative array
    $response = json_decode($json_response, true);

    if (isset($response['data'][0]['url'])) {
        $image = file_get_contents($response['data'][0]['url']);
        file_put_contents(__DIR__ . '/public/images/' . $phrase['date'] . '.jpg', $image);
        // Update the phrase in the database
        $statement = $pdo->prepare("UPDATE phrases SET imaged = 1 WHERE id = :id");
        $statement->execute(['id' => $phrase['id']]);
    }
}
