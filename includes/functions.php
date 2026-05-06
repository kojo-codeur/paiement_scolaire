<?php
// Inclure les fichiers PHPMailer
require_once dirname(__DIR__) . '/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/SMTP.php';
require_once dirname(__DIR__) . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Création automatique des dossiers nécessaires
$dirs = ['factures', 'uploads', 'uploads/bordereaux'];
foreach ($dirs as $dir) {
    $path = dirname(__DIR__) . '/' . $dir;
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

function estConnecte() {
    return isset($_SESSION['user_id']);
}

function estAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function estEleve() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'eleve';
}

function genererNumeroFacture() {
    return 'FACT-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function getEleveByUserId($user_id, $pdo) {
    $sql = "SELECT e.*, u.nom, u.prenom, u.email FROM eleves e 
            JOIN utilisateurs u ON e.user_id = u.id 
            WHERE e.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getTranchesRestantes($eleve_id, $pdo) {
    $sql = "SELECT t.* FROM tranches_paiement t 
            WHERE t.annee_scolaire = (SELECT annee_scolaire FROM eleves WHERE id = ?) 
            AND t.id NOT IN (
                SELECT tranche_id FROM paiements 
                WHERE eleve_id = ? AND statut = 'valide'
            )
            AND t.statut = 'actif'
            ORDER BY t.date_limite ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eleve_id, $eleve_id]);
    return $stmt->fetchAll();
}

function getPaiementsEleve($eleve_id, $pdo) {
    $sql = "SELECT p.*, t.libelle as tranche_libelle, t.date_limite 
            FROM paiements p 
            JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE p.eleve_id = ? 
            ORDER BY p.date_paiement DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eleve_id]);
    return $stmt->fetchAll();
}

/**
 * Générer un template HTML pour l'email de facture
 */
function getFactureEmailTemplate($facture, $eleve, $paiement) {
    $montant_restant = $facture['montant_total'] - $facture['montant_paye'];
    $estPayee = $montant_restant <= 0;
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facture de scolarité</title>
        <style>
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: #f0f2f5;
                line-height: 1.6;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .email-header {
                background: linear-gradient(135deg, #4361ee, #3a0ca3);
                padding: 30px;
                text-align: center;
                color: white;
            }
            .email-header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .facture-badge {
                background: rgba(255,255,255,0.2);
                border-radius: 30px;
                padding: 8px 20px;
                display: inline-block;
                margin-top: 15px;
                font-size: 14px;
            }
            .email-body {
                padding: 30px;
            }
            .details-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 16px;
                margin: 20px 0;
                border: 1px solid #e9ecef;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid #e9ecef;
            }
            .detail-row:last-child {
                border-bottom: none;
            }
            .detail-label {
                font-weight: 600;
                color: #555;
            }
            .detail-value {
                color: #333;
                font-weight: 500;
            }
            .amount-box {
                text-align: center;
                margin: 25px 0;
            }
            .amount {
                font-size: 32px;
                font-weight: 800;
                color: #4361ee;
            }
            .status-badge {
                display: inline-block;
                padding: 8px 20px;
                border-radius: 30px;
                font-size: 14px;
                font-weight: 600;
                text-align: center;
            }
            .status-paid {
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
            }
            .status-partial {
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: white;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #4361ee, #3a0ca3);
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 40px;
                margin-top: 20px;
                font-weight: 600;
            }
            .email-footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #888;
                border-top: 1px solid #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>🎓 ÉCOLE DE GESTION SCOLAIRE</h1>
                <p>Facture officielle de scolarité</p>
                <div class="facture-badge">
                    N° FACTURE: ' . $facture['numero_facture'] . '
                </div>
            </div>
            
            <div class="email-body">
                <p>Bonjour <strong>' . htmlspecialchars($eleve['prenom']) . ' ' . htmlspecialchars($eleve['nom']) . '</strong>,</p>
                <p>Nous vous remercions pour votre paiement. Veuillez trouver ci-joint votre facture de scolarité.</p>
                
                <div class="details-box">
                    <div class="detail-row">
                        <span class="detail-label">📋 Matricule</span>
                        <span class="detail-value">' . htmlspecialchars($eleve['matricule']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">🏫 Classe</span>
                        <span class="detail-value">' . htmlspecialchars($eleve['classe']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📚 Tranche</span>
                        <span class="detail-value">' . htmlspecialchars($paiement['tranche_libelle'] ?? 'Frais de scolarité') . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💰 Référence bordereau</span>
                        <span class="detail-value">' . htmlspecialchars($paiement['reference_bordereau'] ?? 'N/A') . '</span>
                    </div>
                </div>
                
                <div class="amount-box">
                    <div class="amount">' . number_format($facture['montant_total'], 0, ',', ' ') . ' USD</div>
                    <div class="amount-label">Montant total de la facture</div>
                </div>
                
                <div style="text-align: center;">
                    ' . ($estPayee ? 
                        '<div class="status-badge status-paid">✓ FACTURE COMPLÈTEMENT PAYÉE</div>' : 
                        '<div class="status-badge status-partial">⚠ PAIEMENT PARTIEL - Reste: ' . number_format($facture['montant_restant'], 0, ',', ' ') . ' USD</div>'
                    ) . '
                </div>
                
                <div style="text-align: center;">
                    <a href="' . SITE_URL . '/dashboard.php?page=factures" class="button">📄 Voir mes factures</a>
                </div>
            </div>
            
            <div class="email-footer">
                <p><strong>École de Gestion Scolaire</strong><br>
                📞 +225 XX XX XX XX | ✉️ contact@ecole.com</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Générer un PDF professionnel (Version corrigée sans TCPDF pour éviter les erreurs)
 */
