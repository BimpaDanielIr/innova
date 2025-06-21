<?php
session_start();


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once 'config.php'; // 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';
$formations = []; 
$edit_formation = null; 

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

    // Gérer la modification d'une formation si un ID est passé en GET
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $formation_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($formation_id_to_edit) {
            $stmt = $pdo->prepare("SELECT * FROM formations WHERE id = :id");
            $stmt->bindParam(':id', $formation_id_to_edit, PDO::PARAM_INT);
            $stmt->execute();
            $edit_formation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$edit_formation) {
                $_SESSION['error_message'] = "Formation non trouvée pour l'édition.";
                header("Location: admin_manage_formations.php"); 
                exit();
            }
        } else {
            $_SESSION['error_message'] = "ID de formation invalide pour l'édition.";
            header("Location: admin_manage_formations.php");
            exit();
        }
    }

  
    $stmt = $pdo->prepare("SELECT id, title, description, duration, price, category, image_path, is_active FROM formations ORDER BY created_at DESC");
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>InnovaTech - Admin : Gérer les Formations</title>
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
                <h1 class="mb-4">Gérer les Formations</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        Ajouter une Nouvelle Formation
                    </div>
                    <div class="card-body">
                        <form action="admin_formation_process.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre de la formation :</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description :</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="duration" class="form-label">Durée (ex: 20 heures, 3 jours) :</label>
                                <input type="text" class="form-control" id="duration" name="duration" required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Prix (en devise locale) :</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="category" class="form-label">Catégorie :</label>
                                <input type="text" class="form-control" id="category" name="category" required>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Image de la formation :</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="form-text text-muted">Format: JPG, PNG, GIF. Taille maximale: 5MB.</small>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">Activer la formation (visible sur le site)</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Ajouter la formation</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        Liste des Formations Existantes
                    </div>
                    <div class="card-body">
                        <?php if (!empty($formations)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Titre</th>
                                            <th>Catégorie</th>
                                            <th>Durée</th>
                                            <th>Prix</th>
                                            <th>Image</th>
                                            <th>Statut</th>
                                            
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formations as $formation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($formation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($formation['title']); ?></td>
                                                <td><?php echo htmlspecialchars($formation['category']); ?></td>
                                                <td><?php echo htmlspecialchars($formation['duration']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($formation['price'], 2)) . ' FBU'; ?></td>
                                                <td>
                                                    <?php if ($formation['image_path']): ?>
                                                        <img src="<?php echo htmlspecialchars($formation['image_path']); ?>" alt="Image de la formation" style="width: 80px; height: auto; border-radius: 5px;">
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($formation['is_active'] == 1) ? 'success' : 'danger'; ?>">
                                                        <?php echo ($formation['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <a href="admin_edit_formation.php?id=<?php echo htmlspecialchars($formation['id']); ?>" class="btn btn-warning btn-sm me-2" title="Modifier cette formation">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </a>
                                                    <form action="admin_formation_process.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette formation ?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="formation_id" value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer cette formation">
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
                            <p class="text-center text-muted">Aucune formation n'a été ajoutée pour le moment.</p>
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