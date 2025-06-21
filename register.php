<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = '';
$success_message = '';
$trainings = [];

// Récupérer la liste des formations actives pour la liste déroulante
$sql_trainings = "SELECT training_id, training_name FROM trainings WHERE is_active = TRUE ORDER BY training_name";
$result_trainings = $conn->query($sql_trainings);
if ($result_trainings) {
    while ($row = $result_trainings->fetch_assoc()) {
        $trainings[] = $row;
    }
} else {
    $error_message .= "Erreur lors de la récupération des formations : " . $conn->error . "<br>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'user'; // Par défaut 'user', l'admin peut changer
    $training_id = $_POST['training_id'] ?? null; // ID de la formation sélectionnée

    // Validation des entrées
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Vérifier si l'utilisateur ou l'email existe déjà
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = "Ce nom d'utilisateur ou cet email est déjà pris.";
        } else {
            // Hacher le mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insérer le nouvel utilisateur
            // Utilisation d'un opérateur ternaire pour gérer le cas où training_id est null
            $sql_insert = "INSERT INTO users (username, email, password_hash, role, training_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            
            // Si training_id est null, bind_param doit utiliser 'i' pour null
            if ($training_id === '') { // Gérer le cas où rien n'est sélectionné dans le dropdown
                $training_id = null;
                $stmt_insert->bind_param("ssssi", $username, $email, $hashed_password, $role, $training_id);
            } else {
                $stmt_insert->bind_param("ssssi", $username, $email, $hashed_password, $role, $training_id);
            }
            
            if ($stmt_insert->execute()) {
                $success_message = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                // Redirection après succès si l'admin ajoute un utilisateur
                if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                    $_SESSION['success_message'] = "Utilisateur '{$username}' ajouté avec succès !";
                    header("Location: admin_dashboard.php#users");
                    exit();
                }
                 // Redirection pour l'inscription normale
                header("Location: authentification.php");
                exit();
            } else {
                $error_message = "Erreur lors de l'inscription : " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - InnovaTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/logo.png" alt="InnovaTech Logo" width="30" height="30" class="d-inline-block align-text-top me-2">
                InnovaTech
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Profil</a></li>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Tableau de Bord Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="authentification.php">Connexion</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="inscription_formation.php">Formations</a></li>
                    <li class="nav-item"><a class="nav-link" href="assistance.php">Assistance</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3 class="mb-0">Inscription</h3>
                    </div>
                    <div class="card-body p-4">
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

                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <div class="mb-3">
                                <label for="role" class="form-label">Rôle (pour l'administrateur)</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="user" <?php echo (($_POST['role'] ?? '') == 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="admin" <?php echo (($_POST['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="training_id" class="form-label">Sélectionner une formation</label>
                                <select class="form-select" id="training_id" name="training_id">
                                    <option value="">-- Aucune formation --</option>
                                    <?php foreach ($trainings as $training): ?>
                                        <option value="<?php echo htmlspecialchars($training['training_id']); ?>"
                                            <?php echo (($_POST['training_id'] ?? '') == $training['training_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($training['training_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">S'inscrire</button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            Déjà un compte ? <a href="authentification.php">Connectez-vous ici</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>