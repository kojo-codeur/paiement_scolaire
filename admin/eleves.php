<?php
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $password = md5('password123');
    $telephone = $_POST['telephone'];
    $classe = $_POST['classe'];
    $niveau = $_POST['niveau'];
    
    try {
        $pdo->beginTransaction();
        
        $sql = "SELECT id FROM utilisateurs WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé");
        }
        
        $sql = "INSERT INTO utilisateurs (email, password, nom, prenom, telephone, role) 
                VALUES (?, ?, ?, ?, ?, 'eleve')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $password, $nom, $prenom, $telephone]);
        $user_id = $pdo->lastInsertId();
        
        $matricule = 'ELV' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO eleves (user_id, matricule, classe, niveau, annee_scolaire) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $matricule, $classe, $niveau, date('Y') . '-' . (date('Y')+1)]);
        
        $pdo->commit();
        $message = "Élève ajouté avec succès. Matricule: " . $matricule;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $eleve_id = $_POST['eleve_id'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $telephone = $_POST['telephone'];
    $classe = $_POST['classe'];
    $niveau = $_POST['niveau'];
    
    try {
        $pdo->beginTransaction();
        
        $sql = "SELECT user_id FROM eleves WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$eleve_id]);
        $user_id = $stmt->fetch()['user_id'];
        
        $sql = "SELECT id FROM utilisateurs WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé par un autre compte");
        }
        
        $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $prenom, $email, $telephone, $user_id]);
        
        $sql = "UPDATE eleves SET classe = ?, niveau = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$classe, $niveau, $eleve_id]);
        
        $pdo->commit();
        $message = "Élève modifié avec succès";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $sql = "DELETE FROM utilisateurs WHERE id = (SELECT user_id FROM eleves WHERE id = ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$id])) {
            $message = "Élève supprimé avec succès";
        } else {
            $error = "Erreur lors de la suppression";
        }
    } catch(Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

$sql = "SELECT e.*, u.nom, u.prenom, u.email, u.telephone, u.date_inscription 
        FROM eleves e 
        JOIN utilisateurs u ON e.user_id = u.id 
        ORDER BY e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$eleves = $stmt->fetchAll();

$total_eleves = count($eleves);
$classes = array_unique(array_column($eleves, 'classe'));
?>

<div class="stats-grid-premium">
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Total Élèves</span>
            <div class="stat-icon-premium">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value-premium"><?php echo $total_eleves; ?></div>
    </div>
    
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Classes</span>
            <div class="stat-icon-premium">
                <i class="fas fa-chalkboard"></i>
            </div>
        </div>
        <div class="stat-value-premium"><?php echo count($classes); ?></div>
    </div>
    
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Année scolaire</span>
            <div class="stat-icon-premium">
                <i class="fas fa-calendar"></i>
            </div>
        </div>
        <div class="stat-value-premium"><?php echo date('Y') . '-' . (date('Y')+1); ?></div>
    </div>
    
    <div class="stat-card-premium">
        <div class="stat-header-premium">
            <span class="stat-title-premium">Nouveaux</span>
            <div class="stat-icon-premium">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
        <div class="stat-value-premium"> + <?php echo $total_eleves?></div>
    </div>
</div>

<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-users"></i> Liste des élèves</h3>
        <button class="btn-premium btn-premium-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Ajouter un élève
        </button>
    </div>
    <div class="card-body-premium">
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="table-responsive-premium">
            <table class="data-table-premium" id="elevesTable">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Date inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($eleves as $eleve): ?>
                        <tr>
                            <td>
                                <span class="status-badge-premium" style="background: var(--primary-gradient); color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-family: monospace;">
                                    <?php echo $eleve['matricule']; ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($eleve['email']); ?></td>
                            <td><?php echo htmlspecialchars($eleve['telephone'] ?: '-'); ?></td>
                            <td>
                                <span class="status-badge-premium" style="background: var(--gray-100); color: var(--gray-700);">
                                    <?php echo htmlspecialchars($eleve['classe']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($eleve['niveau']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($eleve['date_inscription'])); ?></td>
                            <td>
                                <div class="action-buttons" style="display: flex; gap: 0.5rem;">
                                    <button class="btn-premium btn-premium-sm" style="background: var(--gray-100);" onclick="viewEleve(<?php echo $eleve['id']; ?>)" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-premium btn-premium-sm" style="background: var(--warning); color: white;" onclick="editEleve(<?php echo $eleve['id']; ?>)" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-premium btn-premium-sm" style="background: var(--danger); color: white;" onclick="deleteEleve(<?php echo $eleve['id']; ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addModal" class="modal-premium">
    <div class="modal-content-premium">
        <div class="modal-header-premium">
            <h3><i class="fas fa-user-plus"></i> Ajouter un élève</h3>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST" id="addForm">
            <input type="hidden" name="action" value="add">
            <div class="modal-body-premium">
                <div class="row-premium">
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-user"></i> Nom</label>
                            <input type="text" name="nom" class="form-control-premium" placeholder="Nom de l'élève" required>
                        </div>
                    </div>
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-user"></i> Prénom</label>
                            <input type="text" name="prenom" class="form-control-premium" placeholder="Prénom de l'élève" required>
                        </div>
                    </div>
                </div>
                <div class="form-group-premium">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control-premium" placeholder="exemple@ecole.com" required>
                </div>
                <div class="form-group-premium">
                    <label><i class="fas fa-phone"></i> Téléphone</label>
                    <input type="tel" name="telephone" class="form-control-premium" placeholder="XX XX XX XX XX">
                </div>
                <div class="row-premium">
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-chalkboard"></i> Classe</label>
                            <input type="text" name="classe" class="form-control-premium" placeholder="Ex: 3ème A" required>
                        </div>
                    </div>
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-layer-group"></i> Niveau</label>
                            <select name="niveau" class="form-control-premium" required>
                                <option value="6ème">6ème</option>
                                <option value="5ème">5ème</option>
                                <option value="4ème">4ème</option>
                                <option value="3ème">3ème</option>
                                <option value="2nde">2nde</option>
                                <option value="1ère">1ère</option>
                                <option value="Tle">Tle</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info" style="margin-top: 1rem;">
                    <i class="fas fa-info-circle"></i> 
                    Le mot de passe par défaut est: <strong>password123</strong>
                    <br>L'élève pourra le modifier après sa première connexion.
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-premium btn-premium-secondary" onclick="closeAddModal()">Annuler</button>
                <button type="submit" class="btn-premium btn-premium-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal-premium">
    <div class="modal-content-premium">
        <div class="modal-header-premium">
            <h3><i class="fas fa-user-graduate"></i> Détails de l'élève</h3>
            <span class="close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body-premium" id="viewContent">
            <!-- Contenu chargé dynamiquement -->
        </div>
        <div class="modal-footer-premium">
            <button class="btn-premium btn-premium-primary" onclick="printEleveDetails()">
                <i class="fas fa-print"></i> Imprimer
            </button>
            <button class="btn-premium btn-premium-secondary" onclick="closeViewModal()">Fermer</button>
        </div>
    </div>
</div>

<!-- Modal Modification Élève -->
<div id="editModal" class="modal-premium">
    <div class="modal-content-premium">
        <div class="modal-header-premium">
            <h3><i class="fas fa-user-edit"></i> Modifier l'élève</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="eleve_id" id="edit_eleve_id">
            <div class="modal-body-premium">
                <div class="row-premium">
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-user"></i> Nom</label>
                            <input type="text" name="nom" id="edit_nom" class="form-control-premium" required>
                        </div>
                    </div>
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-user"></i> Prénom</label>
                            <input type="text" name="prenom" id="edit_prenom" class="form-control-premium" required>
                        </div>
                    </div>
                </div>
                <div class="form-group-premium">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control-premium" required>
                </div>
                <div class="form-group-premium">
                    <label><i class="fas fa-phone"></i> Téléphone</label>
                    <input type="tel" name="telephone" id="edit_telephone" class="form-control-premium">
                </div>
                <div class="row-premium">
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-chalkboard"></i> Classe</label>
                            <input type="text" name="classe" id="edit_classe" class="form-control-premium" required>
                        </div>
                    </div>
                    <div class="col-premium-6">
                        <div class="form-group-premium">
                            <label><i class="fas fa-layer-group"></i> Niveau</label>
                            <select name="niveau" id="edit_niveau" class="form-control-premium" required>
                                <option value="6ème">6ème</option>
                                <option value="5ème">5ème</option>
                                <option value="4ème">4ème</option>
                                <option value="3ème">3ème</option>
                                <option value="2nde">2nde</option>
                                <option value="1ère">1ère</option>
                                <option value="Tle">Tle</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-premium btn-premium-secondary" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn-premium btn-premium-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    
    const elevesData = <?php 
    
    $data = [];
    foreach($eleves as $e) {
    
        $sqlStats = "SELECT SUM(CASE WHEN statut = 'valide' THEN montant ELSE 0 END) as total_paye,
                            COUNT(*) as nb_paiements
                     FROM paiements WHERE eleve_id = ?";
        $stmtStats = $pdo->prepare($sqlStats);
        $stmtStats->execute([$e['id']]);
        $stats = $stmtStats->fetch();
        
        $sqlTotal = "SELECT SUM(montant) as total FROM tranches_paiement WHERE annee_scolaire = ? AND statut = 'actif'";
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute([$e['annee_scolaire']]);
        $totalAPayer = $stmtTotal->fetch()['total'] ?? 0;
        
        $data[] = [
            'id' => $e['id'],
            'matricule' => $e['matricule'],
            'nom' => $e['nom'],
            'prenom' => $e['prenom'],
            'email' => $e['email'],
            'telephone' => $e['telephone'],
            'classe' => $e['classe'],
            'niveau' => $e['niveau'],
            'annee_scolaire' => $e['annee_scolaire'],
            'created_at' => $e['date_inscription'],
            'stats' => [
                'total_paye' => floatval($stats['total_paye'] ?? 0),
                'reste_a_payer' => floatval($totalAPayer - ($stats['total_paye'] ?? 0)),
                'nb_paiements' => intval($stats['nb_paiements'] ?? 0)
            ]
        ];
    }
    echo json_encode($data);
?>;

function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

function openEditModal() {
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function deleteEleve(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet élève ? Cette action est irréversible.')) {
        window.location.href = '?page=eleves&delete=' + id;
    }
}

function editEleve(id) {
    // Chercher l'élève dans les données
    const eleve = elevesData.find(e => e.id == id);
    
    if (eleve) {
        document.getElementById('edit_eleve_id').value = id;
        document.getElementById('edit_nom').value = eleve.nom;
        document.getElementById('edit_prenom').value = eleve.prenom;
        document.getElementById('edit_email').value = eleve.email;
        document.getElementById('edit_telephone').value = eleve.telephone || '';
        document.getElementById('edit_classe').value = eleve.classe;
        document.getElementById('edit_niveau').value = eleve.niveau;
        openEditModal();
    } else {
        showToast('Erreur lors du chargement des données', 'error');
    }
}

function viewEleve(id) {

    const eleve = elevesData.find(e => e.id == id);
    
    if (!eleve) {
        document.getElementById('viewContent').innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--danger);">
                <i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i>
                <p>Élève non trouvé</p>
            </div>
        `;
        document.getElementById('viewModal').classList.add('show');
        return;
    }
    
    const reste = eleve.stats.reste_a_payer;
    const estAJour = reste <= 0;
    
    const html = `
        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                <i class="fas fa-user-graduate"></i> Informations personnelles
            </h4>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Matricule</span>
                <span style="color: var(--gray-800);"><strong>${eleve.matricule}</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Nom complet</span>
                <span style="color: var(--gray-800);">${eleve.prenom} ${eleve.nom}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Email</span>
                <span style="color: var(--gray-800);">${eleve.email}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                <span style="font-weight: 500; color: var(--gray-600);">Téléphone</span>
                <span style="color: var(--gray-800);">${eleve.telephone || 'Non renseigné'}</span>
            </div>
        </div>
        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                <i class="fas fa-school"></i> Informations scolaires
            </h4>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Classe</span>
                <span style="color: var(--gray-800);">${eleve.classe}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Niveau</span>
                <span style="color: var(--gray-800);">${eleve.niveau}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Année scolaire</span>
                <span style="color: var(--gray-800);">${eleve.annee_scolaire}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                <span style="font-weight: 500; color: var(--gray-600);">Date d'inscription</span>
                <span style="color: var(--gray-800);">${new Date(eleve.created_at).toLocaleDateString('fr-FR')}</span>
            </div>
        </div>
        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem;">
            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                <i class="fas fa-chart-line"></i> Statistiques financières
            </h4>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Total payé</span>
                <span style="color: var(--success); font-weight: 700;">${eleve.stats.total_paye.toLocaleString('fr-FR')} USD</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                <span style="font-weight: 500; color: var(--gray-600);">Reste à payer</span>
                <span style="color: var(--danger); font-weight: 700;">${eleve.stats.reste_a_payer.toLocaleString('fr-FR')} USD</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                <span style="font-weight: 500; color: var(--gray-600);">Paiements effectués</span>
                <span style="color: var(--gray-800); font-weight: 700;">${eleve.stats.nb_paiements}</span>
            </div>
        </div>
    `;
    
    document.getElementById('viewContent').innerHTML = html;
    document.getElementById('viewModal').classList.add('show');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('show');
}

function printEleveDetails() {
    const content = document.getElementById('viewContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Fiche élève</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Inter', sans-serif; padding: 20px; background: white; }
                    @media print {
                        body { margin: 0; padding: 0; }
                    }
                </style>
            </head>
            <body>${content}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>