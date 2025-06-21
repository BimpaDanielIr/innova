<?php
session_start();
require_once 'config.php'; // Assurez-vous que le chemin est correct

// Vérifie si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Veuillez vous connecter en tant qu'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Protection CSRF : Génère un jeton si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

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

$posts = []; // Initialisation du tableau des articles

try {
    // Vérifier si $pdo est une instance de PDO
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    // Récupérer tous les articles avec le nom de l'auteur et le type de publication
    $stmt = $pdo->prepare("SELECT p.id, p.title, p.content, p.post_type, p.image_path, u.username AS author_name, p.is_active
                            FROM posts p
                            JOIN users u ON p.author_id = u.id
                            ORDER BY p.publication_date DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>InnovaTech - Admin : Gérer les Publications</title>
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
                <h1 class="mb-4">Gérer les Publications</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <a href="add_post.php" class="btn btn-success"><i class="fas fa-plus-circle"></i> Ajouter une Nouvelle Publication</a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        Liste des Publications Existantes
                    </div>
                    <div class="card-body">
                        <?php if (empty($posts)): ?>
                            <p class="text-center text-muted">Aucune publication n'a été trouvée pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Titre</th>
                                            <th>Auteur</th>
                                            <th>Type</th>
                                            <th>Date de Publication</th>
                                            <th>Statut</th>
                                            <th>Image</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($post['id']); ?></td>
                                                <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                <td><?php echo htmlspecialchars($post['author_name']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($post['post_type'])); ?></td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($post['p.publication_date']))); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($post['is_active'] == 1) ? 'success' : 'danger'; ?>">
                                                        <?php echo ($post['is_active'] == 1) ? 'Actif' : 'Inactif'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($post['image_path']): ?>
                                                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Image de la publication" style="width: 80px; height: auto; border-radius: 5px;">
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="admin_edit_post.php?id=<?php echo htmlspecialchars($post['id']); ?>" class="btn btn-warning btn-sm me-2" title="Modifier cette publication">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </a>
                                                    <form action="admin_post_process.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette publication ?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer cette publication">
                                                            <i class="fas fa-trash-alt"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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