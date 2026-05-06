<?php
require_once 'includes/functions.php';
require_once 'includes/Mailer.php'; 

$message = '';
$error = '';

function creerFactureDansBD($paiement_id, $pdo) {

    $sql = "SELECT p.*, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email, u.telephone,
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
        error_log("creerFactureDansBD: Paiement non trouvé ID: " . $paiement_id);
        return false;
    }
    
    $sql = "SELECT id, envoye_email FROM factures WHERE paiement_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        return ['facture_id' => $existing['id'], 'envoye_email' => $existing['envoye_email']];
    }
    
    $sql = "SELECT SUM(montant) as total FROM paiements WHERE eleve_id = ? AND statut = 'valide'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement['eleve_id']]);
    $total_paye = $stmt->fetch()['total'] ?? 0;
    
    $sql = "SELECT SUM(montant) as total FROM tranches_paiement WHERE annee_scolaire = ? AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement['annee_scolaire']]);
    $total_a_payer = $stmt->fetch()['total'] ?? 0;
    
    $reste_a_payer = $total_a_payer - $total_paye;
    
    $numero_facture = 'FACT-' . date('Y') . '-' . str_pad($paiement_id, 6, '0', STR_PAD_LEFT);
    
    $sql = "INSERT INTO factures (eleve_id, paiement_id, numero_facture, montant_total, montant_paye, montant_restant, date_emission, envoye_email)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement['eleve_id'], $paiement_id, $numero_facture, $paiement['tranche_montant'], $paiement['montant'], $reste_a_payer]);
    $facture_id = $pdo->lastInsertId();
    
    return ['facture_id' => $facture_id, 'envoye_email' => 0];
}

function traiterValidationPaiement($paiement_id, $pdo) {

    $result = creerFactureDansBD($paiement_id, $pdo);
    
    if (!$result || !$result['facture_id']) {
        error_log("traiterValidationPaiement: Échec création facture pour paiement ID: " . $paiement_id);
        return false;
    }
    
    $facture_id = $result['facture_id'];
    
    
    if ($result['envoye_email'] == 1) {
        return [
            'facture_id' => $facture_id,
            'numero_facture' => null,
            'email_envoye' => true,
            'deja_envoye' => true
        ];
    }
    
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche_libelle,
            p.montant as paiement_montant
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
    
    $pdf_path = genererFacturePDF($facture_id, $pdo);
    
    if (!$pdf_path) {
        error_log("traiterValidationPaiement: Échec génération PDF pour facture ID: " . $facture_id);
        return false;
    }
    
    
    $sql = "UPDATE factures SET pdf_path = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pdf_path, $facture_id]);
    
    $factureData = [
        'numero_facture' => $facture['numero_facture'],
        'montant_total' => $facture['montant_total'],
        'montant_paye' => $facture['montant_paye'],
        'montant_restant' => $facture['montant_restant'],
        'matricule' => $facture['matricule'],
        'classe' => $facture['classe'],
        'annee_scolaire' => $facture['annee_scolaire'],
        'details' => [
            [
                'description' => $facture['tranche_libelle'] ?? 'Frais de scolarité',
                'prix_unitaire' => $facture['montant_total'],
                'quantite' => 1,
                'total' => $facture['montant_total']
            ]
        ]
    ];
    
    $file_path = dirname(__DIR__) . '/' . $pdf_path;
    
    $email_sent = sendFactureEmail(
        $facture['email'],
        $facture['prenom'] . ' ' . $facture['nom'],
        $factureData,
        $file_path
    );
    
    if ($email_sent) {
        $sql = "UPDATE factures SET envoye_email = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$facture_id]);
    }
    
    return [
        'facture_id' => $facture_id,
        'numero_facture' => $facture['numero_facture'],
        'email_envoye' => $email_sent
    ];
}

