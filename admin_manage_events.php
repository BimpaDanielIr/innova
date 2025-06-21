<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier de configuration de la base de données
require_once 'config.php'; // Assurez-vous que le chemin est correct et que $pdo est initialisé ici

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Protection CSRF : Génère un jeton si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';
$events = []; // Pour stocker tous les événements
$edit_event = null; // Pour stocker l'événement en cours d'édition (si GET 'id' est présent)

// Récupère les messages d'erreur/succès de la session
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

try {
    // Vérifier si $pdo est une instance de PDO
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    // Gérer la modification d'un événement si un ID est passé en GET
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $event_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($event_id_to_edit) {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->bindParam(':id', $event_id_to_edit, PDO::PARAM_INT);
            $stmt->execute();
            $edit_event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$edit_event) {
                $_SESSION['error_message'] = "Événement non trouvé pour l'édition.";
                header("Location: admin_manage_events.php"); // Rediriger si l'ID n'existe pas
                exit();
            }
        } else {
            $_SESSION['error_message'] = "ID d'événement invalide pour l'édition.";
            header("Location: admin_manage_events.php");
            exit();
        }
    }

    // Récupérer tous les événements de la base de données
    $stmt = $pdo->prepare("SELECT id, title, event_date, location, description, image_path, created_at, updated_at, is_active FROM events ORDER BY event_date DESC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'danger';
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Gérer les Événements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/admin.css"> </head>
<body>
    <div class="wrapper">
        <?php include 'admin_sidebar.php'; ?>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Toggle Sidebar</span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="nav navbar-nav ml-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">Tableau de Bord</a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <main class="container-fluid">
                <h1 class="mb-4">Gérer les Événements</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        Ajouter un Nouvel Événement
                    </div>
                    <div class="card-body">
                        <form action="admin_event_process.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre de l'événement :</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Date et Heure de l'événement :</label>
                                <input type="datetime-local" class="form-control" id="event_date" name="event_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lieu :</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description :</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Image de l'événement :</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="form-text text-muted">Format: JPG, PNG, GIF. Taille maximale: 5MB.</small>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">Activer l'événement (visible sur le site)</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Ajouter l'événement</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        Liste des Événements Existants
                    </div>
                    <div class="card-body">
                        <?php if (!empty($events)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Titre</th>
                                            <th>Date & Heure</th>
                                            <th>Lieu</th>
                                            <th>Image</th>
                                            <th>Statut</th>
                                            <th>Créé le</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($event['id']); ?></td>
                                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($event['event_date']))); ?></td>
                                                <td><?php echo htmlspecialchars($event['location']); ?></td>
                                                <td>
                                                    <?php if ($event['image_path']): ?>
                                                        <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="Image de l'événement" style="width: 80px; height: auto; border-radius: 5px;">
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($event['is_active'] == 1) ? 'success' : 'danger'; ?>">
                                                        <?php echo ($event['is_active'] == 1) ? 'Actif' : 'Inactif'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($event['created_at']))); ?></td>
                                                <td>
                                                    <a href="admin_edit_event.php?id=<?php echo htmlspecialchars($event['id']); ?>" class="btn btn-warning btn-sm me-2" title="Modifier cet événement">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </a>
                                                    <form action="admin_event_process.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer cet événement">
                                                            <i class="fas fa-trash-alt"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Aucun événement n'a été ajouté pour le moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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