<?php
session_start();

// Get the JSON POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Update the session variable
$_SESSION['selected_file_type'] = $data['type'];

// Return success
http_response_code(200); 