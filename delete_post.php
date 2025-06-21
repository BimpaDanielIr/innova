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

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($post_id > 0) {
    // Récupérer le chemin de l'image avant de supprimer le post pour pouvoir la supprimer du serveur
    $image_path = null;
    $stmt_get_image = $conn->prepare("SELECT image_path FROM posts WHERE id = ?");
    if ($stmt_get_image) {
        $stmt_get_image->bind_param("i", $post_id);
        $stmt_get_image->execute();
        $result_image = $stmt_get_image->get_result();
        if ($row_image = $result_image->fetch_assoc()) {
            $image_path = $row_image['image_path'];
        }
        $stmt_get_image->close();
    }

    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);

    if ($stmt->execute()) {
        // Supprimer l'image du serveur si elle existe
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        $_SESSION['success_message'] = "Publication supprimée avec succès.";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la suppression de la publication : " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "ID de publication non valide pour la suppression.";
}

$conn->close();
header("Location: admin_dashboard.php#posts");
exit();
?>