function genererFacturePDF($facture_id, $pdo) {
    // Créer dossier factures avec chemin absolu
    $facture_dir = dirname(__DIR__) . '/factures';
    if (!file_exists($facture_dir)) {
        mkdir($facture_dir, 0777, true);
    }
    
    // Récupérer les données
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire, 
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche_libelle,
            p.reference_bordereau, p.date_paiement
            FROM factures f 
            JOIN eleves e ON f.eleve_id = e.id 
            JOIN utilisateurs u ON e.user_id = u.id 
            LEFT JOIN paiements p ON f.paiement_id = p.id 
            LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$facture_id]);
    $facture = $stmt->fetch();
    
    if (!$facture) {
        return false;
    }
    
    $montant_restant = $facture['montant_total'] - $facture['montant_paye'];
    $estPayee = $montant_restant <= 0;
    
    // Générer le contenu HTML du PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Facture ' . $facture['numero_facture'] . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                line-height: 1.6;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #4361ee;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #4361ee;
                margin: 0;
                font-size: 24px;
            }
            .info-section {
                margin-bottom: 30px;
            }
            .info-title {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
                color: #4361ee;
                border-left: 3px solid #4361ee;
                padding-left: 10px;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .info-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .info-table td:first-child {
                font-weight: bold;
                width: 30%;
                background: #f5f5f5;
            }
            .facture-table {
                width: 100%;
                border-collapse: collapse;
            }
            .facture-table th {
                background: #4361ee;
                color: white;
                padding: 10px;
                text-align: left;
            }
            .facture-table td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            .total-section {
                text-align: right;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
            }
            .total-line {
                font-size: 16px;
                margin: 5px 0;
            }
            .total-line.total {
                font-size: 18px;
                font-weight: bold;
                color: #4361ee;
            }
            .footer {
                margin-top: 50px;
                text-align: center;
                font-size: 12px;
                color: #999;
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
            .status-paid {
                background: #10b981;
                color: white;
                padding: 8px 20px;
                border-radius: 30px;
                display: inline-block;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📄 ÉCOLE DE GESTION SCOLAIRE</h1>
            <h3>FACTURE DE SCOLARITÉ</h3>
            <p><strong>N° Facture:</strong> ' . $facture['numero_facture'] . '</p>
            <p><strong>Date:</strong> ' . date('d/m/Y', strtotime($facture['date_emission'])) . '</p>
        </div>
        
        <div class="info-section">
            <div class="info-title">Informations de l\'élève</div>
            <table class="info-table">
                <tr><td>Nom complet</td><td>' . strtoupper($facture['prenom'] . ' ' . $facture['nom']) . '</td></tr>
                <tr><td>Matricule</td><td>' . $facture['matricule'] . '</td></tr>
                <tr><td>Classe</td><td>' . $facture['classe'] . '</td></tr>
                <tr><td>Année scolaire</td><td>' . $facture['annee_scolaire'] . '</td></tr>
                <tr><td>Email</td><td>' . $facture['email'] . '</td></tr>
                <tr><td>Téléphone</td><td>' . ($facture['telephone'] ?? 'Non renseigné') . '</td></tr>
            </table>
        </div>
        
        <div class="info-section">
            <div class="info-title">Détails du paiement</div>
            <table class="facture-table">
                <thead>
                    <tr><th>Description</th><th>Montant</th></tr>
                </thead>
                <tbody>
                    <tr><td>' . ($facture['tranche_libelle'] ?? 'Frais de scolarité') . '</td>
                    <td>' . number_format($facture['montant_total'], 0, ',', ' ') . ' USD</td>
                    </tr>';
    
    if ($facture['reference_bordereau']) {
        $html .= '<tr><td>Référence bordereau</td><td>' . $facture['reference_bordereau'] . '</td></tr>';
    }
    
    if ($facture['date_paiement']) {
        $html .= '<tr><td>Date de paiement</td><td>' . date('d/m/Y', strtotime($facture['date_paiement'])) . '</td></tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="total-section">
            <div class="total-line"><strong>Montant total:</strong> ' . number_format($facture['montant_total'], 0, ',', ' ') . ' USD</div>
            <div class="total-line"><strong>Montant payé:</strong> ' . number_format($facture['montant_paye'], 0, ',', ' ') . ' USD</div>
            <div class="total-line total"><strong>Reste à payer:</strong> ' . number_format($facture['montant_restant'], 0, ',', ' ') . ' USD</div>
        </div>';
    
    if ($estPayee) {
        $html .= '<div style="text-align: center; margin-top: 20px;">
                    <span class="status-paid">✓ FACTURE COMPLÈTEMENT PAYÉE</span>
                  </div>';
    }
    
    $html .= '<div class="footer">
                <p>Merci de votre confiance ! Ce document fait office de facture officielle.</p>
                <p>École de Gestion Scolaire - Tel: +225 XX XX XX XX - Email: contact@ecole.com</p>
              </div>
    </body>
    </html>';
    
    // Sauvegarder en HTML (compatible avec tous les navigateurs)
    $filename = 'factures/facture_' . $facture['numero_facture'] . '.html';
    $full_path = dirname(__DIR__) . '/' . $filename;
    
    // Écrire le fichier
    if (file_put_contents($full_path, $html) === false) {
        error_log("Erreur: Impossible d'écrire le fichier " . $full_path);
        return false;
    }
    
    // Mettre à jour le chemin dans la base
    $sql = "UPDATE factures SET pdf_path = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filename, $facture_id]);
    
    return $filename;
}

/**
 * Envoyer un email avec PHPMailer
 */
function envoyerFactureParEmail($facture_id, $pdo) {
    // Récupérer les données
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire, 
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche_libelle,
            p.reference_bordereau, p.date_paiement
            FROM factures f 
            JOIN eleves e ON f.eleve_id = e.id 
            JOIN utilisateurs u ON e.user_id = u.id 
            LEFT JOIN paiements p ON f.paiement_id = p.id 
            LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$facture_id]);
    $facture = $stmt->fetch();
    
    if (!$facture) {
        return false;
    }
    
    // Vérifier si le fichier existe, sinon le générer
    $file_path = dirname(__DIR__) . '/' . $facture['pdf_path'];
    if (!$facture['pdf_path'] || !file_exists($file_path)) {
        $pdf_path = genererFacturePDF($facture_id, $pdo);
        if (!$pdf_path) {
            return false;
        }
        $facture['pdf_path'] = $pdf_path;
        $file_path = dirname(__DIR__) . '/' . $pdf_path;
    }
    
    // Préparer les données pour le template
    $eleve = [
        'nom' => $facture['nom'],
        'prenom' => $facture['prenom'],
        'email' => $facture['email'],
        'matricule' => $facture['matricule'],
        'classe' => $facture['classe'],
        'annee_scolaire' => $facture['annee_scolaire']
    ];
    
    $paiement = [
        'tranche_libelle' => $facture['tranche_libelle'] ?? 'Frais de scolarité',
        'reference_bordereau' => $facture['reference_bordereau'] ?? null,
        'date_paiement' => $facture['date_paiement'] ?? null
    ];
    
    // Générer le template HTML
    $message_html = getFactureEmailTemplate($facture, $eleve, $paiement);
    
    // Envoyer avec PHPMailer
    $mailer = new Mailer();
    $result = $mailer->sendEmailWithAttachment(
        $facture['email'],
        $facture['prenom'] . ' ' . $facture['nom'],
        'Votre facture de scolarité N° ' . $facture['numero_facture'],
        $message_html,
        $file_path
    );
    
    if ($result) {
        // Mettre à jour le statut d'envoi
        $sql = "UPDATE factures SET envoye_email = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$facture_id]);
        return true;
    }
    
    return false;
}

/**
 * Créer une facture après validation d'un paiement
 */
function creerFactureApresValidation($paiement_id, $pdo) {
    // Récupérer les informations du paiement
    $sql = "SELECT p.*, e.user_id, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email,
            t.libelle as tranche_libelle, t.montant as tranche_montant
            FROM paiements p
            JOIN eleves e ON p.eleve_id = e.id
            JOIN utilisateurs u ON e.user_id = u.id
            JOIN tranches_paiement t ON p.tranche_id = t.id
            WHERE p.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement_id]);
    $paiement = $stmt->fetch();
    
    if (!$paiement) {
        return false;
    }
    
    // Vérifier si une facture existe déjà pour ce paiement
    $sql = "SELECT id FROM factures WHERE paiement_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        return $existing['id'];
    }
    
    // Calculer le total payé pour cet élève
    $sql = "SELECT SUM(montant) as total_paye FROM paiements 
            WHERE eleve_id = ? AND statut = 'valide'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement['eleve_id']]);
    $total_paye = $stmt->fetch()['total_paye'] ?? 0;
    
    // Calculer le total à payer pour l'année
    $sql = "SELECT SUM(montant) as total_a_payer FROM tranches_paiement 
            WHERE annee_scolaire = ? AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement['annee_scolaire']]);
    $total_a_payer = $stmt->fetch()['total_a_payer'] ?? 0;
    
    $reste_a_payer = $total_a_payer - $total_paye;
    
    // Générer le numéro de facture
    $numero_facture = genererNumeroFacture();
    
    // Insérer la facture
    $sql = "INSERT INTO factures (eleve_id, paiement_id, numero_facture, montant_total, montant_paye, montant_restant, date_emission)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $paiement['eleve_id'],
        $paiement_id,
        $numero_facture,
        $paiement['tranche_montant'],
        $paiement['montant'],
        $reste_a_payer
    ]);
    
    if ($result) {
        $facture_id = $pdo->lastInsertId();
        
        // Générer le fichier (HTML)
        $file_path = genererFacturePDF($facture_id, $pdo);
        
        // Envoyer l'email
        envoyerFactureParEmail($facture_id, $pdo);
        
        return $facture_id;
    }
    
    return false;
}


// Définir SITE_URL s'il n'est pas défini
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/paiement_scolaire');
}


if (!defined('APP_NOM')) {
    define('APP_NOM', 'Gestion Scolaire');
}


?>