if (isset($_GET['valider'])) {
    $id = $_GET['valider'];
    
    $pdo->beginTransaction();
    
    try {

        $sql = "UPDATE paiements SET statut = 'valide', date_validation = NOW(), valide_par = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        $result = traiterValidationPaiement($id, $pdo);
        
        if ($result) {
            $pdo->commit();
            if (isset($result['deja_envoye']) && $result['deja_envoye']) {
                $message = "Paiement validé avec succès. La facture existante a été utilisée.";
            } else if ($result['email_envoye']) {
                $message = "Paiement validé avec succès. Facture N° " . $result['numero_facture'] . " générée et envoyée par email à l'élève.";
            } else {
                $message = "Paiement validé avec succès. Facture N° " . $result['numero_facture'] . " générée mais l'envoi de l'email a échoué. Vous pouvez la renvoyer manuellement.";
            }
        } else {
            throw new Exception("Erreur lors de la création de la facture");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}


if (isset($_GET['rejeter'])) {
    $id = $_GET['rejeter'];
    $commentaire = isset($_GET['commentaire']) ? $_GET['commentaire'] : 'Paiement non conforme';
    
    $sql = "UPDATE paiements SET statut = 'rejete', commentaire = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$commentaire, $id])) {
        $message = "Paiement rejeté";
    } else {
        $error = "Erreur lors du rejet";
    }
}

if (isset($_GET['renvoyer_email'])) {
    $paiement_id = $_GET['renvoyer_email'];
    
    $sql = "SELECT f.id FROM factures f WHERE f.paiement_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement_id]);
    $facture = $stmt->fetch();
    
    if ($facture) {

        $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire,
                u.nom, u.prenom, u.email, u.telephone,
                t.libelle as tranche_libelle
                FROM factures f 
                JOIN eleves e ON f.eleve_id = e.id 
                JOIN utilisateurs u ON e.user_id = u.id 
                LEFT JOIN tranches_paiement t ON f.paiement_id = t.id 
                WHERE f.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$facture['id']]);
        $factureData = $stmt->fetch();
        
        if ($factureData) {
    
            $file_path = dirname(__DIR__) . '/' . $factureData['pdf_path'];
            if (!file_exists($file_path)) {

                $pdf_path = genererFacturePDF($factureData['id'], $pdo);
                if ($pdf_path) {
                    $file_path = dirname(__DIR__) . '/' . $pdf_path;
                }
            }
            
            $factureEmailData = [
                'numero_facture' => $factureData['numero_facture'],
                'montant_total' => $factureData['montant_total'],
                'montant_paye' => $factureData['montant_paye'],
                'montant_restant' => $factureData['montant_restant'],
                'matricule' => $factureData['matricule'],
                'classe' => $factureData['classe'],
                'annee_scolaire' => $factureData['annee_scolaire'],
                'details' => [
                    [
                        'description' => $factureData['tranche_libelle'] ?? 'Frais de scolarité',
                        'prix_unitaire' => $factureData['montant_total'],
                        'quantite' => 1,
                        'total' => $factureData['montant_total']
                    ]
                ]
            ];
            
            $email_sent = sendFactureEmail(
                $factureData['email'],
                $factureData['prenom'] . ' ' . $factureData['nom'],
                $factureEmailData,
                $file_path
            );
            
            if ($email_sent) {
                $sql = "UPDATE factures SET envoye_email = 1 WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$factureData['id']]);
                $message = "Facture renvoyée par email avec succès";
            } else {
                $error = "Erreur lors de l'envoi de l'email";
            }
        }
    }
}

$sql = "SELECT p.*, e.matricule, e.classe, u.nom, u.prenom, u.email, t.libelle as tranche 
        FROM paiements p 
        JOIN eleves e ON p.eleve_id = e.id 
        JOIN utilisateurs u ON e.user_id = u.id 
        JOIN tranches_paiement t ON p.tranche_id = t.id 
        WHERE p.statut = 'en_attente' 
        ORDER BY p.date_paiement DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$paiements_attente = $stmt->fetchAll();

// Récupérer les paiements récemment validés
$sql = "SELECT p.*, e.matricule, e.classe, u.nom, u.prenom, t.libelle as tranche,
        f.numero_facture, f.pdf_path, f.envoye_email
        FROM paiements p 
        JOIN eleves e ON p.eleve_id = e.id 
        JOIN utilisateurs u ON e.user_id = u.id 
        JOIN tranches_paiement t ON p.tranche_id = t.id 
        LEFT JOIN factures f ON p.id = f.paiement_id
        WHERE p.statut = 'valide' 
        ORDER BY p.date_validation DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$paiements_valides = $stmt->fetchAll();

$total_attente = count($paiements_attente);
?>



<div class="validation-stats">
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">En attente</span>
            <div class="stat-icon-premium">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-value-premium" style="color: var(--warning);"><?php echo $total_attente; ?></div>
    </div>
    
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Validés ce mois</span>
            <div class="stat-icon-premium">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value-premium" style="color: var(--success);"><?php echo count($paiements_valides); ?></div>
    </div>
    
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Factures envoyées</span>
            <div class="stat-icon-premium">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
        <div class="stat-value-premium" style="color: var(--primary);">
            <?php echo count(array_filter($paiements_valides, function($p) { return $p['envoye_email'] == 1; })); ?>
        </div>
    </div>
</div>

