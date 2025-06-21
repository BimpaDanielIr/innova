<?php
session_start();

// Protection de la page : vérifie que l'utilisateur est connecté ET qu'il a le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    if (isset($_SESSION['user_id'])) {
        header("Location: dashboard.php"); // Redirige un utilisateur connecté non-admin
    } else {
        header("Location: authentification.php"); // Redirige un utilisateur non connecté
    }
    exit();
}

// Inclure le fichier de configuration de la base de données
// Assurez-vous que ce fichier initialise la connexion PDO dans une variable $pdo
require_once 'config.php';

// Initialisation des messages pour éviter les warnings
$error_message = '';
$success_message = '';

// Récupère les messages d'erreur/succès de la session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Générer un token CSRF si non existant (préventif pour les actions futures de suppression/marquage)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$contacts = []; // Pour stocker les messages de contact

try {
    // Utilisation de PDO pour la requête
    // La capture 'contacts.png' montre une table 'contacts' avec id, name, email, subject, message, submission_date
    $stmt = $pdo->prepare("SELECT id, name, email, subject, message, submission_date FROM contacts ORDER BY submission_date DESC");
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    // En production, logger l'erreur et afficher un message générique.
    $_SESSION['error_message'] = $error_message;
    header("Location: admin_dashboard.php"); // Redirection en cas d'erreur critique
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Messages de Contact</title>
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
                <li>
                    <a href="#blogSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-newspaper"></i> Gestion Articles & Blog</a>
                    <ul class="collapse list-unstyled" id="blogSubmenu">
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
                <li class="active">
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
                        <h1 class="h3 mb-0">Messages de Contact</h1>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($contacts)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Sujet</th>
                                        <th>Message</th>
                                        <th>Date de soumission</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contact['id']); ?></td>
                                        <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                        <td><?php echo htmlspecialchars($contact['subject']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($contact['message'])); ?></td>
                                        <td><?php echo htmlspecialchars($contact['submission_date']); ?></td>
                                        <td>
                                            <form action="admin_contact_process.php" method="POST" class="d-inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact['id']); ?>">
                                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce message de contact ?');" title="Supprimer">
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
                            <p class="text-center alert alert-info">Aucun message de contact pour le moment.</p>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="admin_dashboard.php" class="btn btn-secondary">Retour au Tableau de Bord Admin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <p>&copy; 2025 InnovaTech. Tous droits réservés.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Script pour le basculement de la barre latérale
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>