<?php
session_start();
require_once 'config.php'; // Assurez-vous que le chemin est correct

// Génère un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    if (isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
    } else {
        header("Location: authentification.php");
    }
    exit();
}

$message = '';
$message_type = '';
$users = []; // Pour stocker la liste des utilisateurs
$edit_user = null; // Pour stocker les données de l'utilisateur à modifier
$old_user_data = []; // Pour retenir les données du formulaire en cas d'erreur

// Récupération des messages de session
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
// Récupérer les données du formulaire si elles ont été stockées après une erreur
if (isset($_SESSION['old_user_data'])) {
    $old_user_data = $_SESSION['old_user_data'];
    unset($_SESSION['old_user_data']);
}


try {
    // Vérifier si $pdo est une instance de PDO
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    // Gérer la modification d'un utilisateur si un ID est passé en GET
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $user_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($user_id_to_edit) {
            $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);
            $stmt->execute();
            $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$edit_user) {
                $_SESSION['error_message'] = "Utilisateur non trouvé pour l'édition.";
                header("Location: admin_manage_users.php");
                exit();
            }
            // Si pas d'anciennes données après une erreur, pré-remplir avec les données de l'utilisateur
            if (empty($old_user_data)) {
                $old_user_data = $edit_user;
            }
        } else {
            $_SESSION['error_message'] = "ID utilisateur invalide pour l'édition.";
            header("Location: admin_manage_users.php");
            exit();
        }
    }


    // Récupérer tous les utilisateurs
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>InnovaTech - Admin : Gérer les Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
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
                <h1 class="mb-4">Gérer les Utilisateurs</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <?php echo $edit_user ? 'Modifier un Utilisateur Existant' : 'Ajouter un Nouvel Utilisateur'; ?>
                    </div>
                    <div class="card-body">
                        <form action="admin_user_process.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="<?php echo $edit_user ? 'update' : 'add'; ?>">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur :</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($old_user_data['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email :</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($old_user_data['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Rôle :</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo (isset($old_user_data['role']) && $old_user_data['role'] == 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="admin" <?php echo (isset($old_user_data['role']) && $old_user_data['role'] == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe (laisser vide pour ne pas changer) :</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <?php if ($edit_user): ?>
                                    <small class="form-text text-muted">Laissez vide si vous ne souhaitez pas modifier le mot de passe actuel.</small>
                                <?php else: ?>
                                    <small class="form-text text-muted">Le mot de passe est requis pour un nouvel utilisateur.</small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe :</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>

                            <button type="submit" class="btn btn-<?php echo $edit_user ? 'warning' : 'primary'; ?>">
                                <i class="fas fa-<?php echo $edit_user ? 'edit' : 'plus-circle'; ?>"></i>
                                <?php echo $edit_user ? 'Modifier l\'utilisateur' : 'Ajouter l\'utilisateur'; ?>
                            </button>
                            <?php if ($edit_user): ?>
                                <a href="admin_manage_users.php" class="btn btn-secondary ms-2"><i class="fas fa-times-circle"></i> Annuler</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        Liste des Utilisateurs Enregistrés
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom d'utilisateur</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Date d'inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'info' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))); ?></td>
                                                <td>
                                                    <a href="admin_manage_users.php?action=edit&id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-warning btn-sm me-2" title="Modifier cet utilisateur">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </a>
                                                    <form action="admin_user_process.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible !');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer cet utilisateur">
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
                            <p class="text-center text-muted">Aucun utilisateur trouvé.</p>
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