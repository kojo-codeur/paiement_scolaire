<?php
$user_id = $_SESSION['user_id'];
$eleve = getEleveByUserId($user_id, $pdo);

$sql = "SELECT p.*, t.libelle as tranche_libelle, t.date_limite,
        f.numero_facture, f.pdf_path as facture_path
        FROM paiements p 
        JOIN tranches_paiement t ON p.tranche_id = t.id 
        LEFT JOIN factures f ON p.id = f.paiement_id
        WHERE p.eleve_id = ? 
        ORDER BY p.date_paiement DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve['id']]);
$paiements = $stmt->fetchAll();

$total_paye = 0;
$total_attente = 0;
$total_rejete = 0;

foreach($paiements as $p) {
    if($p['statut'] == 'valide') {
        $total_paye += $p['montant'];
    } elseif($p['statut'] == 'en_attente') {
        $total_attente += $p['montant'];
    } elseif($p['statut'] == 'rejete') {
        $total_rejete += $p['montant'];
    }
}

$sql = "SELECT SUM(montant) as total FROM tranches_paiement WHERE annee_scolaire = ? AND statut = 'actif'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve['annee_scolaire']]);
$total_a_payer = $stmt->fetch()['total'] ?? 0;
$reste_a_payer = $total_a_payer - $total_paye;
?>


<div class="historique-header">
    <div class="row-premium align-items-center">
        <div class="col-premium-8">
            <h2 style="color: white; margin-bottom: 0.5rem;">
                <i class="fas fa-history"></i> Historique des paiements
            </h2>
            <p style="opacity: 0.9;">Suivez l'évolution de tous vos paiements de scolarité</p>
        </div>
        <div class="col-premium-4 text-end">
            <div class="small" style="opacity: 0.8;">Classe :</div>
            <div class="fw-bold"><?php echo $eleve['classe']; ?></div>
            <div class="small">Année: <?php echo $eleve['annee_scolaire']; ?></div>
        </div>
    </div>
    
    <div class="stats-historique">
        <div class="stat-historique-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo number_format($total_paye, 0, ',', ' '); ?> USD</div>
            <div class="small">Total payé</div>
        </div>
        <div class="stat-historique-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo number_format($total_attente, 0, ',', ' '); ?> USD</div>
            <div class="small">En attente</div>
        </div>
        <div class="stat-historique-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo number_format($total_rejete, 0, ',', ' '); ?> USD</div>
            <div class="small">Rejetés</div>
        </div>
        <div class="stat-historique-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo number_format($reste_a_payer, 0, ',', ' '); ?> USD</div>
            <div class="small">Reste à payer</div>
        </div>
    </div>
</div>

<?php if(count($paiements) > 0): ?>
    <div class="timeline">
        <?php foreach($paiements as $paiement): ?>
            <div class="timeline-item">
                <div class="timeline-dot <?php echo $paiement['statut']; ?>"></div>
                <div class="paiement-card">
                    <div class="paiement-info">
                        <h4><?php echo htmlspecialchars($paiement['tranche_libelle']); ?></h4>
                        <div class="small text-muted">
                            <i class="fas fa-calendar"></i> 
                            Paiement le <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?>
                            <?php if($paiement['date_validation']): ?>
                                - Validé le <?php echo date('d/m/Y', strtotime($paiement['date_validation'])); ?>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-hashtag"></i> Réf: <?php echo $paiement['reference_bordereau']; ?>
                        </div>
                        <?php if($paiement['commentaire']): ?>
                            <div class="small text-danger mt-1">
                                <i class="fas fa-comment"></i> Motif: <?php echo htmlspecialchars($paiement['commentaire']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="paiement-montant <?php echo $paiement['statut']; ?>">
                        <?php echo number_format($paiement['montant'], 0, ',', ' '); ?> USD
                    </div>
                    <div class="paiement-actions">
                        <?php if($paiement['statut'] == 'valide' && $paiement['facture_path']): ?>
                            <a href="<?php echo $paiement['facture_path']; ?>" target="_blank" class="facture-link">
                                <i class="fas fa-file-pdf"></i> Voir facture
                            </a>
                        <?php endif; ?>
                        <?php if($paiement['statut'] == 'rejete'): ?>
                            <a href="?page=paiement" class="facture-link" style="background: var(--danger); color: white;">
                                <i class="fas fa-redo"></i> Re-payer
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state-premium">
        <i class="fas fa-receipt"></i>
        <p>Aucun paiement effectué pour le moment</p>
        <a href="?page=paiement" class="btn-premium btn-premium-primary mt-2">
            <i class="fas fa-credit-card"></i> Effectuer un paiement
        </a>
    </div>
<?php endif; ?>