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

// Arrays of random words to include in the generated phrases to help the AI be more creative
$ingredients_colors = array(
    'red', 'green', 'blue', 'yellow', 'orange', 'pink', 'brown', 'black', 'white', 'grey', 'silver', 'beige', 'turquoise', 'fuchsia', 'ochre'
);
$ingredients_adjectives = array(
    'spicy', 'sweet', 'sour', 'bitter', 'salty', 'mild', 'hot', 'cold', 'crisp', 'dry', 'fresh', 'juicy', 'rich', 'ripe', 'smooth', 'tender', 'tough', 'creamy', 'fragrant', 'smoky', 'buttery', 'flaky', 'spongy', 'chewy', 'succulent', 'hearty', 'piquant', 'silky', 'stale', 'toasty', 'unctuous', 'woody', 'nutty', 'caramelized', 'peppery', 'herbal', 'grassy', 'meaty', 'pungent', 'funky', 'lemony', 'chocolaty', 'bubbly', 'fizzy', 'cool', 'aromatic', 'briny', 'fibrous', 'gelatinous', 'mellow', 'refreshing', 'robust', 'velvety', 'brisk', 'buttery', 'chalky', 'floral', 'glazed', 'glutinous', 'herbaceous', 'malty', 'peppery', 'resinous', 'sappy', 'sharp', 'slick', 'snappy', 'soapy', 'syrupy', 'waxy', 'zesty', 'delicate', 'elegant', 'fiery', 'fluffy', 'hearty', 'intense', 'lingering', 'mature', 'peppery', 'refined', 'robust', 'rustic', 'seasoned', 'sizzling', 'sophisticated', 'succulent', 'tempting', 'vibrant', 'yummy'
);
$ingredients_nouns = array(
    'apple', 'banana', 'car', 'laptop', 'book', 'table', 'chair', 'bicycle', 'camera', 'backpack', 'pencil', 'umbrella', 'wallet', 'clock', 'phone', 'ball', 'shoe', 'hat', 'key', 'lamp', 'sofa', 'cup', 'bottle', 'pizza', 'burger', 'pasta', 'sushi', 'steak', 'salad', 'cookie', 'ice-cream', 'bread', 'cheese', 'ham', 'butter', 'yogurt', 'honey', 'jam', 'pancake', 'waffle', 'city', 'village', 'town', 'beach', 'mountain', 'river', 'forest', 'desert', 'island', 'cave', 'bridge', 'castle', 'temple', 'museum', 'park', 'school', 'library', 'university', 'hospital', 'stadium', 'cinema', 'restaurant', 'cafe', 'bar', 'club', 'gym', 'hotel', 'office', 'factory', 'market', 'train', 'bus', 'plane', 'boat', 'ship', 'helicopter', 'motorcycle', 'scooter', 'subway', 'tram', 'airport', 'station', 'port', 'harbor', 'garage', 'workshop', 'farm', 'field', 'garden', 'forest', 'flower', 'tree', 'bush', 'grass', 'leaf', 'branch', 'root', 'seed', 'fruit', 'vegetable', 'tool', 'hammer', 'wrench', 'screwdriver', 'nail', 'saw', 'drill', 'level', 'measuring', 'tape', 'paint', 'brush', 'roller', 'can', 'bucket', 'hose', 'sprinkler', 'fountain', 'pool', 'lake', 'ocean', 'sea', 'pond', 'stream', 'waterfall', 'spring', 'geyser', 'volcano', 'cliff', 'canyon', 'boulder', 'rock', 'pebble', 'stone', 'sand', 'dirt', 'mud', 'clay', 'gravel', 'dust', 'cloud', 'rain', 'snow', 'hail', 'storm', 'wind', 'breeze', 'thunder', 'lightning', 'sun', 'moon', 'star', 'planet', 'comet', 'asteroid', 'meteor', 'galaxy', 'universe', 'space'
);
$ingredients_verbs = array(
    'run', 'jump', 'swim', 'fly', 'sing', 'read', 'write', 'eat', 'drink', 'sleep', 'wake', 'walk', 'drive', 'ride', 'cook', 'bake', 'fry', 'boil', 'grill', 'mix', 'blend', 'chop', 'slice', 'dice', 'stir', 'whisk', 'knead', 'measure', 'pour', 'taste', 'smell', 'touch', 'see', 'hear', 'feel', 'think', 'believe', 'know', 'understand', 'learn', 'teach', 'study', 'watch', 'listen', 'talk', 'speak', 'say', 'shout', 'whisper', 'laugh', 'cry', 'smile', 'frown', 'hug', 'kiss', 'shake', 'wave', 'nod', 'clap', 'run', 'jump', 'swim', 'fly', 'sing', 'read', 'write', 'eat', 'drink', 'build', 'create', 'design', 'draw', 'paint', 'sculpt', 'carve', 'mold', 'assemble', 'construct', 'drive', 'ride', 'walk', 'hike', 'bike', 'climb', 'descend', 'navigate', 'explore', 'travel', 'play', 'game', 'compete', 'win', 'lose', 'practice', 'train', 'exercise', 'workout', 'stretch', 'think', 'ponder', 'consider', 'reflect', 'meditate', 'contemplate', 'analyze', 'solve', 'compute', 'calculate', 'love', 'hate', 'like', 'dislike', 'enjoy', 'prefer', 'adore', 'cherish', 'treasure'
);
$ingredients_adverbs = array(
    'quickly', 'slowly', 'carefully', 'happily', 'sadly', 'angrily', 'eagerly', 'gracefully', 'lazily', 'quietly', 'loudly', 'smoothly', 'awkwardly', 'calmly', 'cheerfully', 'briskly', 'gently', 'intensely', 'softly', 'swiftly', 'tenderly', 'warmly', 'wildly', 'boldly', 'courageously', 'frankly', 'genuinely', 'gratefully', 'honestly', 'jovially', 'kindly', 'lightly', 'meekly', 'nervously', 'openly', 'politely', 'proudly', 'rudely', 'seriously', 'sternly', 'vividly', 'vaguely', 'vibrantly', 'zealously', 'carelessly', 'deliberately', 'elegantly', 'foolishly', 'innocently', 'recklessly'
);
$ingredients_emotions = array(
    'happy', 'sad', 'angry', 'excited', 'nervous', 'anxious', 'relaxed', 'content', 'joyful', 'frustrated', 'bored', 'curious', 'confused', 'scared', 'hopeful', 'lonely', 'proud', 'guilty', 'ashamed', 'determined', 'surprised', 'grateful', 'jealous', 'disappointed', 'embarrassed', 'enthusiastic', 'fearful', 'furious', 'glad', 'miserable', 'optimistic', 'pessimistic', 'relieved', 'resentful', 'shocked', 'stressed', 'sympathetic', 'tired', 'trustful', 'unhappy', 'upset', 'worried', 'zestful', 'passionate', 'melancholic', 'nostalgic', 'satisfied', 'serene', 'pensive'
);
$ingredients_professions = array(
    'doctor', 'engineer', 'teacher', 'artist', 'writer', 'chef', 'nurse', 'lawyer', 'scientist', 'pilot', 'mechanic', 'plumber', 'electrician', 'architect', 'dentist', 'pharmacist', 'firefighter', 'police', 'soldier', 'accountant', 'actor', 'musician', 'designer', 'journalist', 'photographer', 'veterinarian', 'barber', 'tailor', 'carpenter', 'coach', 'manager', 'salesperson', 'consultant', 'developer', 'analyst', 'researcher', 'professor', 'receptionist', 'librarian', 'farmer', 'gardener', 'translator', 'interpreter', 'guide', 'broker', 'dispatcher', 'guard', 'technician', 'therapist', 'counselor'
);

