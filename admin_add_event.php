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

$error_message = '';
$success_message = '';
$old_event_data = []; // Pour pré-remplir le formulaire en cas d'erreur

// Récupère les messages d'erreur/succès de la session et les données anciennes
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['old_event_data'])) {
    $old_event_data = $_SESSION['old_event_data'];
    unset($_SESSION['old_event_data']);
}

// Si on vient d'une modification échouée, les données seront déjà dans $_SESSION['old_event_data']
// Si on veut ajouter un nouvel événement, le formulaire sera vide par défaut

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Ajouter un Événement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .wrapper {
            flex: 1;
            display: flex;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
            padding-top: 20px;
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #343a40;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul p {
            color: #fff;
            padding: 10px;
        }
        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
        }
        #sidebar ul li a:hover {
            color: #1a2a4b;
            background: #fff;
        }
        #content {
            flex: 1;
            padding: 20px;
        }
        .navbar {
            padding: 15px 10px;
            background: #fff;
            border: none;
            border-radius: 0;
            margin-bottom: 40px;
            box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Dashboard</h3>
            </div>
            <ul class="list-unstyled components">
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Tableau de Bord</a></li>
                <li><a href="admin_manage_users.php"><i class="fas fa-users"></i> Gérer les Utilisateurs</a></li>
                <li><a href="admin_manage_posts.php"><i class="fas fa-newspaper"></i> Gérer les Publications</a></li>
                <li class="active"><a href="admin_manage_events.php"><i class="fas fa-calendar-alt"></i> Gérer les Événements</a></li>
                <li><a href="admin_manage_formations.php"><i class="fas fa-graduation-cap"></i> Gérer les Formations</a></li>
                <li><a href="admin_manage_enrollments.php"><i class="fas fa-user-graduate"></i> Gérer les Inscriptions</a></li>
                <li><a href="admin_finances.php"><i class="fas fa-dollar-sign"></i> Gérer les Finances</a></li>
                <li><a href="admin_reports.php"><i class="fas fa-chart-pie"></i> Rapports et Statistiques</a></li>
                <li><a href="admin_contacts.php"><i class="fas fa-envelope"></i> Messages de Contact</a></li>
                <li><a href="admin_assistance.php"><i class="fas fa-life-ring"></i> Demandes d'Assistance</a></li>
                <li><a href="admin_site_settings.php"><i class="fas fa-cog"></i> Paramètres du Site</a></li>
                <li><a href="auth_process.php?action=logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Toggle Sidebar</span>
                    </button>
                    <div class="ms-auto">
                        Bonjour, <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <h2 class="mb-4">Ajouter un Nouvel Événement</h2>

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

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        Formulaire d'ajout d'événement
                    </div>
                    <div class="card-body">
                        <form action="admin_event_process.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

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
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo htmlspecialchars($old_event_data['event_date'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="event_time" class="form-label">Heure de l'événement :</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" value="<?php echo htmlspecialchars($old_event_data['event_time'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lieu :</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($old_event_data['location'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Image de l'événement :</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="form-text text-muted">Taille maximale : 5 Mo. Formats acceptés : JPG, PNG, GIF.</small>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo (isset($old_event_data['is_active']) && $old_event_data['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Activer l'événement (visible sur le site)</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Ajouter l'événement</button>
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