<?php
require_once 'controllers/CmsController.php';

$cmsController = new CmsController();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/create-post') {
    // Decode the incoming JSON payload
    $inputData = json_decode(file_get_contents('php://input'), true);

    // Call the function to create a new post
    $cmsController->createPost($inputData);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^\/get-single-post\/(\d+)\/(\w+)$/', $uri, $matches)) {
    // Extract post ID and type from the URI
    $postId = $matches[1];
    $postType = $matches[2];

    // Call the function to get the single post
    $cmsController->getSinglePost($postId, $postType);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri === '/get-all-posts') {
    // Get type from query parameters if provided (optional)
    $type = isset($_GET['type']) ? $_GET['type'] : null;

    // Call the function to get all posts
    $cmsController->getAllPosts($type);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri === '/get-post') {
    // Get type from query parameters if provided (optional)
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    // Call the function to get all posts
    $cmsController->getSinglePost($id, $type);
    exit;
}






