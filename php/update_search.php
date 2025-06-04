<?php
session_start();

// Get the search term from POST data
$searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';

// Update the session variable
$_SESSION['searched_file_name'] = $searchTerm;

// Redirect back to welcome page
header('Location: welcome.php');
exit; 