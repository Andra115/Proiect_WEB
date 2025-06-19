<?php
session_start();

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$_SESSION['selected_file_type'] = $data['type'];

http_response_code(200); 