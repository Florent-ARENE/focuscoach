<?php
/**
 * ============================================
 * ADMINISTRATION - GESTION DES RÉSERVATIONS
 * ============================================
 */
require_once __DIR__ . '/../includes/init.php';

use App\Helpers;
use App\Settings;

// Gestion de l'authentification
$isLoggedIn = !empty($_SESSION['admin_logged_in']);
$loginError = null;

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $isLoggedIn = true;
    } else {
        $loginError = 'Mot de passe incorrect';
    }
}

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: index.php');
    exit;
}

// Charger les settings si connecté
$currentSettings = [];
if ($isLoggedIn) {
    $settings = new Settings();
    $currentSettings = $settings->getAll();
}

$pageTitle = 'Administration';
$currentPage = $_GET['page'] ?? 'bookings';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= siteConfig()['site_name'] ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php if (!$isLoggedIn): ?>
    <!-- Écran de connexion -->
    <div class="login-container">
        <div class="login-box">
            <h1 class="login-logo"><?= brandWordmark() ?></h1>
            <p class="login-subtitle">Administration</p>
            
            <?php if ($loginError): ?>
            <div class="login-error"><?= Helpers::escape($loginError) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="admin_login" value="1">
                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input type="password" class="form-input" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary login-btn">Se connecter</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Dashboard Admin -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="#" class="sidebar-logo"><?= brandWordmark() ?></a>
            <p class="sidebar-subtitle">Administration</p>
            
            <nav class="sidebar-nav">
                <a href="?page=bookings" class="nav-item <?= $currentPage === 'bookings' ? 'active' : '' ?>">
                    <span>📅</span>
                    Réservations
                    <span class="nav-badge" id="pending-count">0</span>
                </a>
                <a href="?page=settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span>⚙️</span>
                    Paramètres
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="../" class="sidebar-link" target="_blank">
                    <span>🌐</span>
                    Voir le site
                </a>
                <a href="../booking/" class="sidebar-link" target="_blank">
                    <span>📅</span>
                    Page réservation
                </a>
                <a href="?logout=1" class="sidebar-link danger">
                    <span>🚪</span>
                    Déconnexion
                </a>
            </div>
        </aside>
        
        <!-- Contenu principal -->
        <main class="admin-main">
            
            <?php if ($currentPage === 'bookings'): ?>
            <!-- ============================================ -->
            <!-- PAGE : RÉSERVATIONS -->
            <!-- ============================================ -->
            <div class="admin-page-header">
                <h1 class="page-title">Réservations</h1>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-label">En attente</span>
                        <div class="stat-icon pending">🟡</div>
                    </div>
                    <div class="stat-value" id="stat-pending">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-label">Confirmées</span>
                        <div class="stat-icon confirmed">🟢</div>
                    </div>
                    <div class="stat-value" id="stat-confirmed">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-label">Aujourd'hui</span>
                        <div class="stat-icon today">📅</div>
                    </div>
                    <div class="stat-value" id="stat-today">0</div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label class="filter-label">Statut :</label>
                    <select class="filter-select" id="filter-status">
                        <option value="">Tous</option>
                        <option value="pending">En attente</option>
                        <option value="confirmed">Confirmé</option>
                        <option value="cancelled">Annulé</option>
                        <option value="completed">Terminé</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Du :</label>
                    <input type="date" class="filter-input" id="filter-date-from">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Au :</label>
                    <input type="date" class="filter-input" id="filter-date-to">
                </div>
            </div>
            
            <!-- Tableau -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Visiteur</th>
                            <th>Service</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookings-table-body">
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-state-icon">⏳</div>
                                <p>Chargement...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php elseif ($currentPage === 'settings'): ?>
            <!-- ============================================ -->
            <!-- PAGE : PARAMÈTRES -->
            <!-- ============================================ -->
            <div class="admin-page-header">
                <h1 class="page-title">Paramètres</h1>
            </div>
            
            <div class="settings-container">
                <!-- Google Calendar -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📅 Google Calendar (Miroir)</h3>
                    </div>
                    <div class="card-body">
                        <p class="settings-description">
                            Le calendrier Google sert de <strong>miroir en lecture seule</strong> avec alertes.<br>
                            Les réservations sont automatiquement synchronisées, mais <strong>toute la gestion se fait ici</strong>.
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label">Activer la synchronisation</label>
                            <div class="toggle-container">
                                <label class="toggle">
                                    <input type="checkbox" id="google_calendar_enabled" 
                                           <?= ($currentSettings['google_calendar_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label" id="sync-status">
                                    <?= ($currentSettings['google_calendar_enabled'] ?? '0') === '1' ? 'Activée' : 'Désactivée' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="google_calendar_id">
                                ID du calendrier Google
                            </label>
                            <input type="text" class="form-input" id="google_calendar_id" 
                                   placeholder="xxxxx@group.calendar.google.com"
                                   value="<?= Helpers::escape($currentSettings['google_calendar_id'] ?? '') ?>">
                            <small class="form-help">
                                Trouvez l'ID dans : Google Calendar → Paramètres du calendrier → Intégrer le calendrier
                            </small>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>⚠️ Configuration requise :</strong>
                            <ol>
                                <li>Créer un projet sur <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                                <li>Activer l'API Google Calendar</li>
                                <li>Créer un Service Account et télécharger les credentials</li>
                                <li>Placer le fichier dans <code>config/google-credentials.json</code></li>
                                <li>Partager le calendrier avec l'email du Service Account</li>
                            </ol>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="AdminApp.saveSettings()">
                            Enregistrer
                        </button>
                    </div>
                </div>
                
                <!-- Informations générales -->
                <div class="card mt-2">
                    <div class="card-header">
                        <h3 class="card-title">🏢 Informations générales</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label" for="site_name">Nom du site</label>
                            <input type="text" class="form-input" id="site_name" 
                                   value="<?= Helpers::escape($currentSettings['site_name'] ?? SITE_NAME) ?>">
                            <small class="form-help">Utilisé dans les titres de page et les emails</small>
                        </div>
                        
                        <div class="settings-grid-2">
                            <div class="form-group">
                                <label class="form-label" for="admin_name">Prénom</label>
                                <input type="text" class="form-input" id="admin_name"
                                       value="<?= Helpers::escape($currentSettings['admin_name'] ?? ADMIN_NAME) ?>"
                                       placeholder="Renaud">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="admin_lastname">Nom de famille</label>
                                <input type="text" class="form-input" id="admin_lastname"
                                       value="<?= Helpers::escape($currentSettings['admin_lastname'] ?? '') ?>"
                                       placeholder="Dupont">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admin_activity">Activité / Profession</label>
                            <input type="text" class="form-input" id="admin_activity"
                                   value="<?= Helpers::escape($currentSettings['admin_activity'] ?? '') ?>"
                                   placeholder="Coach, préparateur mental et formateur">
                            <small class="form-help">Affichée dans la politique de confidentialité (responsable du traitement)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admin_email">Email de notification</label>
                            <input type="email" class="form-input" id="admin_email" 
                                   value="<?= Helpers::escape($currentSettings['admin_email'] ?? ADMIN_EMAIL) ?>">
                            <small class="form-help">Adresse qui reçoit les notifications de nouvelles réservations</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="admin_phone">Téléphone</label>
                            <input type="tel" class="form-input" id="admin_phone" 
                                   value="<?= Helpers::escape($currentSettings['admin_phone'] ?? '') ?>"
                                   placeholder="06 12 34 56 78">
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="AdminApp.saveSettings()">
                            Enregistrer
                        </button>
                    </div>
                </div>
                
                <!-- Informations légales (RGPD / Mentions légales) -->
                <div class="card mt-2">
                    <div class="card-header">
                        <h3 class="card-title">⚖️ Informations légales</h3>
                    </div>
                    <div class="card-body">
                        <p class="settings-description">
                            Ces informations sont utilisées automatiquement dans les pages 
                            <strong>Mentions légales</strong> et <strong>Politique de confidentialité</strong>.
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label" for="legal_status">Statut juridique</label>
                            <input type="text" class="form-input" id="legal_status" 
                                   value="<?= Helpers::escape($currentSettings['legal_status'] ?? '') ?>"
                                   placeholder="Entrepreneur individuel, SASU, SARL...">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="admin_siret">Numéro SIRET</label>
                            <input type="text" class="form-input" id="admin_siret" 
                                   value="<?= Helpers::escape($currentSettings['admin_siret'] ?? '') ?>"
                                   placeholder="123 456 789 00012">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="admin_address">Adresse professionnelle</label>
                            <input type="text" class="form-input" id="admin_address" 
                                   value="<?= Helpers::escape($currentSettings['admin_address'] ?? '') ?>"
                                   placeholder="12 rue de la Paix, 75002 Paris">
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>💡 Important :</strong> Ces champs sont obligatoires pour la conformité RGPD et les mentions légales LCEN. 
                            Tant qu'ils ne sont pas remplis, des placeholders <code>[À COMPLÉTER]</code> s'afficheront sur les pages publiques.
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="AdminApp.saveSettings()">
                            Enregistrer
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <!-- Modal détails (pour les réservations) -->
    <div class="modal-overlay" id="detail-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Détails de la réservation</h3>
                <button type="button" class="modal-close" onclick="AdminApp.closeModal()">✕</button>
            </div>
            <div class="modal-body" id="modal-body"></div>
            <div class="modal-footer" id="modal-footer"></div>
        </div>
    </div>
    
    <!-- Toast notification -->
    <div class="toast" id="toast"></div>
    
    <script src="../assets/js/admin.js"></script>
<?php endif; ?>
</body>
</html>
