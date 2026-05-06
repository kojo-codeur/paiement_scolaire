<?php

include_once 'includes/functions.php';
include_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$eleve = getEleveByUserId($user_id, $pdo);
$tranches_restantes = getTranchesRestantes($eleve['id'], $pdo);
$paiements = getPaiementsEleve($eleve['id'], $pdo);

$total_paye = 0;
$total_attente = 0;
foreach($paiements as $p) {
    if($p['statut'] == 'valide') {
        $total_paye += $p['montant'];
    } elseif($p['statut'] == 'en_attente') {
        $total_attente += $p['montant'];
    }
}

$sql = "SELECT SUM(montant) as total FROM tranches_paiement WHERE annee_scolaire = ? AND statut = 'actif'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve['annee_scolaire']]);
$total_a_payer = $stmt->fetch()['total'] ?? 0;
$reste_a_payer = $total_a_payer - $total_paye;
$taux_completion = $total_a_payer > 0 ? round(($total_paye / $total_a_payer) * 100) : 0;
?>

<div class="welcome-card">
    <div class="welcome-title">
        Bienvenue, <?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?> ! 👋
    </div>
    <p style="opacity: 0.9;">Gérez vos paiements et suivez votre scolarité en toute simplicité</p>
    <div class="row-premium mt-3">
        <div class="col-premium-4">
            <div class="small" style="opacity: 0.8;">Matricule</div>
            <div class="fw-bold"><?php echo $eleve['matricule']; ?></div>
        </div>
        <div class="col-premium-4">
            <div class="small" style="opacity: 0.8;">Classe</div>
            <div class="fw-bold"><?php echo $eleve['classe']; ?></div>
        </div>
        <div class="col-premium-4">
            <div class="small" style="opacity: 0.8;">Année scolaire</div>
            <div class="fw-bold"><?php echo $eleve['annee_scolaire']; ?></div>
        </div>
    </div>
</div>

<div class="stats-grid-premium">
    <div class="stat-card-eleve">
        <div class="stat-icon-eleve">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-value-premium" style="font-size: 1.5rem;"><?php echo number_format($total_paye, 0, ',', ' '); ?> USD</div>
        <div class="small text-muted">Total payé</div>
    </div>
    
    <div class="stat-card-eleve">
        <div class="stat-icon-eleve">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-value-premium" style="font-size: 1.5rem; color: var(--warning);"><?php echo number_format($reste_a_payer, 0, ',', ' '); ?> USD</div>
        <div class="small text-muted">Reste à payer</div>
    </div>
    
    <div class="stat-card-eleve">
        <div class="stat-icon-eleve">
            <i class="fas fa-percent"></i>
        </div>
        <div class="stat-value-premium" style="font-size: 1.5rem;"><?php echo $taux_completion; ?>%</div>
        <div class="small text-muted">Taux de complétion</div>
    </div>
    
    <div class="stat-card-eleve">
        <div class="stat-icon-eleve">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value-premium" style="font-size: 1.5rem; color: var(--success);"><?php echo count(array_filter($paiements, function($p) { return $p['statut'] == 'valide'; })); ?></div>
        <div class="small text-muted">Paiements validés</div>
    </div>
</div>

<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-chart-line"></i> Progression des paiements</h3>
        <span class="status-badge-premium valide"><?php echo $taux_completion; ?>% complété</span>
    </div>
    <div class="card-body-premium">
        <div class="payment-progress">
            <div class="payment-progress-fill" style="width: <?php echo $taux_completion; ?>%"></div>
        </div>
        <div class="row-premium mt-3">
            <div class="col-premium-6">
                <div class="small text-muted">Objectif total</div>
                <div class="fw-bold"><?php echo number_format($total_a_payer, 0, ',', ' '); ?> USD</div>
            </div>
            <div class="col-premium-6 text-end">
                <div class="small text-muted">Déjà payé</div>
                <div class="fw-bold text-success"><?php echo number_format($total_paye, 0, ',', ' '); ?> USD</div>
            </div>
        </div>
    </div>
</div>

<div class="row-premium">
    <div class="col-premium-6">
        <div class="card-premium">
            <div class="card-header-premium">
                <h3><i class="fas fa-calendar-alt"></i> Tranches à payer</h3>
                <a href="?page=paiement" class="show-all-link">Effectuer un paiement →</a>
            </div>
            <div class="card-body-premium">
                <?php if(count($tranches_restantes) > 0): ?>
                    <div class="tranches-list-premium">
                        <?php foreach($tranches_restantes as $tranche): ?>
                            <div class="tranche-item-premium">
                                <div class="tranche-info-premium">
                                    <strong><?php echo htmlspecialchars($tranche['libelle']); ?></strong>
                                    <div class="date-limite">
                                        <i class="fas fa-calendar"></i> 
                                        À payer avant le <?php echo date('d/m/Y', strtotime($tranche['date_limite'])); ?>
                                    </div>
                                </div>
                                <div class="tranche-montant-premium">
                                    <?php echo number_format($tranche['montant'], 0, ',', ' '); ?> USD
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-premium">
                        <i class="fas fa-check-circle" style="color: var(--success); font-size: 3rem;"></i>
                        <p>Félicitations ! Toutes vos tranches sont payées.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-premium-6">
        <div class="card-premium">
            <div class="card-header-premium">
                <h3><i class="fas fa-history"></i> Derniers paiements</h3>
                <a href="?page=historique" class="show-all-link">Voir tout →</a>
            </div>
            <div class="card-body-premium">
                <?php if(count($paiements) > 0): ?>
                    <div class="timeline-list">
                        <?php foreach(array_slice($paiements, 0, 5) as $paiement): ?>
                            <div class="timeline-item <?php echo $paiement['statut']; ?>">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($paiement['tranche_libelle']); ?></strong>
                                        <span class="fw-bold <?php echo $paiement['statut'] == 'valide' ? 'text-success' : 'text-warning'; ?>">
                                            <?php echo number_format($paiement['montant'], 0, ',', ' '); ?> USD
                                        </span>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?>
                                        <span class="ms-2">
                                            <span class="status-badge-premium <?php echo $paiement['statut']; ?>">
                                                <?php echo $paiement['statut'] == 'valide' ? '✓ Validé' : ($paiement['statut'] == 'en_attente' ? '⏳ En attente' : '❌ Rejeté'); ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-premium">
                        <i class="fas fa-receipt"></i>
                        <p>Aucun paiement effectué pour le moment.</p>
                        <a href="?page=paiement" class="btn-premium btn-premium-primary mt-2">Effectuer un paiement</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-rocket"></i> Recommandé pour vous</h3>
    </div>
    <div class="card-body-premium">
        <div class="row-premium">
            <div class="col-premium-6">
                <div class="quick-action-card-premium" onclick="window.location.href='?page=paiement'">
                    <div class="quick-action-icon-premium">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="quick-action-content-premium">
                        <div class="quick-action-title-premium">Effectuer un paiement</div>
                        <div class="quick-action-desc-premium">Payez vos tranches de scolarité</div>
                    </div>
                </div>
            </div>
            <div class="col-premium-6">
                <div class="quick-action-card-premium" onclick="window.location.href='?page=factures'">
                    <div class="quick-action-icon-premium">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="quick-action-content-premium">
                        <div class="quick-action-title-premium">Voir mes factures</div>
                        <div class="quick-action-desc-premium">Téléchargez vos factures PDF</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>