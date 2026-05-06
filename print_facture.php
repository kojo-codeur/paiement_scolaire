<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$facture_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$facture_id) {
    die("ID facture manquant");
}

$sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire, 
        u.nom, u.prenom, u.email, u.telephone,
        t.libelle as tranche_libelle, t.date_limite,
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
    die("Facture non trouvée");
}

$montant_restant = $facture['montant_total'] - $facture['montant_paye'];
$estPayee = $montant_restant <= 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo $facture['numero_facture']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            padding: 40px;
        }
        
        .facture-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .facture-header {
            background: linear-gradient(135deg, #1e385b, #0099fa);
            padding: 35px;
            text-align: center;
            color: white;
        }
        
        .facture-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .facture-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .facture-number {
            border-radius: 30px;
            color: white;
            padding: 8px 20px;
            display: inline-block;
            margin-top: 15px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .facture-body {
            padding: 35px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e385b;
            padding-left: 12px;
            margin: 25px 0 15px 0;
        }
        
        .section-title:first-of-type {
            margin-top: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 13px;
        }
        
        .info-value {
            color: #212529;
            font-weight: 500;
            font-size: 13px;
        }
        
        .info-value strong {
            color: #1e385b;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th {
            background: #1e385b;
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }
        
        .items-table td {
            padding: 12px;
            font-size: 13px;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        
        .total-section {
            text-align: right;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .total-line {
            font-size: 14px;
            margin: 8px 0;
            color: #495057;
        }
        
        .grand-total {
            font-size: 20px;
            font-weight: 800;
            color: #1e385b;
            margin-top: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-top: 20px;
        }
        
        .status-paid {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-partial {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .facture-footer {
            background: #f8f9fa;
            padding: 25px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
        
        .facture-footer p {
            margin: 5px 0;
        }
        
        .btn-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #2a7eec, #0099fa);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-print:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .btn-print {
                display: none;
            }
            .facture-container {
                box-shadow: none;
                border-radius: 0;
            }
            .info-grid {
                break-inside: avoid;
            }
            .items-table {
                break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .facture-body {
                padding: 20px;
            }
            .facture-header {
                padding: 25px;
            }
            .facture-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="facture-container">
        <div class="facture-header">
            <h1><i class="fas fa-graduation-cap"></i> GESTION SCOLAIRE</h1>
            <p>Facture officielle de scolarité</p>
            <div class="facture-number">
                N° <?php echo htmlspecialchars($facture['numero_facture']); ?>
            </div>
        </div>
        
        <div class="facture-body">
        
            <div class="section-title">
                <i class="fas fa-user-graduate"></i> Informations de l'élève
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nom complet</span>
                    <span class="info-value"><strong><?php echo strtoupper(htmlspecialchars($facture['prenom'] . ' ' . $facture['nom'])); ?></strong></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Matricule</span>
                    <span class="info-value"><?php echo htmlspecialchars($facture['matricule']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Classe</span>
                    <span class="info-value"><?php echo htmlspecialchars($facture['classe']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Année scolaire</span>
                    <span class="info-value"><?php echo htmlspecialchars($facture['annee_scolaire']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($facture['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Téléphone</span>
                    <span class="info-value"><?php echo htmlspecialchars($facture['telephone'] ?: 'Non renseigné'); ?></span>
                </div>
            </div>
            
            <!-- Détails du paiement -->
            <div class="section-title">
                <i class="fas fa-credit-card"></i> Détails du paiement
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>DESCRIPTION</th>
                        <th>MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($facture['tranche_libelle'] ?? 'Frais de scolarité'); ?></td>
                        <td><strong><?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> USD</strong></td>
                    </tr>
                    <?php if($facture['reference_bordereau']): ?>
                    <tr>
                        <td>Référence bordereau</td>
                        <td><?php echo htmlspecialchars($facture['reference_bordereau']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($facture['date_paiement']): ?>
                    <tr>
                        <td>Date de paiement</td>
                        <td><?php echo date('d/m/Y', strtotime($facture['date_paiement'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Date d'émission</td>
                        <td><?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Totaux -->
            <div class="total-section">
                <div class="total-line"><strong>Montant total :</strong> <?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> USD</div>
                <div class="total-line"><strong>Montant payé :</strong> <?php echo number_format($facture['montant_paye'], 0, ',', ' '); ?> USD</div>
                <div class="total-line grand-total"><strong>Reste à payer :</strong> <?php echo number_format($montant_restant, 0, ',', ' '); ?> USD</div>
            </div>
            
            <!-- Statut -->
            <div style="text-align: center;">
                <?php if($estPayee): ?>
                    <div class="status-badge status-paid">
                        <i class="fas fa-check-circle"></i> FACTURE COMPLÈTEMENT PAYÉE
                    </div>
                <?php else: ?>
                    <div class="status-badge status-partial">
                        <i class="fas fa-clock"></i> PAIEMENT PARTIEL
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="facture-footer">
            <p>Merci de votre confiance ! Ce document fait office de facture officielle.</p>
            <p><i class="fas fa-phone"></i>+1 XX XX XX XX | <i class="fas fa-envelope"></i> contact@ecole.com | <i class="fas fa-globe"></i> www.ecole.com</p>
            <p> <i class="fas fa-map-marker-alt"></i>xxxxxxx </p>
            <p style="margin-top: 10px; font-size: 10px;">Document généré le <?php echo date('d/m/Y à H:i'); ?></p>
        </div>
    </div>
    
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer / Enregistrer PDF
    </button>
</body>
</html>