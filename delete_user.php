<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: authentification.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id > 0) {
    // Empêcher un admin de se supprimer lui-même
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Vous ne pouvez pas supprimer votre propre compte administrateur.";
        header("Location: admin_dashboard.php#users");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la suppression de l'utilisateur : " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "ID utilisateur non valide pour la suppression.";
}

$conn->close();
header("Location: admin_dashboard.php#users");
exit();
?>