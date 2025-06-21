<?php
session_start();

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Génère un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inclure le fichier de configuration de la base de données
require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event_data = null; // Pour stocker les données de l'événement à modifier

$error_message = '';
$success_message = '';
$old_event_data = []; // Pour pré-remplir le formulaire en cas d'erreur de soumission

// Récupère les messages d'erreur/succès de la session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
    // Si une erreur est présente, récupérer les anciennes données du formulaire
    if (isset($_SESSION['old_event_data'])) {
        $old_event_data = $_SESSION['old_event_data'];
        unset($_SESSION['old_event_data']);
    }
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    // Vérifier si $pdo est bien un objet PDO valide
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    if ($event_id) {
        // Récupérer les données de l'événement pour pré-remplir le formulaire
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->bindParam(':id', $event_id, PDO::PARAM_INT);
        $stmt->execute();
        $event_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event_data) {
            $_SESSION['error_message'] = "Événement non trouvé.";
            header("Location: admin_manage_events.php");
            exit();
        }
        // Si aucune donnée POST n'est présente (première visite ou redirection après succès), utilisez les données de la DB
        if (empty($old_event_data)) {
            $old_event_data = $event_data;
        }
    } else {
        $_SESSION['error_message'] = "ID d'événement manquant.";
        header("Location: admin_manage_events.php");
        exit();
    }

    // Traitement de la soumission du formulaire (mise à jour)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Vérification du jeton CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.");
        }

        // Collecter et nettoyer les données du formulaire
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $location = trim($_POST['location']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $current_image_path = $_POST['current_image_path'] ?? null; // Chemin de l'image existante

        // Pré-remplir les données pour le cas d'erreur
        $_SESSION['old_event_data'] = $_POST;
        $_SESSION['old_event_data']['id'] = $event_id; // S'assurer que l'ID est conservé pour la redirection

        // Validation simple des entrées
        if (empty($title) || empty($description) || empty($event_date) || empty($location)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Gestion de l'upload de l'image
        $image_path = $current_image_path; // Par défaut, conserve l'ancienne image

        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/events/"; // Assurez-vous que ce dossier existe et est inscriptible
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $image_file_type = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($image_file_type, $allowed_types)) {
                throw new Exception("Seuls les fichiers JPG, JPEG, PNG, GIF sont autorisés.");
            }

            $unique_name = uniqid('event_', true) . '.' . $image_file_type;
            $target_file = $target_dir . $unique_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;

                // Supprimer l'ancienne image si elle existe et est différente de la nouvelle
                if ($current_image_path && file_exists($current_image_path) && $current_image_path !== $image_path) {
                    unlink($current_image_path);
                }
            } else {
                throw new Exception("Erreur lors de l'upload de l'image.");
            }
        }

        // Préparer la requête de mise à jour
        $stmt_update = $pdo->prepare("UPDATE events SET title = :title, description = :description, event_date = :event_date, location = :location, image_path = :image_path, is_active = :is_active, updated_at = NOW() WHERE id = :id");

        $stmt_update->bindParam(':title', $title);
        $stmt_update->bindParam(':description', $description);
        $stmt_update->bindParam(':event_date', $event_date);
        $stmt_update->bindParam(':location', $location);
        $stmt_update->bindParam(':image_path', $image_path);
        $stmt_update->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        $stmt_update->bindParam(':id', $event_id, PDO::PARAM_INT);

        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "Événement mis à jour avec succès.";
            unset($_SESSION['old_event_data']); // Nettoyer les anciennes données si succès
            header("Location: admin_manage_events.php"); // Rediriger vers la liste des événements
            exit();
        } else {
            throw new Exception("Erreur lors de la mise à jour de l'événement.");
        }
    }

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    // Conserver les données POST en cas d'erreur de DB pour pré-remplir
    if (isset($_POST)) {
        $_SESSION['old_event_data'] = $_POST;
        $_SESSION['old_event_data']['id'] = $event_id;
    }
} catch (Exception $e) {
    $error_message = "Erreur : " . $e->getMessage();
    // Conserver les données POST en cas d'erreur pour pré-remplir
    if (isset($_POST)) {
        $_SESSION['old_event_data'] = $_POST;
        $_SESSION['old_event_data']['id'] = $event_id;
    }
}

// Si $old_event_data est vide à ce stade (ex: première charge de la page ou redirection après succès et données nettoyées),
// assurez-vous qu'elle contient les données de l'événement si elles ont été chargées depuis la DB.
if (empty($old_event_data) && $event_data) {
    $old_event_data = $event_data;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Modifier un Événement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 20px;
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #343a40;
            text-align: center;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #f8f9fa;
            text-decoration: none;
        }
        #sidebar ul li a:hover {
            color: #343a40;
            background: #fff;
        }
        #content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }
        .navbar {
            padding: 15px 10px;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .footer {
            width: 100%;
            background: #343a40;
            color: #fff;
            text-align: center;
            padding: 15px 0;
        }
        .current-image-preview img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'admin_sidebar.php'; // Inclure la sidebar ?>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Menu</span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="auth_process.php?action=logout">Déconnexion <i class="fas fa-sign-out-alt"></i></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <h1 class="mb-4">Modifier un Événement</h1>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-white">
                        Formulaire de Modification d'Événement
                    </div>
                    <div class="card-body">
                        <form action="admin_edit_event.php?id=<?php echo htmlspecialchars($event_id); ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                            <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($old_event_data['image_path'] ?? ''); ?>">

                            <div class="mb-3">
                                <label for="title" class="form-label">Titre de l'événement :</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($old_event_data['title'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description :</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($old_event_data['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Date de l'événement :</label>
                                <input type="datetime-local" class="form-control" id="event_date" name="event_date" value="<?php echo date('Y-m-d\TH:i', strtotime($old_event_data['event_date'] ?? '')); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lieu :</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($old_event_data['location'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Image de l'événement (laisser vide pour ne pas changer) :</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if (!empty($old_event_data['image_path'])): ?>
                                    <div class="mt-2 current-image-preview">
                                        <p>Image actuelle :</p>
                                        <img src="<?php echo htmlspecialchars($old_event_data['image_path']); ?>" alt="Image actuelle de l'événement" class="img-thumbnail">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?php echo (isset($old_event_data['is_active']) && $old_event_data['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Activer l'événement (visible sur le site)</label>
                            </div>
                            <button type="submit" class="btn btn-warning"><i class="fas fa-edit"></i> Modifier l'événement</button>
                            <a href="admin_manage_events.php" class="btn btn-secondary"><i class="fas fa-times-circle"></i> Annuler</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Script pour le basculement de la barre latérale
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>