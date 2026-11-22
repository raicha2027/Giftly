<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';

$baseUrl = '/~fatma.abdourahmane/Giftly';
$error = '';

if (
    empty($_SESSION['gift_finder_csrf']) ||
    !is_string($_SESSION['gift_finder_csrf'])
) {
    $_SESSION['gift_finder_csrf'] = bin2hex(random_bytes(32));
}

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function giftlyText($value)
{
    return strtolower(trim((string) ($value ?? '')));
}

function giftlyMatches($productValue, $preference)
{
    $productValue = giftlyText($productValue);
    $preference = giftlyText($preference);

    if ($productValue === '' || $preference === '') {
        return false;
    }

    return strpos($productValue, $preference) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['gift_finder_csrf'], $submittedToken)
    ) {
        $error = 'Your session has expired. Please refresh the page and try again.';

    } else {

        $preferences = [
            'relationship' => trim((string) ($_POST['relationship'] ?? '')),
            'recipient_gender' => trim((string) ($_POST['recipient_gender'] ?? '')),
            'occasion' => trim((string) ($_POST['occasion'] ?? '')),
            'interest' => trim((string) ($_POST['interest'] ?? '')),
            'budget' => trim((string) ($_POST['budget'] ?? '')),
            'recipient_age' => trim((string) ($_POST['recipient_age'] ?? '')),
            'personality' => trim((string) ($_POST['personality'] ?? '')),
            'style' => trim((string) ($_POST['style'] ?? '')),
            'gift_type' => trim((string) ($_POST['gift_type'] ?? '')),
            'extra_notes' => trim((string) ($_POST['extra_notes'] ?? ''))
        ];

        if (
            $preferences['relationship'] === '' ||
            $preferences['occasion'] === '' ||
            $preferences['interest'] === '' ||
            !is_numeric($preferences['budget']) ||
            (float) $preferences['budget'] <= 0
        ) {
            $error = 'Please complete the required fields and enter a valid budget.';

        } else {

            try {

                $sql = "
                    SELECT
                        product_id,
                        product_name,
                        description,
                        price,
                        image_path,
                        recipient_gender,
                        interests,
                        relationship,
                        recipient_age,
                        personality,
                        style,
                        gift_type,
                        occasions
                    FROM giftly_products
                    WHERE is_active = 1
                      AND price > 0
                      AND price <= ?
                    ORDER BY product_id DESC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([(float) $preferences['budget']]);

                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $recommendations = [];

                foreach ($products as $product) {

                    $productGender = giftlyText($product['recipient_gender'] ?? '');
                    $requestedGender = giftlyText($preferences['recipient_gender']);

                    // Exclude products explicitly intended for another gender.
                    if (
                        $requestedGender !== '' &&
                        $productGender !== '' &&
                        $productGender !== 'unisex' &&
                        $productGender !== $requestedGender
                    ) {
                        continue;
                    }

                    $score = 0;
                    $reasons = [];

                    if (giftlyMatches($product['interests'] ?? '', $preferences['interest'])) {
                        $score += 5;
                        $reasons[] = 'Matches their interests';
                    }

                    if (giftlyMatches($product['occasions'] ?? '', $preferences['occasion'])) {
                        $score += 4;
                        $reasons[] = 'Suitable for the occasion';
                    }

                    if (giftlyMatches($product['relationship'] ?? '', $preferences['relationship'])) {
                        $score += 3;
                        $reasons[] = 'Matches your recipient';
                    }

                    if (giftlyMatches($product['recipient_age'] ?? '', $preferences['recipient_age'])) {
                        $score += 2;
                        $reasons[] = 'Matches their age range';
                    }

                    if (giftlyMatches($product['personality'] ?? '', $preferences['personality'])) {
                        $score += 2;
                        $reasons[] = 'Matches their personality';
                    }

                    if (giftlyMatches($product['style'] ?? '', $preferences['style'])) {
                        $score += 2;
                        $reasons[] = 'Matches their style';
                    }

                    if (giftlyMatches($product['gift_type'] ?? '', $preferences['gift_type'])) {
                        $score += 2;
                        $reasons[] = 'Matches your gift preference';
                    }

                    if (empty($reasons)) {
                        $reasons[] = 'A gift within your budget';
                    }

                    $product['score'] = $score;
                    $product['match_reason'] = implode('. ', $reasons) . '.';

                    $product['recommendation_type'] = $score >= 5
                        ? 'Best match'
                        : 'Within your budget';

                    $recommendations[] = $product;
                }

                // Highest matching scores appear first.
                usort($recommendations, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });

                // Display at most six recommendations.
                $recommendations = array_slice($recommendations, 0, 6);

                $_SESSION['giftly_preferences'] = $preferences;

                $_SESSION['giftly_results'] = [
                    'success' => true,
                    'message' => empty($recommendations)
                        ? 'We could not find suitable products within your budget. Try increasing your budget or changing your preferences.'
                        : 'Here are gift ideas selected from your preferences.',
                    'recommendations' => $recommendations
                ];

                header('Location: ' . $baseUrl . '/gift_agent/gift_results.php');
                exit;

            } catch (PDOException $e) {

                error_log('Gift Finder database error: ' . $e->getMessage());

                $error = 'We could not load gift recommendations. Please try again later.';
            }
        }
    }
}

