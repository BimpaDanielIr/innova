<?php
session_start();

// Protection de la page : Vérifie si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Inclure le fichier de configuration de la base de données
// Utilisation de PDO pour une meilleure sécurité et flexibilité
require_once 'config.php'; // Assurez-vous que ce fichier initialise $pdo

$error_message = '';
$success_message = '';

// Récupérer les messages de session et les effacer
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pré-remplir les champs du formulaire en cas d'erreur de soumission
$old_post_data = $_SESSION['old_post_data'] ?? [];
unset($_SESSION['old_post_data']); // Effacer après utilisation

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
        $_SESSION['error_message'] = $error_message;
        $_SESSION['old_post_data'] = $_POST; // Conserver les données soumises
        header("Location: add_post.php");
        exit();
    }

    // Filtration et validation des entrées
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $post_type = filter_input(INPUT_POST, 'post_type', FILTER_SANITIZE_STRING); // news, formation, event, service_promo
    $posted_by = $_SESSION['user_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0; // Checkbox

    // Stocker les données actuelles du POST en cas d'erreur
    $_SESSION['old_post_data'] = $_POST;

    // Validation côté serveur
    if (empty($title) || empty($content) || empty($post_type)) {
        $error_message = "Veuillez remplir tous les champs obligatoires (Titre, Contenu, Type de publication).";
    } elseif (strlen($title) > 255) {
        $error_message = "Le titre ne peut pas dépasser 255 caractères.";
    } elseif (!in_array($post_type, ['news', 'formation', 'event', 'service_promo'])) {
        $error_message = "Type de publication invalide.";
    }

    $image_path = null;

    // Gestion de l'upload d'image
    if (empty($error_message) && isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/posts/"; // Assurez-vous que ce dossier existe et est inscriptible
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Crée le dossier si inexistant
        }

        $image_file_type = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($image_file_type, $allowed_types)) {
            $error_message = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        } elseif ($_FILES['image']['size'] > $max_file_size) {
            $error_message = "La taille de l'image ne doit pas dépasser 5 Mo.";
        } else {
            // Générer un nom de fichier unique pour éviter les conflits
            $new_file_name = uniqid('post_') . '.' . $image_file_type;
            $image_path = $target_dir . $new_file_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $error_message = "Erreur lors de l'upload de l'image.";
                $image_path = null; // Réinitialiser le chemin si l'upload échoue
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_message = "Une erreur est survenue lors du téléchargement de l'image : " . $_FILES['image']['error'];
    }

    // Si aucune erreur de validation ou d'upload, procéder à l'insertion
    if (empty($error_message)) {
        try {
            // Utilisation de requêtes préparées avec PDO pour prévenir les injections SQL
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, post_type, author_id, image_url, is_active, publication_date, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            
            // Les publications sont "published" par défaut lors de l'ajout
            $status = 'published'; 

            if ($stmt->execute([$title, $content, $post_type, $posted_by, $image_path, $is_active, $status])) {
                $success_message = "Publication ajoutée avec succès !";
                unset($_SESSION['old_post_data']); // Nettoyer les données du formulaire après succès
                $_SESSION['success_message'] = $success_message;
                header("Location: admin_manage_posts.php"); // Rediriger vers la liste des posts
                exit();
            } else {
                $error_message = "Erreur lors de l'ajout de la publication : " . implode(" ", $stmt->errorInfo());
                // Si l'insertion échoue, supprimer l'image uploadée
                if ($image_path && file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
            // Si l'insertion échoue, supprimer l'image uploadée
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
    // Si une erreur survient, stocker le message et rediriger pour afficher sur la page
    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
        header("Location: add_post.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Ajouter une Publication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .wrapper {
            flex: 1;
            display: flex;
            padding-top: 20px;
        }
        .sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            padding-top: 20px;
        }
        .sidebar.active {
            margin-left: -250px;
        }
        .sidebar .sidebar-header {
            padding: 20px;
            background: #343a40;
        }
        .sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        .sidebar ul p {
            color: #fff;
            padding: 10px;
        }
        .sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
        }
        .sidebar ul li a:hover {
            color: #7386D5;
            background: #fff;
        }
        .sidebar ul li.active > a, .sidebar ul li a[aria-expanded="true"] {
            color: #fff;
            background: #0d6efd;
        }
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
            margin-left: 250px;
        }
        #sidebarCollapse {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .form-label strong {
            color: #007bff;
        }
        .current-image-preview img {
            max-width: 200px;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header text-center">
                <h3>Admin Dashboard</h3>
            </div>
            <ul class="list-unstyled components">
                <li>
                    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Tableau de Bord</a>
                </li>
                <li>
                    <a href="#userSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-users"></i> Gestion Utilisateurs</a>
                    <ul class="collapse list-unstyled" id="userSubmenu">
                        <li><a href="admin_manage_users.php"><i class="fas fa-user-cog"></i> Gérer les Utilisateurs</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#formationsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-graduation-cap"></i> Gestion Formations</a>
                    <ul class="collapse list-unstyled" id="formationsSubmenu">
                        <li><a href="admin_manage_formations.php"><i class="fas fa-chalkboard-teacher"></i> Gérer les Formations</a></li>
                        <li><a href="admin_manage_enrollments.php"><i class="fas fa-user-check"></i> Gérer les Inscriptions</a></li>
                    </ul>
                </li>
                <li class="active">
                    <a href="#blogSubmenu" data-bs-toggle="collapse" aria-expanded="true" class="dropdown-toggle"><i class="fas fa-newspaper"></i> Gestion Articles & Blog</a>
                    <ul class="collapse list-unstyled show" id="blogSubmenu">
                        <li><a href="admin_manage_posts.php"><i class="fas fa-sticky-note"></i> Gérer les Publications</a></li>
                        <li><a href="add_post.php"><i class="fas fa-plus-circle"></i> Ajouter une Publication</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#eventSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-calendar-alt"></i> Gestion Événements</a>
                    <ul class="collapse list-unstyled" id="eventSubmenu">
                        <li><a href="admin_manage_events.php"><i class="fas fa-calendar-check"></i> Gérer les Événements</a></li>
                    </ul>
                </li>
                <li>
                    <a href="admin_finances.php"><i class="fas fa-dollar-sign"></i> Gestion Financière</a>
                </li>
                <li>
                    <a href="admin_assistance.php"><i class="fas fa-question-circle"></i> Demandes d'Assistance</a>
                </li>
                <li>
                    <a href="admin_contacts.php"><i class="fas fa-envelope"></i> Messages de Contact</a>
                </li>
                <li>
                    <a href="admin_reports.php"><i class="fas fa-chart-line"></i> Rapports</a>
                </li>
                <li>
                    <a href="admin_site_settings.php"><i class="fas fa-cog"></i> Paramètres du Site</a>
                </li>
                <li>
                    <a href="auth_process.php?action=logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </li>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Toggle Sidebar</span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="#"><i class="fas fa-user-circle"></i> Admin</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger text-center" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success text-center" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h1 class="h3 mb-0">Ajouter une Nouvelle Publication</h1>
                    </div>
                    <div class="card-body">
                        <form action="add_post.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="mb-3">
                                <label for="title" class="form-label"><strong>Titre de la publication :</strong></label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($old_post_data['title'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label"><strong>Contenu :</strong></label>
                                <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($old_post_data['content'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label"><strong>Image de la publication :</strong> (Optionnel)</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="form-text text-muted">Formats acceptés : JPG, JPEG, PNG, GIF. Taille maximale : 5 Mo.</small>
                            </div>
                            <div class="mb-3">
                                <label for="post_type" class="form-label"><strong>Type de publication :</strong></label>
                                <select class="form-select" id="post_type" name="post_type" required>
                                    <option value="">-- Choisir un type --</option>
                                    <option value="news" <?php echo (($old_post_data['post_type'] ?? '') == 'news') ? 'selected' : ''; ?>>Actualité</option>
                                    <option value="formation" <?php echo (($old_post_data['post_type'] ?? '') == 'formation') ? 'selected' : ''; ?>>Formation</option>
                                    <option value="event" <?php echo (($old_post_data['post_type'] ?? '') == 'event') ? 'selected' : ''; ?>>Événement</option>
                                    <option value="service_promo" <?php echo (($old_post_data['post_type'] ?? '') == 'service_promo') ? 'selected' : ''; ?>>Promotion Service</option>
                                </select>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo (isset($old_post_data['is_active']) && $old_post_data['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Activer la publication (visible sur le site)</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Ajouter la publication</button>
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