// Get the random types to include
$random_types = get_random_types();

// Initialize the result array
$result = array();

foreach ($random_types as $type) {
    switch ($type) {
        case 'color':
            $result[] = get_random_item($ingredients_colors);
            break;
        case 'adjective':
            $result[] = get_random_item($ingredients_adjectives);
            break;
        case 'noun':
            $result[] = get_random_item($ingredients_nouns);
            break;
        case 'verb':
            $result[] = get_random_item($ingredients_verbs);
            break;
        case 'adverb':
            $result[] = get_random_item($ingredients_adverbs);
            break;
        case 'emotion':
            $result[] = get_random_item($ingredients_emotions);
            break;
        case 'profession':
            $result[] = get_random_item($ingredients_professions);
            break;
    }
}

// Shuffle the result to randomize the order
shuffle($result);

// Convert the result array to a string
$random_ingredients = implode(' ', $result);

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
                "content" => "Give me the SQL query for today, noting that the original phrase should be around 20 words long more or less: " . $phrase_date
            ],
            [
                "role" => "assistant",
                "content" => "INSERT INTO `phrases` (`date`, `phrase`, `spanish`, `german`, `italian`, `french`, `portuguese`, `norwegian`) VALUES ('2024-07-27', 'The future belongs to those who believe in the beauty of their dreams', 'El futuro pertenece a aquellos que creen en la belleza de sus sueños', 'Die Zukunft gehört denen, die an die Schönheit ihrer Träume glauben', 'Il futuro appartiene a coloro che credono nella bellezza dei loro sogni', 'L\'avenir appartient à ceux qui croient en la beauté de leurs rêves', 'O futuro pertence àqueles que acreditam na beleza de seus sonhos', 'Framtiden tilhører de som tror på skjønnheten i drømmene sine')"
            ],
            [
                "role" => "user",
                "content" => "Good, you only returned the SQL, nothing else. That is what I wanted. You also used the correct date that I provided and escaped the values properly. But the phrase is too philosophical. I need something creative talking about random stuff (maybe include the words " . $random_ingredients . "), just make sure to be random, because every day it has to be unique (the original phrase should be around 20 words long more or less), and pay attention to the translations, they have to be perfect, I don't like mediocre translations. And escaping values for SQL is very important too to avoid SQL injection. Things you need to pay attention to: just return the SQL (with properly escaped values), the original phrase with around 20 words, be very creative and random, it does not have to make sense, but use proper grammar and perfect translations. Now, give me the SQL query for today: " . $phrase_date
            ],
        ],
        'temperature' => 1,
        'max_tokens' => 4096,
        'frequency_penalty' => 0.25,
        'presence_penalty' => 0.25,
    ]);

    // Decode response
    $d = json_decode($chat);
    $ai_generated_sql = $d->choices[0]->message->content;

    try {
        // Execute the SQL query
        $stmt = $pdo->prepare($ai_generated_sql);
        $stmt->execute();

        // Get the last inserted ID
        $generated_phrase_id = $pdo->lastInsertId();

        // Prepare the email content
        $emailContent = "<pre>" . $ai_generated_sql . "</pre>";
        $emailContent .= "
        <p>Helper ingredients: " . $random_ingredients . "</p>
        <p>Generated Phrase ID: " . $generated_phrase_id . "</p>";

        if ($_ENV['CURRENT_ENV'] === 'production') {
            // Send email to the admin with the generated phrase and ID
            send_email($_ENV['ADMIN_EMAIL'], 'A daily phrase was generated for ' . $phrase_date, $emailContent);
        } else {
            echo $emailContent;
        }
    } catch (PDOException $e) {
        if ($_ENV['CURRENT_ENV'] === 'production') {
            // Send email to the admin with the MySQL error
            send_email($_ENV['ADMIN_EMAIL'], 'Error generating daily phrase for ' . $phrase_date, '<pre>' . $ai_generated_sql . ' -- ' . $e->getMessage() . '</pre>');
        } else {
            echo '<pre>' . $ai_generated_sql . ' -- ' . $e->getMessage() . '</pre>';
        }
    }

} catch (Exception $e) {
    echo 'Error: ', $e->getMessage();
}

// Helper function to get a random item from an array
function get_random_item($array)
{
    return $array[array_rand($array)];
}

// Helper function to randomly decide which types to include
function get_random_types()
{
    $types = ['color', 'adjective', 'noun', 'verb', 'adverb', 'emotion', 'profession'];
    shuffle($types);
    return array_slice($types, 0, 3);
}