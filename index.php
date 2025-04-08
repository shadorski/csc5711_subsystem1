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
    logDebug("Home page accessed");
    
    if (!isset($_SESSION['user_id'])) {
        ob_start();
        include __DIR__ . '/home.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    // Logged-in users get the dashboard
    $user_id = $_SESSION['user_id'];
    $db = $container->get('db');
    $conn = $db->getConnection();

    // Latest uploads by user
    $stmt = $conn->prepare("SELECT title, file_path, upload_date FROM documents WHERE uploaded_by = ? ORDER BY upload_date DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest_by_user = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result->free();

    // Latest uploads by others
    $stmt = $conn->prepare("SELECT title, file_path, upload_date FROM documents WHERE uploaded_by != ? ORDER BY upload_date DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest_by_others = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result->free();

    // Total documents by user
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM documents WHERE uploaded_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_by_user = $result->fetch_assoc()['total'];
    $stmt->close();
    $result->free();

    // File type counts by user
    $stmt = $conn->prepare("SELECT file_type, COUNT(*) as count FROM documents WHERE uploaded_by = ? GROUP BY file_type");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file_type_counts = [];
    while ($row = $result->fetch_assoc()) {
        $file_type_counts[$row['file_type']] = $row['count'];
    }
    $stmt->close();
    $result->free();

    // Pass data to view
    $data = [
        'latest_by_user' => $latest_by_user,
        'latest_by_others' => $latest_by_others,
        'total_by_user' => $total_by_user,
        'file_type_counts' => $file_type_counts
    ];
    
    ob_start();
    extract($data); // Ensure variables are in scope for dashboard.php
    include __DIR__ . '/dashboard.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Dashboard (protected route)
$app->get('/dashboard', function (Request $request, Response $response) {
    logDebug("Dashoard Page");
    return $response->withHeader('Location', '/subsystem1/')->withStatus(302);
});

// Search Route (GET /search)
$app->get('/search', function (Request $request, Response $response) use ($container) {
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/subsystem1/login')->withStatus(302);
    }

    $query = trim($request->getQueryParams()['q'] ?? '');
    $user_id = $_SESSION['user_id'];
    $db = $container->get('db');
    $conn = $db->getConnection();

    $results = [];
    if (!empty($query)) {
        $stmt = $conn->prepare("
            SELECT DISTINCT d.title, d.file_path, d.upload_date, d.author 
            FROM documents d
            LEFT JOIN content c ON d.id = c.doc_id
            WHERE d.title LIKE ? 
               OR d.author LIKE ? 
               OR c.text_content LIKE ?
            ORDER BY d.upload_date DESC 
        ");
        $search_term = "%$query%";
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $result->free();
    }

    // Pass data to search.php
    $data = [
        'query' => $query,
        'results' => $results
    ];
    logDebug("Search data: " . json_encode($data));

    ob_start();
    extract($data); // Make $query and $results available in search.php
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