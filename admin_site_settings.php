<?php
session_start();
require_once 'config.php'; // Assurez-vous que le chemin est correct

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

$message = '';
$message_type = '';
$site_settings = [];

// Récupérer les messages de session
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
    // Récupérer les paramètres du site depuis la base de données
    // Supposons une table 'site_settings' avec des colonnes 'setting_name' et 'setting_value'
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM site_settings");
    $settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($settings_raw as $row) {
        $site_settings[$row['setting_name']] = $row['setting_value'];
    }

    // Traitement de la soumission du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification du token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur CSRF : Jeton invalide. Veuillez réessayer.");
        }

        // Récupérer les données soumises
        $site_name = trim($_POST['site_name'] ?? '');
        $site_description = trim($_POST['site_description'] ?? '');
        $contact_email = filter_var(trim($_POST['contact_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $facebook_url = filter_var(trim($_POST['facebook_url'] ?? ''), FILTER_VALIDATE_URL);
        $twitter_url = filter_var(trim($_POST['twitter_url'] ?? ''), FILTER_VALIDATE_URL);
        $linkedin_url = filter_var(trim($_POST['linkedin_url'] ?? ''), FILTER_VALIDATE_URL);

        // Validation basique (peut être étendue)
        if (empty($site_name) || empty($site_description) || empty($contact_email)) {
            throw new Exception("Le nom du site, la description et l'email de contact sont obligatoires.");
        }
        if ($contact_email === false) {
            throw new Exception("L'adresse email de contact est invalide.");
        }

        // Préparer la mise à jour des paramètres
        // Utiliser UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) si site_settings a une clé unique sur setting_name
        // Sinon, faire un UPDATE ou INSERT conditionnel
        $pdo->beginTransaction();

        $settings_to_update = [
            'site_name' => $site_name,
            'site_description' => $site_description,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'address' => $address,
            'facebook_url' => $facebook_url,
            'twitter_url' => $twitter_url,
            'linkedin_url' => $linkedin_url,
        ];

        $stmt_update = $pdo->prepare("INSERT INTO site_settings (setting_name, setting_value) VALUES (:setting_name, :setting_value) ON DUPLICATE KEY UPDATE setting_value = :setting_value");

        foreach ($settings_to_update as $name => $value) {
            $stmt_update->execute([':setting_name' => $name, ':setting_value' => $value]);
            $site_settings[$name] = $value; // Mettre à jour les données affichées immédiatement
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Paramètres du site mis à jour avec succès !";
        header("Location: admin_site_settings.php"); // Rediriger pour effacer les données POST
        exit();

    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'danger';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Paramètres du Site</title>
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
                            <li class="nav-item active">
                                <a class="nav-link" href="admin_site_settings.php">Paramètres du Site</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <main class="container-fluid">
                <h1 class="mb-4">Gestion des Paramètres du Site</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Informations Générales du Site</h5>
                    </div>
                    <div class="card-body">
                        <form action="admin_site_settings.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="mb-3">
                                <label for="site_name" class="form-label">Nom du Site :</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_settings['site_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="site_description" class="form-label">Description du Site :</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3" required><?php echo htmlspecialchars($site_settings['site_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Email de Contact :</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($site_settings['contact_email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Téléphone de Contact :</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($site_settings['contact_phone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Adresse :</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($site_settings['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="facebook_url" class="form-label">URL Facebook :</label>
                                <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($site_settings['facebook_url'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="twitter_url" class="form-label">URL Twitter :</label>
                                <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($site_settings['twitter_url'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="linkedin_url" class="form-label">URL LinkedIn :</label>
                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($site_settings['linkedin_url'] ?? ''); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les Paramètres</button>
                        </form>
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