?>



<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gift Finder | Giftly</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/gift-finder.css">

</head>

<body>

    <header class="gf-header">

        <a href="<?= $baseUrl ?>/pages/home/home.php" class="gf-logo">

            <img src="<?= $baseUrl ?>/assets/Logo.png" alt="Giftly">

            <span>Giftly</span>

        </a>

        <nav>

            <a href="<?= $baseUrl ?>/pages/home/home.php">
                Home
            </a>

            <a href="<?= $baseUrl ?>/gift_agent/gift_finder.php" class="active">
                Gift Finder
            </a>

        </nav>

    </header>

    <main class="gf-page">

        <section class="gf-hero">

            <div class="hero-text">

                <span class="eyebrow">YOUR PERSONAL GIFT ASSISTANT</span>

                <h1>Let's find a gift<br>that feels <span>just right.</span></h1>

            </div>

            <div class="robot-scene">

                <div class="robot-glow"></div>

                <div class="robot">

                    <div class="robot-antenna">
                        <span></span>
                    </div>

                    <div class="robot-head">

                        <div class="robot-ear left"></div>
                        <div class="robot-ear right"></div>

                        <div class="robot-face">

                            <div class="robot-eyes">
                                <span></span>
                                <span></span>
                            </div>

                            <div class="robot-smile"></div>

                        </div>

                        <div class="robot-cheek left"></div>
                        <div class="robot-cheek right"></div>

                    </div>

                    <div class="robot-neck"></div>

                    <div class="robot-body">
                        <div class="robot-heart">♥</div>
                        <div class="robot-body-line"></div>
                    </div>

                    <div class="robot-arm left"></div>
                    <div class="robot-arm right"></div>

                </div>

                <div class="robot-message">
                    <span>Hi there!</span>
                    I'm Giftly Bot. Let's find something special!
                </div>

                <span class="floating-star star-one">✦</span>
                <span class="floating-star star-two">✧</span>
                <span class="floating-star star-three">♥</span>

            </div>

        </section>

        <section class="gf-form-section">

            <div class="form-heading">

                <span class="eyebrow">LET'S GET TO KNOW THEM</span>

                <h2>Who are we shopping for?</h2>

                <p>A few details will help us find a gift they may love.</p>

            </div>

            <?php if ($error !== ''): ?>

                <div class="gf-error">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="<?= $baseUrl ?>/gift_agent/gift_finder.php" id="giftFinderForm">

                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['gift_finder_csrf']) ?>">

                <div class="form-grid">

                    <div class="form-field">

                        <label for="relationship">
                            Who is this gift for? <span>*</span>
                        </label>

                        <select name="relationship" id="relationship" required>
                            <option value="">Choose a relationship</option>
                            <option>Friend</option>
                            <option>Mother</option>
                            <option>Father</option>
                            <option>Partner</option>
                            <option>Sibling</option>
                            <option>Colleague</option>
                            <option>Other</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="recipient_gender">
                            Who are you buying the gift for?
                        </label>

                        <select name="recipient_gender" id="recipient_gender">
                            <option value="">Choose gender</option>
                            <option value="women">Woman</option>
                            <option value="men">Man</option>
                            <option value="unisex">No preference</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="occasion">
                            What's the occasion? <span>*</span>
                        </label>

                        <select name="occasion" id="occasion" required>
                            <option value="">Choose an occasion</option>
                            <option>Birthday</option>
                            <option>Anniversary</option>
                            <option>Graduation</option>
                            <option>Thank You</option>
                            <option>Just Because</option>
                            <option>Other</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="interest">
                            What are they interested in? <span>*</span>
                        </label>

                        <select name="interest" id="interest" required>
                            <option value="">Choose an interest</option>
                            <option>Fashion</option>
                            <option>Beauty</option>
                            <option>Technology</option>
                            <option>Sports</option>
                            <option>Books</option>
                            <option>Food</option>
                            <option>Other</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="recipient_age">
                            What is their age range?
                        </label>

                        <select name="recipient_age" id="recipient_age">
                            <option value="">Prefer not to say</option>
                            <option>Under 18</option>
                            <option>18-24</option>
                            <option>25-34</option>
                            <option>35-44</option>
                            <option>45-54</option>
                            <option>55 and above</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="personality">
                            How would you describe them?
                        </label>

                        <select name="personality" id="personality">
                            <option value="">Choose their personality</option>
                            <option>Creative</option>
                            <option>Adventurous</option>
                            <option>Quiet and thoughtful</option>
                            <option>Outgoing and social</option>
                            <option>Practical</option>
                            <option>Romantic</option>
                            <option>Simple and calm</option>
                        </select>

                    </div>

                    <div class="form-field">

                        <label for="style">What is their style?</label>

                        <select name="style" id="style">
                            <option value="">Choose a style</option>
                            <option>Elegant</option>
                            <option>Minimalist</option>
                            <option>Trendy</option>
                            <option>Classic</option>
                            <option>Colorful</option>
                            <option>Traditional</option>
                            <option>Not sure</option>
                        </select>

                    </div>

                    <div class="form-field full-width">

                        <label for="gift_type">
                            What kind of gift would you prefer?
                        </label>

                        <select name="gift_type" id="gift_type">
                            <option value="">No particular preference</option>
                            <option>Something useful</option>
                            <option>Something personal</option>
                            <option>Something beautiful</option>
                            <option>A surprise</option>
                            <option>An experience</option>
                        </select>

                    </div>

                    <div class="form-field full-width budget-field">

                        <label for="budget">
                            What's your budget? <span>*</span>
                        </label>

                        <div class="budget-input">

                            <span>GHS</span>

                            <input type="number" name="budget" id="budget" min="1" step="0.01"
                                placeholder="Enter your budget" required>

                        </div>

                        <small>
                            Enter any amount you are comfortable spending.
                        </small>

                    </div>

                    <div class="form-field full-width">

                        <label for="extra_notes">
                            Anything else we should know?
                        </label>

                        <textarea name="extra_notes" id="extra_notes" rows="4"
                            placeholder="For example: They love handmade things, enjoy reading, or already own many accessories..."></textarea>

                    </div>

                </div>

                <div class="form-bottom">

                    <p><span>*</span> Required fields</p>

                    <button type="submit" id="findGiftButton">
                        <span>Find My Gift</span>
                        <span class="button-arrow">→</span>
                    </button>

                </div>

            </form>

        </section>

    </main>

    <footer class="gf-footer">
        <p>Made by Giftly. A little thought goes a long way.</p>
    </footer>

    <script src="<?= $baseUrl ?>/assets/js/gift-finder.js"></script>

</body>

</html>