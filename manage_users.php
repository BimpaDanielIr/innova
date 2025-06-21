<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier de configuration de la base de données
// Assurez-vous que le chemin est correct par rapport à l'emplacement de manage_users.php
require_once 'config.php';

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

$error_message = '';
$success_message = '';
$users = []; // Pour stocker tous les utilisateurs

// Récupère les messages d'erreur/succès de la session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// --- LOGIQUE DE GESTION DES UTILISATEURS (AJOUT / SUPPRESSION / ACTIVER / DÉSACTIVER) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Erreur de sécurité : Jeton CSRF invalide.";
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$user_id && $action !== 'add_user') { // Pour add_user, l'ID n'est pas encore généré
            $error_message = "ID utilisateur invalide.";
        } else {
            try {
                switch ($action) {
                    case 'add_user':
                        $username = trim($_POST['username']);
                        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                        $password = $_POST['password'];
                        $role = $_POST['role'] ?? 'user'; // Rôle par défaut
                        $is_active = isset($_POST['is_active']) ? 1 : 0;

                        if (empty($username) || empty($email) || empty($password)) {
                            $error_message = "Tous les champs requis pour l'ajout d'utilisateur doivent être remplis.";
                            break;
                        }
                        if (!$email) {
                            $error_message = "Format d'email invalide.";
                            break;
                        }

                        // Vérifier si l'email ou le nom d'utilisateur existe déjà
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$username, $email]);
                        if ($stmt->fetch()) {
                            $error_message = "Le nom d'utilisateur ou l'email existe déjà.";
                            break;
                        }

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$username, $email, $hashed_password, $role, $is_active])) {
                            $success_message = "Utilisateur ajouté avec succès !";
                        } else {
                            $error_message = "Erreur lors de l'ajout de l'utilisateur.";
                        }
                        break;

                    case 'toggle_active':
                        $status = ($_POST['status'] == 'activate') ? 1 : 0;
                        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                        if ($stmt->execute([$status, $user_id])) {
                            $success_message = "Statut de l'utilisateur mis à jour avec succès !";
                        } else {
                            $error_message = "Erreur lors de la mise à jour du statut de l'utilisateur.";
                        }
                        break;

                    case 'delete':
                        // Empêcher un admin de se supprimer lui-même
                        if ($user_id == $_SESSION['user_id']) {
                            $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
                            break;
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        if ($stmt->execute([$user_id])) {
                            $success_message = "Utilisateur supprimé avec succès !";
                        } else {
                            $error_message = "Erreur lors de la suppression de l'utilisateur.";
                        }
                        break;
                    
                    case 'edit_user':
                        $username = trim($_POST['username']);
                        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                        $role = $_POST['role'] ?? 'user';
                        $is_active = isset($_POST['is_active']) ? 1 : 0;
                        $password_update = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

                        if (empty($username) || empty($email)) {
                            $error_message = "Le nom d'utilisateur et l'email sont requis pour la modification.";
                            break;
                        }
                        if (!$email) {
                            $error_message = "Format d'email invalide.";
                            break;
                        }

                        // Vérifier si l'email ou le nom d'utilisateur existe déjà pour un AUTRE utilisateur
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                        $stmt->execute([$username, $email, $user_id]);
                        if ($stmt->fetch()) {
                            $error_message = "Le nom d'utilisateur ou l'email existe déjà pour un autre compte.";
                            break;
                        }

                        if ($password_update) {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
                            $exec_params = [$username, $email, $password_update, $role, $is_active, $user_id];
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                            $exec_params = [$username, $email, $role, $is_active, $user_id];
                        }
                        
                        if ($stmt->execute($exec_params)) {
                            $success_message = "Utilisateur mis à jour avec succès !";
                        } else {
                            $error_message = "Erreur lors de la mise à jour de l'utilisateur.";
                        }
                        break;


                    default:
                        $error_message = "Action non reconnue.";
                        break;
                }
            } catch (PDOException $e) {
                $error_message = "Erreur de base de données : " . $e->getMessage();
                error_log("Erreur dans manage_users.php (POST) : " . $e->getMessage());
            }
        }
    }
    // Après toute action POST, redirige pour éviter le re-submit du formulaire
    // Stocker les messages dans la session avant la redirection
    $_SESSION['error_message'] = $error_message;
    $_SESSION['success_message'] = $success_message;
    header("Location: manage_users.php");
    exit();
}