<!-- Paiements en attente -->
<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-clock"></i> Paiements en attente de validation</h3>
        <span class="status-badge-premium en_attente"><?php echo $total_attente; ?> en attente</span>
    </div>
    <div class="card-body-premium">
        <?php if(count($paiements_attente) > 0): ?>
            <?php foreach($paiements_attente as $paiement): ?>
                <div class="payment-card">
                    <div class="row-premium p-3 align-items-center">
                        <div class="col-premium-3">
                            <strong><?php echo htmlspecialchars($paiement['prenom'] . ' ' . $paiement['nom']); ?></strong>
                            <div class="small text-muted">Matricule: <?php echo $paiement['matricule']; ?></div>
                            <div class="small text-muted">Classe: <?php echo $paiement['classe']; ?></div>
                        </div>
                        <div class="col-premium-2">
                            <div class="fw-bold"><?php echo htmlspecialchars($paiement['tranche']); ?></div>
                            <div class="small text-muted">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?>
                            </div>
                        </div>
                        <div class="col-premium-2">
                            <div class="fw-bold text-primary"><?php echo number_format($paiement['montant'], 0, ',', ' '); ?> USD</div>
                            <div class="small text-muted">Réf: <?php echo $paiement['reference_bordereau']; ?></div>
                        </div>
                        <div class="col-premium-2">
                            <?php if($paiement['fichier_bordereau'] && file_exists($paiement['fichier_bordereau'])): ?>
                                <a href="<?php echo $paiement['fichier_bordereau']; ?>" target="_blank" class="btn-premium btn-premium-sm" style="background: var(--info); color: white;">
                                    <i class="fas fa-file-alt"></i> Voir bordereau
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Aucun fichier</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-premium-3">
                            <div class="action-buttons-group">
                                <a href="?page=validation&valider=<?php echo $paiement['id']; ?>" 
                                   class="btn-premium btn-premium-success btn-premium-sm"
                                   onclick="return confirm('Valider ce paiement ? La facture sera générée automatiquement et envoyée par email à l\'élève.')">
                                    <i class="fas fa-check"></i> Valider
                                </a>
                                <button class="btn-premium btn-premium-danger btn-premium-sm" 
                                        onclick="rejeterPaiement(<?php echo $paiement['id']; ?>)">
                                    <i class="fas fa-times"></i> Rejeter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state-premium">
                <i class="fas fa-check-circle" style="color: var(--success); font-size: 3rem;"></i>
                <p>Aucun paiement en attente</p>
                <p class="text-muted">Tous les paiements ont été traités</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card-premium mt-3">
    <div class="card-header-premium">
        <h3><i class="fas fa-history"></i> Paiements récemment validés</h3>
        <div class="export-btn" onclick="exportValidations()">
            <i class="fas fa-download"></i> Exporter
        </div>
    </div>
    <div class="card-body-premium">
        <?php if(count($paiements_valides) > 0): ?>
            <div class="table-responsive-premium">
                <table class="data-table-premium">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Tranche</th>
                            <th>Montant</th>
                            <th>N° Facture</th>
                            <th>Statut email</th>
                            <th>Date validation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($paiements_valides as $paiement): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($paiement['prenom'] . ' ' . $paiement['nom']); ?></strong>
                                    <div class="small text-muted"><?php echo $paiement['matricule']; ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($paiement['tranche']); ?></td>
                                <td><strong class="text-primary"><?php echo number_format($paiement['montant'], 0, ',', ' '); ?> USD</strong></td>
                                <td>
                                    <?php if($paiement['numero_facture']): ?>
                                        <span class="facture-badge">
                                            <i class="fas fa-file-pdf"></i> <?php echo $paiement['numero_facture']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($paiement['envoye_email']): ?>
                                        <span class="email-sent">
                                            <i class="fas fa-envelope"></i> Envoyé
                                        </span>
                                    <?php else: ?>
                                        <span class="email-pending">
                                            <i class="fas fa-clock"></i> En attente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($paiement['date_validation'])); ?></td>
                                <td>
                                    <div class="action-buttons-group">
                                        <?php if($paiement['pdf_path'] && file_exists($paiement['pdf_path'])): ?>
                                            <a href="<?php echo $paiement['pdf_path']; ?>" target="_blank" class="btn-premium btn-premium-sm" style="background: var(--info); color: white;" title="Voir facture">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if(!$paiement['envoye_email'] && $paiement['numero_facture']): ?>
                                            <a href="?page=validation&renvoyer_email=<?php echo $paiement['id']; ?>" class="btn-premium btn-premium-sm" style="background: var(--primary); color: white;" title="Renvoyer l'email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-premium">
                <i class="fas fa-receipt"></i>
                <p>Aucun paiement validé</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="rejetModal" class="modal-premium">
    <div class="modal-content-premium">
        <div class="modal-header-premium">
            <h3><i class="fas fa-ban"></i> Rejeter le paiement</h3>
            <span class="close" onclick="closeRejetModal()">&times;</span>
        </div>
        <form method="GET">
            <input type="hidden" name="page" value="validation">
            <input type="hidden" name="rejeter" id="rejetId">
            <div class="modal-body-premium">
                <div class="form-group-premium">
                    <label><i class="fas fa-comment"></i> Motif du rejet</label>
                    <textarea name="commentaire" class="form-control-premium" rows="3" placeholder="Expliquez la raison du rejet..." required></textarea>
                </div>
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    Attention : Cette action est irréversible. L'élève devra soumettre un nouveau paiement.
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-premium btn-premium-secondary" onclick="closeRejetModal()">Annuler</button>
                <button type="submit" class="btn-premium btn-premium-danger">Rejeter</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentRejetId = null;

function rejeterPaiement(id) {
    currentRejetId = id;
    document.getElementById('rejetId').value = id;
    document.getElementById('rejetModal').classList.add('show');
}

function closeRejetModal() {
    document.getElementById('rejetModal').classList.remove('show');
}

function exportValidations() {
    const data = <?php echo json_encode($paiements_valides); ?>;
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'validations_' + new Date().toISOString().slice(0,10) + '.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Export effectué', 'success');
}
</script>

