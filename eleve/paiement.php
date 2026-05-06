<?php
$user_id = $_SESSION['user_id'];
$eleve = getEleveByUserId($user_id, $pdo);

$tranches_restantes = getTranchesRestantes($eleve['id'], $pdo);

$sql = "SELECT t.*, p.statut, p.date_paiement, p.reference_bordereau, p.id as paiement_id
        FROM tranches_paiement t
        LEFT JOIN paiements p ON t.id = p.tranche_id AND p.eleve_id = ?
        WHERE t.annee_scolaire = ? AND t.statut = 'actif'
        ORDER BY t.date_limite ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve['id'], $eleve['annee_scolaire']]);
$toutes_tranches = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_paiement'])) {
    $tranche_id = $_POST['tranche_id'];
    $reference_bordereau = $_POST['reference_bordereau'];
    $montant = $_POST['montant'];
    
    $sql = "SELECT * FROM tranches_paiement WHERE id = ? AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tranche_id]);
    $tranche = $stmt->fetch();
    
    if (!$tranche) {
        $error = "Tranche invalide";
    } else {
        $sql = "SELECT id FROM paiements WHERE eleve_id = ? AND tranche_id = ? AND statut = 'valide'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$eleve['id'], $tranche_id]);
        if ($stmt->fetch()) {
            $error = "Cette tranche a déjà été payée";
        } else {
            $fichier_bordereau = null;
            if (isset($_FILES['bordereau']) && $_FILES['bordereau']['error'] == 0) {
                $upload_dir = 'uploads/bordereaux/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['bordereau']['name'], PATHINFO_EXTENSION);
                $nom_fichier = 'bordereau_' . $eleve['matricule'] . '_' . time() . '.' . $extension;
                $chemin_fichier = $upload_dir . $nom_fichier;
                
                if (move_uploaded_file($_FILES['bordereau']['tmp_name'], $chemin_fichier)) {
                    $fichier_bordereau = $chemin_fichier;
                }
            }
            
            $sql = "INSERT INTO paiements (eleve_id, tranche_id, montant, date_paiement, reference_bordereau, fichier_bordereau, statut) 
                    VALUES (?, ?, ?, NOW(), ?, ?, 'en_attente')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$eleve['id'], $tranche_id, $montant, $reference_bordereau, $fichier_bordereau])) {
                $message = "Votre paiement a été enregistré et est en attente de validation. Vous recevrez un email une fois validé.";
            } else {
                $error = "Erreur lors de l'enregistrement du paiement";
            }
        }
    }
}
?>

<div class="payment-header">
    <div class="row-premium align-items-center">
        <div class="col-premium-8">
            <h2 style="color: white; margin-bottom: 0.5rem;"><i class="fas fa-credit-card"></i> Paiement en ligne</h2>
            <p style="opacity: 0.9;">Sélectionnez la tranche que vous souhaitez payer et téléchargez votre bordereau</p>
        </div>
        <div class="col-premium-4 text-end">
            <div class="small" style="opacity: 0.8;">Compte :</div>
            <div class="fw-bold"><?php echo $eleve['matricule']; ?></div>
        </div>
    </div>
</div>