// --- RÉCUPÉRATION DES UTILISATEURS POUR L'AFFICHAGE (GET) ---
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at, is_active FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur de base de données lors de la récupération des utilisateurs : " . $e->getMessage();
    error_log("Erreur dans manage_users.php (GET) : " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Utilisateurs - InnovaTech Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
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
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #343a40;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul.components {
            padding: 20px 0;
        }
        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }
        #sidebar ul li a:hover {
            color: #343a40;
            background: #fff;
        }
        #sidebar ul li.active > a, a[aria-expanded="true"] {
            color: #fff;
            background: #0d6efd;
        }
        #content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.08);
        }
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            #sidebar.active {
                margin-left: 0;
            }
            #sidebarCollapse span {
                display: none;
            }
        }
        .table img {
            max-width: 80px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>InnovaTech Admin</h3>
            </div>
            <ul class="list-unstyled components">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="manage_users.php"><i class="fas fa-users"></i> Gérer les Utilisateurs</a></li>
                <li><a href="admin_manage_posts.php"><i class="fas fa-newspaper"></i> Gérer les Actualités/Publications</a></li>
                <li><a href="admin_manage_events.php"><i class="fas fa-calendar-alt"></i> Gérer les Événements</a></li>
                <li><a href="admin_manage_enrollments.php"><i class="fas fa-clipboard-list"></i> Gérer les Inscriptions</a></li>
                <li><a href="settings_admin.php"><i class="fas fa-cogs"></i> Paramètres du Site</a></li>
                <li><a href="admin_reports.php"><i class="fas fa-chart-line"></i> Rapports Statistiques</a></li>
                <li><a href="auth_process.php?action=logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <h2 class="ms-3 mb-0">Gestion des Utilisateurs</h2>
                </div>
            </nav>

            <main class="container-fluid mt-4">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        Liste des Utilisateurs
                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus-circle"></i> Ajouter un utilisateur
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom d'utilisateur</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Statut</th>
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
                                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                <td>
                                                    <?php if ($user['is_active']): ?>
                                                        <span class="badge bg-success">Actif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-warning btn-sm me-1" title="Modifier" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                            data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                            data-is_active="<?php echo htmlspecialchars($user['is_active']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>

                                                    <form action="manage_users.php" method="POST" class="d-inline-block">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                        <input type="hidden" name="status" value="<?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>">
                                                        <button type="submit" class="btn btn-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?> btn-sm me-1" title="<?php echo $user['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                            <i class="fas fa-power-off"></i>
                                                        </button>
                                                    </form>

                                                    <form action="manage_users.php" method="POST" class="d-inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Aucun utilisateur enregistré pour le moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">Ajouter un Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_users.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" id="role" name="role">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Actif
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_users.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Nouveau Mot de passe (laisser vide si inchangé)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="form-text text-muted">Laissez ce champ vide si vous ne souhaitez pas modifier le mot de passe.</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Rôle</label>
                            <select class="form-select" id="edit_role" name="role">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Actif
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Script pour le basculement de la barre latérale
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Script pour remplir le modal de modification
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Bouton qui a déclenché le modal
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var email = button.getAttribute('data-email');
            var role = button.getAttribute('data-role');
            var isActive = button.getAttribute('data-is_active');

            var modalId = editUserModal.querySelector('#edit_user_id');
            var modalUsername = editUserModal.querySelector('#edit_username');
            var modalEmail = editUserModal.querySelector('#edit_email');
            var modalRole = editUserModal.querySelector('#edit_role');
            var modalIsActive = editUserModal.querySelector('#edit_is_active');

            modalId.value = id;
            modalUsername.value = username;
            modalEmail.value = email;
            modalRole.value = role;
            modalIsActive.checked = (isActive == 1); // Coche la case si is_active est 1
        });
    </script>
</body>
</html>