<?php
function search_documents($conn, $query, $limit = 10) {
    //logDebug('Search Query : '.$query);
    $stmt = $conn->prepare("
    SELECT DISTINCT d.id as doc_id, d.title, d.file_path, d.upload_date, d.author, d.original_filename as filename
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
    //logDebug('Results : '.$results);
    $stmt->close();
    $result->free();
    return $results;
}

function get_latest_documents($conn, $user_id, $by_user = true, $limit = 5) {
    $operator = $by_user ? '=' : '!=';
    $stmt = $conn->prepare("
        SELECT title, file_path, upload_date, author 
        FROM documents 
        WHERE uploaded_by $operator ? 
        ORDER BY upload_date DESC 
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result->free();
    return $results;
}

function add_document($conn, $user_id, $title, $author, $isbn, $file_path, $file_type, $size_kb, $original_filename, &$doc_id) {
    $guid = bin2hex(random_bytes(16));
    $file_path_relative = "/subsystem1/uploads/$guid.$file_type";
    $stmt = $conn->prepare("
        INSERT INTO documents (guid, title, author, upload_date, file_path, file_type, size_kb, uploaded_by, updated, updated_by, isbn, original_filename) 
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->bind_param("sssssiiiss", $guid, $title, $author, $file_path_relative, $file_type, $size_kb, $user_id, $user_id, $isbn, $original_filename);
    $success = $stmt->execute();
    $doc_id = $conn->insert_id;
    $stmt->close();
    return $success;
}

function add_document_content($conn, $doc_id, $text_content) {
    if (empty($text_content)) return true;
    $stmt = $conn->prepare("INSERT INTO content (doc_id, text_content) VALUES (?, ?)");
    $stmt->bind_param("is", $doc_id, $text_content);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_all_documents($conn) {
    $stmt = $conn->prepare("
        SELECT title, file_path, upload_date, author, id as doc_id, original_filename as filename
        FROM documents 
        ORDER BY upload_date DESC
    ");
    if (!$stmt) {
        // Log error if needed
        error_log('Prepare failed: ' . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result->free();
    return $results;
}
?>