<?php if($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row-premium">
    <div class="col-premium-7">
        <div class="card-premium">
            <div class="card-header-premium">
                <h3><i class="fas fa-list"></i> Tranches de scolarité</h3>
                <span class="status-badge-premium valide">Année <?php echo $eleve['annee_scolaire']; ?></span>
            </div>
            <div class="card-body-premium">
                <div class="row-premium">
                    <?php foreach($toutes_tranches as $index => $tranche): 
                        $statut_paiement = null;
                        $paiement_id = null;
                        if (isset($tranche['statut'])) {
                            $statut_paiement = $tranche['statut'];
                            $paiement_id = $tranche['paiement_id'];
                        }
                        $estPaye = $statut_paiement == 'valide';
                        $estAttente = $statut_paiement == 'en_attente';
                    ?>
                        <div class="col-premium-12 mb-3">
                            <div class="tranche-card <?php echo $estPaye ? 'paid' : ($estAttente ? 'pending' : ''); ?>" 
                                 onclick="selectTranche(<?php echo $tranche['id']; ?>, '<?php echo addslashes($tranche['libelle']); ?>', <?php echo $tranche['montant']; ?>)">
                                <div class="row-premium p-3 align-items-center">
                                    <div class="col-premium-1">
                                        <div class="rank-badge"><?php echo $index + 1; ?></div>
                                    </div>
                                    <div class="col-premium-5">
                                        <strong><?php echo htmlspecialchars($tranche['libelle']); ?></strong>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar"></i> 
                                            À payer avant le <?php echo date('d/m/Y', strtotime($tranche['date_limite'])); ?>
                                        </div>
                                    </div>
                                    <div class="col-premium-3">
                                        <div class="amount-display" style="font-size: 1.25rem;">
                                            <?php echo number_format($tranche['montant'], 0, ',', ' '); ?> USD
                                        </div>
                                    </div>
                                    <div class="col-premium-3 text-end">
                                        <?php if($estPaye): ?>
                                            <span class="status-badge-premium valide">
                                                <i class="fas fa-check-circle"></i> Payé
                                            </span>
                                        <?php elseif($estAttente): ?>
                                            <span class="status-badge-premium en_attente">
                                                <i class="fas fa-clock"></i> En attente
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge-premium" style="background: var(--gray-200); color: var(--gray-600);">
                                                <i class="fas fa-hourglass-half"></i> À payer
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card-premium mt-3">
            <div class="card-header-premium">
                <h3><i class="fas fa-university"></i> Informations de paiement</h3>
            </div>
            <div class="card-body-premium">
                <div class="row-premium">
                    <div class="col-premium-6">
                        <div class="info-box">
                            <i class="fas fa-building" style="color: var(--primary);"></i>
                            <strong>Banque</strong><br>
                            <small>Interbank</small>
                        </div>
                    </div>
                    <div class="col-premium-6">
                        <div class="info-box">
                            <i class="fas fa-credit-card" style="color: var(--primary);"></i>
                            <strong>Numéro de compte</strong><br>
                            <small>CI 12345 67890 12345678901 01</small>
                        </div>
                    </div>
                    <div class="col-premium-12 mt-2">
                        <div class="info-box">
                            <i class="fas fa-file-invoice" style="color: var(--primary);"></i>
                            <strong>Référence à indiquer</strong><br>
                            <small>Votre matricule: <strong><?php echo $eleve['matricule']; ?></strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-premium-5">
        <div class="payment-form-container" id="paymentFormContainer">
            <h4 class="mb-3"><i class="fas fa-credit-card"></i> Formulaire de paiement</h4>
            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                <input type="hidden" name="tranche_id" id="tranche_id" required>
                <input type="hidden" name="montant" id="montant" required>
                
                <div class="form-group-premium">
                    <label><i class="fas fa-tag"></i> Tranche sélectionnée</label>
                    <input type="text" class="form-control-premium" id="tranche_libelle" readonly placeholder="Sélectionnez une tranche ci-dessus">
                </div>
                
                <div class="form-group-premium">
                    <label><i class="fas fa-money-bill-wave"></i> Montant à payer</label>
                    <input type="text" class="form-control-premium" id="montant_display" readonly placeholder="0 FCFA" style="font-size: 1.25rem; font-weight: bold; color: var(--primary);">
                </div>
                
                <div class="form-group-premium">
                    <label><i class="fas fa-hashtag"></i> Référence du bordereau</label>
                    <input type="text" name="reference_bordereau" class="form-control-premium" placeholder="Ex: REF-2024-001234" required>
                    <small class="text-muted">La référence unique de votre virement bancaire</small>
                </div>
                
                <div class="form-group-premium">
                    <label><i class="fas fa-file-upload"></i> Bordereau de paiement (PDF/Image)</label>
                    <input type="file" name="bordereau" class="form-control-premium" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-muted">Format PDF, JPG ou PNG. Maximum 5MB.</small>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    Après validation de votre paiement par l'administration, vous recevrez votre facture par email.
                </div>
                
                <button type="submit" name="submit_paiement" class="btn-premium btn-premium-primary w-100" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane"></i> Soumettre le paiement
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let selectedTrancheId = null;

function selectTranche(id, libelle, montant) {
    selectedTrancheId = id;
    document.getElementById('tranche_id').value = id;
    document.getElementById('tranche_libelle').value = libelle;
    document.getElementById('montant').value = montant;
    document.getElementById('montant_display').value = new Intl.NumberFormat('fr-FR').format(montant) + ' USD';
    document.getElementById('submitBtn').disabled = false;
    
    document.querySelectorAll('.tranche-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
}

document.querySelector('input[name="bordereau"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file) {
        const maxSize = 5 * 1024 * 1024;
        if(file.size > maxSize) {
            alert('Le fichier est trop volumineux. Maximum 5MB.');
            this.value = '';
            return;
        }
        
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if(!allowedTypes.includes(file.type)) {
            alert('Format non supporté. Utilisez JPG, PNG ou PDF.');
            this.value = '';
            return;
        }
    }
});
</script>