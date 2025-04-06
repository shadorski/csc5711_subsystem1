<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/vendor/autoload.php';
session_start();

// Simple logging function
function logDebug($message) {
    error_log(date("Y-m-d H:m:s") . " [DEBUG] " . $message . "\n", 3, __DIR__ . '/debug.log');
}

// Create container and set it for Slim
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('/subsystem1');

// Add DB to container
$container->set('db', require __DIR__ . '/includes/db_connect.php');

// Middleware to check DB connection
$app->add(function (Request $request, RequestHandler $handler) use ($container) {
    $db = $container->get('db');
    if (!$db->isConnected()) {
        logDebug("Database unavailable - showing error page");
        $response = new \Slim\Psr7\Response();
        $message = "Sorry, the system is currently offline due to a database issue. Please try again later.";
        ob_start();
        include __DIR__ . '/error.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withStatus(503)->withHeader('Content-Type', 'text/html');
    }
    return $handler->handle($request);
});

// Middleware for error handling (dev mode)
$app->addErrorMiddleware(true, true, true);

// Home page
$app->get('/', function (Request $request, Response $response) {
    logDebug("Home page accessed");
    ob_start();
    if (isset($_SESSION['user_id'])) {
        include __DIR__ . '/dashboard.php';
    } else {
        include __DIR__ . '/home.php';
    }
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Dashboard (protected route)
$app->get('/dashboard', function (Request $request, Response $response) {
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/login')->withStatus(302);
    }
    ob_start();
    include __DIR__ . '/dashboard.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Signup page (GET)
$app->get('/signup', function (Request $request, Response $response) {
    if (isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
    }
    ob_start();
    include __DIR__ . '/signup.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Signup form submission (POST)
$app->post('/signup', function (Request $request, Response $response) use ($container) {
    $count = 0;
    if (isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
    }
    $data = $request->getParsedBody();
    logDebug("Signup POST data: " . print_r($data, true));

    $username = trim($data['username'] ?? '');
    $first_name = trim($data['first_name'] ?? '');
    $last_name = trim($data['last_name'] ?? '');
    $gender = $data['gender'] ?? '';
    $password = $data['password'] ?? '';

    logDebug("Parsed username: '$username'");

    $errors = [];
    if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username must be 3-50 characters and contain only letters, numbers, or underscores.";
    }
    if (empty($first_name) || strlen($first_name) > 50) {
        $errors[] = "First name is required and must be 50 characters or less.";
    }
    if (empty($last_name) || strlen($last_name) > 50) {
        $errors[] = "Last name is required and must be 50 characters or less.";
    }
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $errors[] = "Invalid gender selection.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (empty($errors)) {
        $db = $container->get('db');
        if (!$db->isConnected()) {
            $errors[] = "Sorry, we’re having trouble connecting to the database. Please try again later.";
        } else {
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $errors[] = "Username already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $create_date = date("Y-m-d H:m:s");
                $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, gender, password, date_created, last_login) VALUES (?, ?, ?, ?, ?, $create_date, $create_date)");
                $stmt->bind_param("sssss", $username, $first_name, $last_name, $gender, $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $conn->insert_id;
                    return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
                } else {
                    $errors[] = "An error occurred during signup. Please try again.";
                }
                $stmt->close();
            }
        }
    }

    ob_start();
    include __DIR__ . '/signup.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// API: Check username
$app->post('/api/user/check_username', function (Request $request, Response $response) use ($container) {
    $count = 0;
    $rawBody = $request->getBody()->getContents();
    logDebug("Raw body: '$rawBody'");
    $data = json_decode($rawBody, true);
    logDebug("Parsed JSON data: " . print_r($data, true));

    $username = $data['username'] ?? '';
    logDebug("Parsed username: '$username'");

    if (empty($username)) {
        $payload = json_encode(['error' => 'Username is required']);
        $response->getBody()->write($payload);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = $container->get('db');
    if (!$db->isConnected()) {
        $payload = json_encode(['error' => 'Database unavailable. Please try again later.']);
        $response->getBody()->write($payload);
        return $response->withStatus(503)->withHeader('Content-Type', 'application/json');
    }

    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    $payload = json_encode(['exists' => $count > 0]);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// Logout
$app->get('/logout', function (Request $request, Response $response) {
    session_destroy();
    return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
});

// 404 Handler
$app->map(['GET', 'POST'], '/{routes:.+}', function (Request $request, Response $response) {
    ob_start();
    include __DIR__ . '/404.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withStatus(404)->withHeader('Content-Type', 'text/html');
});

// Run the app
$app->run();