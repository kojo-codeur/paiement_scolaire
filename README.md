Le **Système de Paiement Scolaire** est une application web moderne permettant aux établissements scolaires de gérer facilement :

* les frais scolaires,
* les paiements,
* les reçus,
* les statistiques financières,
* et les rapports administratifs.

Cette plateforme a été développée avec :

* **PHP**
* **MySQL**
* **JavaScript**
* **HTML5**
* **CSS3**
* **Bootstrap 5**
* **Chart.js**


# 🚀 Technologies Utilisées

## 🔹 Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap 5
* Font Awesome
* Google Fonts (Inter)
* Chart.js

## 🔹 Backend

* PHP

## 🔹 Base de données

* MySQL

---

# 📦 Dépendances CDN

```html
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

---

# ✨ Fonctionnalités

## 👨‍🎓 Gestion des Élèves

* Ajouter un élève
* Modifier les informations
* Supprimer un élève
* Rechercher un élève

---

## 💳 Gestion des Paiements

* Enregistrer un paiement
* Historique des paiements
* Vérification des soldes
* Génération des reçus

---

## 📊 Tableau de Bord

* Nombre total d’élèves
* Revenus mensuels
* Paiements récents
* Graphiques statistiques avec Chart.js

---

## 🧾 Rapports

* Rapport journalier
* Rapport mensuel
* Impression des rapports

---

## 🔐 Sécurité

* Authentification administrateur
* Sessions sécurisées
* Validation des formulaires
* Protection contre les injections SQL

---

# 📁 Structure du Projet

```bash
school-payment-system/
│
├── assets/
│   ├── css/
│   │   └── style.css
│   │
│   ├── js/
│   │   └── app.js
│   │
│   └── images/
│
├── config/
│   └── database.php
│
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
│
├── pages/
│   ├── dashboard.php
│   ├── students.php
│   ├── payments.php
│   ├── reports.php
│   └── login.php
│
├── database/
│   └── school_payment.sql
│
├── index.php
└── README.md
```


## 2️⃣ Déplacer le Projet

Déplacer le dossier dans :

### XAMPP

```bash
htdocs/
```

### Exemple

```bash
C:/xampp/htdocs/paiement_scolaire
```

---

## 3️⃣ Créer la Base de Données

Ouvrir :

```bash
http://localhost/paiement_scolaire
```

Créer une base :

```sql
paiement_scolaire
```

Importer :

```bash
database/school_payment.sql
```

---

## 4️⃣ Configuration MySQL

Modifier :

```bash
config/database.php
```

```php
<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "school_payment";

$conn = mysqli_connect($host, $user, $password, $database);

if(!$conn){
    die("Connection failed");
}

?>
```

---

## 5️⃣ Lancer le Projet

```bash
http://localhost/paiement_scolaire
```

---

# 🎨 Interface Moderne

Le projet utilise :

* ✅ Bootstrap 5
* ✅ Font Awesome
* ✅ Google Font Inter
* ✅ Dashboard responsive
* ✅ Cartes modernes
* ✅ Tableaux dynamiques
* ✅ Graphiques interactifs

---

# 📊 Exemple Chart.js

```javascript
const ctx = document.getElementById('paymentChart');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Janvier', 'Février', 'Mars', 'Avril'],
        datasets: [{
            label: 'Paiements',
            data: [1000, 2000, 1500, 3000],
            borderWidth: 2
        }]
    }
});
```

---

# 📸 Captures d’Écran

Vous pouvez ajouter :

* Dashboard
* Gestion des élèves
* Paiements
* Rapports
* Reçus ou facture

---

# 🔮 Améliorations Futures

* 📱 Application Android
* 💬 Notifications SMS
* 📧 Notifications Email
* 🧾 Export PDF
* 🌍 API REST
* 💳 Paiement Mobile

---
