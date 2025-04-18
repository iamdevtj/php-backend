<?php
require_once 'config/database.php';

class CmsController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }


    public function createPost($data) {
        // Check if required fields are provided
        if (empty($data['title']) || empty($data['description']) || empty($data['images']) || !is_array($data['images']) || empty($data['type'])) {
            echo json_encode(['status' => 'error', 'message' => 'Title, description, images (array), and type are required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Validate the type field to make sure it's either 'gallery' or 'news'
        if (!in_array($data['type'], ['gallery', 'news','library'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid post type']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Insert the post details into the `posts` table
        $query = "INSERT INTO posts (title, description, type, created_at) VALUES (?, ?, ?, NOW())";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sss', $data['title'], $data['description'], $data['type']);
        $stmt->execute();
    
        // Check if the post was successfully inserted
        if ($stmt->affected_rows > 0) {
            // Get the ID of the newly created post
            $postId = $stmt->insert_id;
            $stmt->close();
    
            // Insert the images into a `post_images` table
            $imageQuery = "INSERT INTO post_images (post_id, image_url) VALUES (?, ?)";
            $imageStmt = $this->conn->prepare($imageQuery);
    
            foreach ($data['images'] as $image) {
                $imageStmt->bind_param('is', $postId, $image);
                $imageStmt->execute();
            }
            $imageStmt->close();
    
            echo json_encode(['status' => 'success', 'message' => 'Post created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create post']);
            http_response_code(500); // Internal Server Error
        }
    }
    
    
    public function getSinglePost($id, $type) {
        // SQL query to fetch the post based on ID and type
        $query = "SELECT id, title, description, type, created_at FROM posts WHERE id = ? AND type = ?";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('is', $id, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();
    
        // Check if the post exists
        if ($post) {
            // Fetch the associated images
            $imageQuery = "SELECT image_url FROM post_images WHERE post_id = ?";
            $imageStmt = $this->conn->prepare($imageQuery);
            $imageStmt->bind_param('i', $id);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();
            $images = $imageResult->fetch_all(MYSQLI_ASSOC);
            $imageStmt->close();
    
            // Add images to the post data
            $post['images'] = array_column($images, 'image_url');
    
            echo json_encode(['status' => 'success', 'data' => $post]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Post not found']);
            http_response_code(404); // Not Found
        }
    }
    
    public function getAllPosts($type = null) {
        // Validate the provided type if any
        if ($type && !in_array($type, ['gallery', 'news','library'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid post type']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // SQL query to fetch all posts, optionally filtered by type
        $query = "SELECT id, title, description, type, created_at FROM posts";
        if ($type) {
            $query .= " WHERE type = ?";
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if ($type) {
            $stmt->bind_param('s', $type);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $posts = [];
        while ($row = $result->fetch_assoc()) {
            // Initialize the post data
            $post = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'type' => $row['type'],
                'created_at' => $row['created_at'],
                'images' => [] // Add an images key for all types
            ];
    
            // Fetch images for gallery and news posts
            $imageQuery = "SELECT image_url FROM post_images WHERE post_id = ?";
            $imageStmt = $this->conn->prepare($imageQuery);
            $imageStmt->bind_param('i', $row['id']);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();
            $images = [];
            while ($imageRow = $imageResult->fetch_assoc()) {
                $images[] = $imageRow['image_url'];
            }
            $imageStmt->close();
    
            // Add the images to the post
            $post['images'] = $images;
    
            $posts[] = $post;
        }
        $stmt->close();
    
        // Respond with the posts or a no-content message
        if (!empty($posts)) {
            echo json_encode(['status' => 'success', 'data' => $posts]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No posts found']);
            http_response_code(404); // Not Found
        }
    }
    
    
    
    
        
    
}