<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = null;
$user_id = $_SESSION['user_id'] ?? null;
$is_user_enrolled = false; // Pour vérifier si l'utilisateur est déjà inscrit

$error_message = '';
$success_message = '';

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    if (!$event_id) {
        throw new Exception("ID d'événement invalide.");
    }

    // Récupérer les détails de l'événement
    $stmt = $pdo->prepare("SELECT id, title, description, event_date, start_time, end_time, location, speaker, price, capacity, image_path FROM events WHERE id = :id AND is_active = 1");
    $stmt->bindParam(':id', $event_id, PDO::PARAM_INT);
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception("Événement non trouvé ou non actif.");
    }

    // Vérifier si l'utilisateur est déjà inscrit à cet événement
    if ($user_id) {
        $stmt_check_enrollment = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = :user_id AND item_id = :event_id AND item_type = 'event'");
        $stmt_check_enrollment->execute([':user_id' => $user_id, ':event_id' => $event_id]);
        if ($stmt_check_enrollment->fetchColumn() > 0) {
            $is_user_enrolled = true;
            $success_message = "Vous êtes déjà inscrit(e) à cet événement.";
        }
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans event_details.php: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des détails de l'événement. Veuillez réessayer.";
} catch (Exception $e) {
    $error_message = $e->getMessage();
    // Si l'événement n'est pas trouvé, rediriger l'utilisateur
    if ($e->getMessage() === "Événement non trouvé ou non actif.") {
        $_SESSION['error_message'] = $error_message;
        header("Location: events.php");
        exit();
    }
}

// Assurez-vous que l'image par défaut est utilisée si image_path est vide
$event_image_path = $event['image_path'] ?? 'assets/images/default_event_details.jpg'; // Utilisez une image par défaut plus grande si vous voulez
if (!file_exists($event_image_path) || is_dir($event_image_path)) {
    $event_image_path = 'assets/images/default_event_details.jpg';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Détails de l'événement : <?php echo htmlspecialchars($event['title'] ?? 'Événement non trouvé'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css"> <style>
        /* Styles personnalisés pour la page de détails de l'événement */
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        .event-details-container {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .event-details-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .event-details-title {
            font-size: 2.8rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 20px;
            text-align: center;
        }
        .event-details-meta {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 25px;
            text-align: center;
        }
        .event-details-meta span {
            display: inline-block;
            margin: 0 15px;
            font-weight: 500;
        }
        .event-details-meta i {
            color: #007bff;
            margin-right: 8px;
        }
        .event-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #444;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .event-speaker-info {
            background-color: #e9f5ff;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            border-left: 5px solid #007bff;
        }
        .event-speaker-info h4 {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .event-price-box {
            background-color: #d4edda; /* Vert clair pour le prix */
            border: 1px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
        }
        .event-price-box .price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745; /* Vert */
            margin-bottom: 10px;
        }
        .event-price-box .price small {
            font-size: 1.2rem;
            color: #555;
        }
        .btn-register-event {
            background-color: #28a745; /* Vert */
            border-color: #28a745;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        .btn-register-event:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-back-events {
            background-color: #6c757d; /* Gris */
            border-color: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        .btn-back-events:hover {
            background-color: #5a6268;
            border-color: #4e555b;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; // Incluez votre en-tête commun ?>

    <main class="container py-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success text-center alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($event): ?>
            <div class="event-details-container">
                <div class="text-center mb-5">
                    <img src="<?php echo htmlspecialchars($event_image_path); ?>" class="img-fluid event-details-image" alt="<?php echo htmlspecialchars($event['title']); ?>">
                    <h1 class="event-details-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div class="event-details-meta">
                        <span><i class="far fa-calendar-alt"></i> Date : <?php echo date("d/m/Y", strtotime($event['event_date'])); ?></span>
                        <span><i class="far fa-clock"></i> Heure : <?php echo date("H:i", strtotime($event['start_time'])); ?> - <?php echo date("H:i", strtotime($event['end_time'])); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> Lieu : <?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <h3 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i> Description de l'événement</h3>
                        <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>

                        <?php if (!empty($event['speaker'])): ?>
                            <div class="event-speaker-info">
                                <h4><i class="fas fa-microphone-alt me-2"></i> Intervenant :</h4>
                                <p><?php echo nl2br(htmlspecialchars($event['speaker'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="sticky-top" style="top: 20px;">
                            <div class="event-price-box">
                                <p class="price">
                                    <?php echo ($event['price'] > 0) ? htmlspecialchars($event['price']) . ' FCFA' : 'Gratuit'; ?>
                                    <?php if ($event['price'] > 0): ?><br><small>Prix par participant</small><?php endif; ?>
                                </p>
                                <p class="text-muted">Places disponibles : <?php echo htmlspecialchars($event['capacity']); ?></p>

                                <?php if ($is_user_enrolled): ?>
                                    <button class="btn btn-success btn-register-event" disabled>
                                        <i class="fas fa-check-circle me-2"></i> Déjà inscrit
                                    </button>
                                <?php elseif ($user_id): // Utilisateur connecté et non inscrit ?>
                                    <form action="enroll_process.php" method="POST">
                                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                        <input type="hidden" name="item_type" value="event">
                                        <?php if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-register-event">
                                            <i class="fas fa-user-plus me-2"></i> S'inscrire à cet événement
                                        </button>
                                    </form>
                                <?php else: // Utilisateur non connecté ?>
                                    <p class="text-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i> Pour vous inscrire, veuillez vous connecter.
                                    </p>
                                    <a href="authentification.php" class="btn btn-register-event">
                                        <i class="fas fa-sign-in-alt me-2"></i> Se connecter pour s'inscrire
                                    </a>
                                <?php endif; ?>

                            </div>
                            <a href="events.php" class="btn btn-back-events mt-3"><i class="fas fa-arrow-left me-2"></i> Retour aux événements</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; // Incluez votre pied de page commun ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>