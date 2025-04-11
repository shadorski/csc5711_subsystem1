<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use DI\Container;
use Spatie\PdfToText\Pdf;
use PhpOffice\PhpWord\IOFactory;

require __DIR__ . '/vendor/autoload.php';

session_start();

// Simple logging function
function logDebug($message) {
    $date = date("Y-m-d h:i:s");
    error_log($date . " [DEBUG] " . $message . "\n", 3, __DIR__ . '/debug.log');
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
$app->get('/', function (Request $request, Response $response) use ($container) {
    require_once __DIR__ . '/includes/db_helpers.php';
    logDebug("Home page accessed");
    
    if (!isset($_SESSION['user_id'])) {
        ob_start();
        include __DIR__ . '/home.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    $user_id = $_SESSION['user_id'];
    $db = $container->get('db');
    $conn = $db->getConnection();

    $data = [
        'latest_by_user' => get_latest_documents($conn, $user_id, true),
        'latest_by_others' => get_latest_documents($conn, $user_id, false),
        'total_by_user' => $conn->query("SELECT COUNT(*) FROM documents WHERE uploaded_by = $user_id")->fetch_row()[0],
        'file_type_counts' => $conn->query("SELECT file_type, COUNT(*) as count FROM documents WHERE uploaded_by = $user_id GROUP BY file_type")->fetch_all(MYSQLI_ASSOC)
    ];
    logDebug("Dashboard data: " . json_encode($data));

    ob_start();
    extract($data);
    include __DIR__ . '/dashboard.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Dashboard (protected route)
$app->get('/dashboard', function (Request $request, Response $response) {
    logDebug("Dashboard Page");
    return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
});

// Search Route (GET /search)
$app->get('/search', function (Request $request, Response $response) use ($container) {
    require_once __DIR__ . '/includes/db_helpers.php';
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/login')->withStatus(302);
    }

    $query = trim($request->getQueryParams()['q'] ?? '');
    $user_id = $_SESSION['user_id'];
    $db = $container->get('db');
    $conn = $db->getConnection();

    $results = !empty($query) ? search_documents($conn, $query) : [];
    $data = ['query' => $query, 'results' => $results];
    logDebug("Search data: " . json_encode($data));

    ob_start();
    extract($data);
    include __DIR__ . '/search.php';
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
                $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, gender, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $first_name, $last_name, $gender, $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['username'] = $username;
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

// Login page (GET)
$app->get('/login', function (Request $request, Response $response) {
    if (isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
    }
    ob_start();
    include __DIR__ . '/login.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Login form submission (POST)
$app->post('/login', function (Request $request, Response $response) use ($container) {
    if (isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
    }
    $data = $request->getParsedBody();
    logDebug("Login POST data: " . print_r($data, true));

    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    $errors = [];
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    $user_id = '';
    $hashed_password = '';

    if (empty($errors)) {
        $db = $container->get('db');
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($user_id, $hashed_password);
        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $stmt->close();
            //update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            logDebug("User logged in successfully: $username");
            
            return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
        } else {
            $errors[] = "Invalid username or password.";
            logDebug("Login failed for username: $username");
        }
        $stmt->close();
    }

    ob_start();
    include __DIR__ . '/login.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Upload page (GET)
$app->get('/upload', function (Request $request, Response $response) {
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/login')->withStatus(302);
    }
    ob_start();
    include __DIR__ . '/upload.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Upload form submission (POST)
$app->post('/upload', function (Request $request, Response $response) use ($container) {
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/login')->withStatus(302);
    }

    $data = $request->getParsedBody();
    $file = $request->getUploadedFiles()['file'] ?? null;
    $user_id = $_SESSION['user_id'];

    $title = trim($data['title'] ?? '');
    $author = trim($data['author'] ?? '');
    $isbn = trim($data['isbn'] ?? '');

    $allowed_file_types = ['txt', 'rtf', 'pdf', 'docx', 'epub', 'html'];

    $errors = [];
    if (empty($title) || strlen($title) > 255) $errors[] = "Title is required and must be 255 characters or less.";
    //if (empty($author) || strlen($author) > 255) $errors[] = "Author is required and must be 255 characters or less.";
    //if (empty($isbn) || !preg_match('/^\d{13}$|^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$/', $isbn)) $errors[] = "ISBN must be 13 digits or in XXX-X-XXX-XXXXX-X format.";
    if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) $errors[] = "File is required.";
    elseif ($file->getError() !== UPLOAD_ERR_OK) $errors[] = "File upload failed.";

    if (empty($errors)) {
        $file_extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_file_types)) {
            $errors[] = "Only .txt, .rtf, .pdf, .docx, .epub, and .html files are allowed.";
        }
    }

    if (empty($errors)) {
        $db = $container->get('db');
        $conn = $db->getConnection();

        // File handling
        $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $guid = bin2hex(random_bytes(16));
        $file_name = $guid . '.' . $file_extension;
        $file_path_absolute = $upload_dir . $file_name;
        $file_path_relative = "/subsystem1/uploads/$file_name";
        $original_filename = $file->getClientFilename();
        $file->moveTo($file_path_absolute);
        $size_kb = ceil($file->getSize() / 1024);

        // Insert into documents
        $file_type = $file_extension;
        $stmt = $conn->prepare("INSERT INTO documents (guid, title, author, upload_date, file_path, file_type, size_kb, uploaded_by, updated, updated_by, isbn, original_filename) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->bind_param("sssssiiiss", $guid, $title, $author, $file_path_relative, $file_type, $size_kb, $user_id, $user_id, $isbn, $original_filename);
        $stmt->execute();
        $doc_id = $conn->insert_id;
        $stmt->close();

        // Extract text content
        $text_content = '';
        try {
            switch ($file_type) {
                case 'txt':
                    $text_content = file_get_contents($file_path_absolute);
                    break;
        
                case 'rtf':
                    $phpWord = IOFactory::load($file_path_absolute, 'RTF');
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if ($element instanceof \PhpOffice\PhpWord\Element\Text || $element instanceof \PhpOffice\PhpWord\Element\Link) {
                                $text_content .= $element->getText() . "\n";
                            } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                foreach ($element->getElements() as $childElement) {
                                    if ($childElement instanceof \PhpOffice\PhpWord\Element\Text || $childElement instanceof \PhpOffice\PhpWord\Element\Link) {
                                        $text_content .= $childElement->getText() . "\n";
                                    }
                                }
                            }
                        }
                    }
                    break;
        
                case 'pdf':
                    $text_content = Pdf::getText($file_path_absolute);
                    break;
        
                case 'docx':
                    $phpWord = IOFactory::load($file_path_absolute, 'Word2007');
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if ($element instanceof \PhpOffice\PhpWord\Element\Text || $element instanceof \PhpOffice\PhpWord\Element\Link) {
                                $text_content .= $element->getText() . "\n";
                            } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                foreach ($element->getElements() as $childElement) {
                                    if ($childElement instanceof \PhpOffice\PhpWord\Element\Text || $childElement instanceof \PhpOffice\PhpWord\Element\Link) {
                                        $text_content .= $childElement->getText() . "\n";
                                    }
                                }
                            }
                        }
                    }
                    break;
        
                case 'epub':
                    $zip = new ZipArchive();
                    if ($zip->open($file_path_absolute) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if (preg_match('/\.(xhtml|html)$/', $name)) {
                                $content = $zip->getFromName($name);
                                $text_content .= strip_tags($content) . "\n";
                            }
                        }
                        $zip->close();
                    }
                    break;
        
                case 'html':
                    $text_content = strip_tags(file_get_contents($file_path_absolute));
                    break;
        
                default:
                    logDebug("Unsupported file type for extraction: $file_type");
                    break;
            }
        } catch (Exception $e) {
            logDebug("Text extraction failed for $file_name: " . $e->getMessage());
        }

        if (!empty($text_content)) {
            $stmt = $conn->prepare("INSERT INTO content (doc_id, text_content) VALUES (?, ?)");
            $stmt->bind_param("is", $doc_id, $text_content);
            $stmt->execute();
            $stmt->close();
        }

        logDebug("Document uploaded: $title by user $user_id (original: $original_filename)");
        return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
    }

    ob_start();
    include __DIR__ . '/upload.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Logout
$app->get('/logout', function (Request $request, Response $response) {
    session_destroy();
    return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
});



// Middleware to validate API key
$apiKeyMiddleware = function (Request $request, RequestHandler $handler) use ($container) {
    $apiKey = $request->getHeaderLine('X-API-Key');
    if (empty($apiKey)) {
        $payload = json_encode(['error' => 'API key is required']);
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write($payload);
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = $container->get('db');
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id, user_id, is_active FROM api_keys WHERE api_key = ?");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $keyData = $result->fetch_assoc();
    $stmt->close();

    if (!$keyData || !$keyData['is_active']) {
        $payload = json_encode(['error' => 'Invalid or inactive API key']);
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write($payload);
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // Store user_id and api_key in request attributes
    $request = $request->withAttribute('api_key', $apiKey);
    $request = $request->withAttribute('user_id', $keyData['user_id']);

    // Update last_used_at
    $stmt = $conn->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE api_key = ?");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $stmt->close();

    return $handler->handle($request);
};

// Middleware for rate limiting
$rateLimitMiddleware = function (Request $request, RequestHandler $handler) use ($container) {
    $apiKey = $request->getAttribute('api_key');
    $db = $container->get('db');
    $conn = $db->getConnection();

    // Define rate limit: 100 requests per hour
    $maxRequests = 100;
    $windowSeconds = 3600; // 1 hour

    // Get current window start (rounded to the hour) for insertion
    $windowStart = date('Y-m-d H:00:00');

    // Check existing request count, truncating window_start to hour for comparison
    $stmt = $conn->prepare("SELECT request_count FROM api_request_limits WHERE api_key = ? AND DATE_FORMAT(window_start, '%Y-%m-%d %H:00:00') = ?");
    $stmt->bind_param("ss", $apiKey, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $requestCount = $row ? (int)$row['request_count'] : 0;
    $stmt->close();

    if ($requestCount >= $maxRequests) {
        $payload = json_encode(['error' => 'Rate limit exceeded. Try again later.']);
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write($payload);
        return $response->withStatus(429)->withHeader('Content-Type', 'application/json');
    }

    // Increment request count
    if ($row) {
        $stmt = $conn->prepare("UPDATE api_request_limits SET request_count = request_count + 1 WHERE api_key = ? AND DATE_FORMAT(window_start, '%Y-%m-%d %H:00:00') = ?");
        $stmt->bind_param("ss", $apiKey, $windowStart);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO api_request_limits (api_key, request_count, window_start) VALUES (?, 1, ?)");
        $stmt->bind_param("ss", $apiKey, $windowStart);
        $stmt->execute();
        $stmt->close();
    }

    // Optional: Clean up old rows (older than 24 hours) to prevent table growth
    $stmt = $conn->prepare("DELETE FROM api_request_limits WHERE window_start < NOW() - INTERVAL 24 HOUR");
    $stmt->execute();
    $stmt->close();

    return $handler->handle($request);
};

// Public route: GET /api/documents (no API key required)
$app->get('/api/documents', function (Request $request, Response $response) use ($container) {
    $db = $container->get('db');
    $conn = $db->getConnection();

    $docs = get_all_documents($conn);
    $body = json_encode($docs);
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Public route: GET /api/documents/<doc_id> (returns document metadata)
$app->get('/api/documents/{doc_id}', function (Request $request, Response $response, array $args) use ($container) {
    $doc_id = (int) $args['doc_id']; // Cast to integer for safety
    $db = $container->get('db');
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT id as doc_id, title, file_path, upload_date, author, original_filename as filename
        FROM documents 
        WHERE id = ?
    ");
    if (!$stmt) {
        $body = json_encode(['status' => 'error', 'message' => 'Database error']);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    $result->free();

    if (!$document) {
        $body = json_encode(['status' => 'error', 'message' => 'Document not found']);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $body = json_encode($document);
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Public route: GET /api/documents/<doc_id>/content (returns document content)
$app->get('/api/documents/{doc_id}/content', function (Request $request, Response $response, array $args) use ($container) {
    $doc_id = (int) $args['doc_id']; // Cast to integer for safety
    $db = $container->get('db');
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT text_content 
        FROM content 
        WHERE doc_id = ?
    ");
    if (!$stmt) {
        $body = json_encode(['status' => 'error', 'message' => 'Database error']);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $content = $result->fetch_assoc();
    $stmt->close();
    $result->free();

    if (!$content || empty($content['text_content'])) {
        $body = json_encode(['status' => 'error', 'message' => 'Content not found']);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $body = json_encode(['text_content' => $content['text_content']]);
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// API Group
$app->group('/api', function ($app) use ($container) {
    require_once __DIR__ . '/includes/db_helpers.php';

    // Generate API key (POST /api/key/generate)
    $app->post('/key/generate', function (Request $request, Response $response) use ($container) {
        if (!isset($_SESSION['user_id'])) {
            $payload = json_encode(['error' => 'Unauthorized']);
            $response->getBody()->write($payload);
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $user_id = $_SESSION['user_id'];
        $db = $container->get('db');
        $conn = $db->getConnection();

        // Generate a unique API key
        $apiKey = bin2hex(random_bytes(32)); // 64-character key
        $activeKeys = 0;
        // Check if user already has an active key
        $stmt = $conn->prepare("SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($activeKeys);
        $stmt->fetch();
        $stmt->close();

        if ($activeKeys > 0) {
            $payload = json_encode(['error' => 'You already have an active API key']);
            $response->getBody()->write($payload);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Insert new API key
        $stmt = $conn->prepare("INSERT INTO api_keys (user_id, api_key, is_active) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $user_id, $apiKey);
        if ($stmt->execute()) {
            $payload = json_encode(['status' => 'success', 'api_key' => $apiKey]);
            $response->getBody()->write($payload);
            $stmt->close();
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $payload = json_encode(['error' => 'Failed to generate API key']);
        $response->getBody()->write($payload);
        $stmt->close();
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    });

    // Check Username (POST /api/user/check_username)
    $app->post('/user/check_username', function (Request $request, Response $response) use ($container) {
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
        $count = 0;
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

    // Add Document (POST /api/documents)
    $app->post('/documents', function (Request $request, Response $response) use ($container) {
        $user_id = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        $file = $request->getUploadedFiles()['file'] ?? null;

        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $isbn = trim($data['isbn'] ?? '');

        if (empty($title) || empty($author) || empty($isbn) || !$file || $file->getError() !== UPLOAD_ERR_OK) {
            $body = json_encode(['status' => 'error', 'message' => 'Invalid input']);
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = $container->get('db');
        $conn = $db->getConnection();
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $file_type = $file_extension;
        $file_name = bin2hex(random_bytes(16)) . '.' . $file_type;
        $file_path = $upload_dir . $file_name;
        $file->moveTo($file_path);
        $size_kb = ceil($file->getSize() / 1024);

        $doc_id = 0;
        if (add_document($conn, $user_id, $title, $author, $isbn, $file_path, $file_type, $size_kb, $file->getClientFilename(), $doc_id)) {
            $body = json_encode(['status' => 'success', 'data' => ['id' => $doc_id, 'title' => $title], 'message' => 'Document added']);
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
        $body = json_encode(['status' => 'error', 'message' => 'Failed to add document']);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    });

    // Search Documents (GET /api/search)
    $app->get('/search', function (Request $request, Response $response) use ($container) {
        $query = trim($request->getQueryParams()['q'] ?? '');
        $db = $container->get('db');
        $conn = $db->getConnection();
        
        $results = !empty($query) ? search_documents($conn, $query) : [];
        
        $body = json_encode(['status' => 'success', 'data' => $results]);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

})->add($rateLimitMiddleware)->add($apiKeyMiddleware);

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