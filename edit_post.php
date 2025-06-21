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
$post_data = null;
$error_message = '';
$success_message = '';

// Récupérer les données de la publication à modifier
if ($post_id > 0) {
    $stmt = $conn->prepare("SELECT id, title, content, image_path, post_type, is_active FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $post_data = $result->fetch_assoc();
    } else {
        $error_message = "Publication non trouvée.";
    }
    $stmt->close();
} else {
    $error_message = "ID de publication non spécifié.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $post_data) {
    $new_title = trim($_POST['title']);
    $new_content = trim($_POST['content']);
    $new_post_type = $_POST['post_type'];
    $new_is_active = isset($_POST['is_active']) ? 1 : 0;
    $current_image_path = $post_data['image_path']; // Garder l'ancienne image par défaut

    // Gestion de l'upload d'image si une nouvelle image est fournie
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/posts/"; // Assurez-vous que ce dossier existe et est inscriptible
        $image_name = uniqid() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Vérifier le type de fichier
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if($check === false) {
            $error_message .= "Le fichier n'est pas une image. ";
        }
        // Vérifier la taille du fichier (ex: max 5MB)
        if ($_FILES["image"]["size"] > 5000000) {
            $error_message .= "Désolé, votre fichier est trop grand. ";
        }
        // Autoriser certains formats de fichier
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
            $error_message .= "Désolé, seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés. ";
        }

        if (empty($error_message)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // Supprimer l'ancienne image si elle existe et est différente de la nouvelle
                if ($current_image_path && file_exists($current_image_path) && $current_image_path != $target_file) {
                    unlink($current_image_path);
                }
                $current_image_path = $target_file; // Mettre à jour le chemin de l'image
            } else {
                $error_message .= "Désolé, une erreur s'est produite lors du téléchargement de votre fichier. ";
            }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        // Option pour supprimer l'image actuelle sans en uploader une nouvelle
        if ($current_image_path && file_exists($current_image_path)) {
            unlink($current_image_path);
        }
        $current_image_path = null;
    }


    if (empty($new_title) || empty($new_content) || empty($new_post_type)) {
        $error_message .= "Veuillez remplir tous les champs obligatoires (Titre, Contenu, Type de publication).";
    }

    if (empty($error_message)) {
        $stmt_update = $conn->prepare("UPDATE posts SET title = ?, content = ?, image_path = ?, post_type = ?, is_active = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ssssii", $new_title, $new_content, $current_image_path, $new_post_type, $new_is_active, $post_id);
            if ($stmt_update->execute()) {
                $success_message = "Publication mise à jour avec succès !";
                // Mettre à jour post_data pour refléter les changements
                $post_data['title'] = $new_title;
                $post_data['content'] = $new_content;
                $post_data['image_path'] = $current_image_path;
                $post_data['post_type'] = $new_post_type;
                $post_data['is_active'] = $new_is_active;

            } else {
                $error_message = "Erreur lors de la mise à jour de la publication : " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $error_message = "Erreur de préparation de la requête : " . $conn->error;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Publication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid container">
            <a class="navbar-brand" href="admin_dashboard.php">Admin Dashboard</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a></li>
            </ul>
        </div>
    </nav>
    <div class="container mt-5">
        <h1 class="mb-4">Modifier la Publication</h1>
        <a href="admin_dashboard.php#posts" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left"></i> Retour à la gestion des Publications</a>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($post_data): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="edit_post.php?id=<?php echo $post_id; ?>" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Titre de la publication</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post_data['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Contenu</label>
                            <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($post_data['content']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image actuelle</label>
                            <?php if ($post_data['image_path'] && file_exists($post_data['image_path'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($post_data['image_path']); ?>" alt="Image actuelle" style="max-width: 200px; height: auto;">
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image" value="1">
                                    <label class="form-check-label" for="remove_image">Supprimer l'image actuelle</label>
                                </div>
                            <?php else: ?>
                                <p>Aucune image actuelle.</p>
                            <?php endif; ?>
                            <label for="image_upload" class="form-label">Changer l'image (laissera vide si non sélectionné)</label>
                            <input type="file" class="form-control" id="image_upload" name="image" accept="image/*">
                            <small class="form-text text-muted">Formats acceptés : JPG, JPEG, PNG, GIF (Max 5MB)</small>
                        </div>
                        <div class="mb-3">
                            <label for="post_type" class="form-label">Type de publication</label>
                            <select class="form-select" id="post_type" name="post_type" required>
                                <option value="">-- Choisir un type --</option>
                                <option value="news" <?php echo ($post_data['post_type'] == 'news') ? 'selected' : ''; ?>>Actualité</option>
                                <option value="formation" <?php echo ($post_data['post_type'] == 'formation') ? 'selected' : ''; ?>>Formation</option>
                                <option value="event" <?php echo ($post_data['post_type'] == 'event') ? 'selected' : ''; ?>>Événement</option>
                                <option value="service_promo" <?php echo ($post_data['post_type'] == 'service_promo') ? 'selected' : ''; ?>>Promotion Service</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo ($post_data['is_active'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Activer la publication (visible sur le site)</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>