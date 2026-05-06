-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le :  mer. 06 mai 2026 à 14:06
-- Version du serveur :  10.1.28-MariaDB
-- Version de PHP :  7.1.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `paiement_scolaire`
--

-- --------------------------------------------------------

--
-- Structure de la table `eleves`
--

CREATE TABLE `eleves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `classe` varchar(50) NOT NULL,
  `niveau` varchar(50) NOT NULL,
  `annee_scolaire` varchar(20) NOT NULL,
  `email_parent` varchar(100) DEFAULT NULL,
  `telephone_parent` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` int(11) NOT NULL,
  `numero_facture` varchar(50) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `paiement_id` int(11) DEFAULT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `montant_paye` decimal(10,2) DEFAULT '0.00',
  `montant_restant` decimal(10,2) DEFAULT NULL,
  `tva` decimal(10,2) DEFAULT '0.00',
  `date_emission` date NOT NULL,
  `date_echeance` date DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `envoye_email` tinyint(1) DEFAULT '0',
  `statut` enum('payee','impayee','partielle') DEFAULT 'impayee'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `tranche_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `reference_bordereau` varchar(100) NOT NULL,
  `fichier_bordereau` varchar(255) DEFAULT NULL,
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `date_validation` date DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  `commentaire` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

--
-- Structure de la table `tranches_paiement`
--

CREATE TABLE `tranches_paiement` (
  `id` int(11) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `description` text,
  `montant` decimal(10,2) NOT NULL,
  `date_limite` date NOT NULL,
  `annee_scolaire` varchar(20) NOT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `ordre` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `tranches_paiement`
--

INSERT INTO `tranches_paiement` (`id`, `libelle`, `description`, `montant`, `date_limite`, `annee_scolaire`, `statut`, `ordre`) VALUES
(2, '1ème Tranche', 'Deuxième mensualité', '15.00', '2026-12-31', '2026-2027', 'actif', 2),
(3, '2ème Tranche', 'Troisième mensualité', '25.00', '2026-01-31', '2026-2027', 'actif', 3),
(4, '3ème Tranche', 'Quatrième mensualité', '25.00', '2026-02-28', '2026-2027', 'actif', 4),
(5, '4ème Tranche', 'Cinquième mensualité', '25.00', '2026-03-31', '2026-2027', 'actif', 5),
(6, '5ème Tranche', 'Sixième mensualité', '25.00', '2026-04-30', '2026-2027', 'actif', 6);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` text,
  `role` enum('admin','eleve') DEFAULT 'eleve',
  `date_inscription` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `email`, `password`, `nom`, `prenom`, `telephone`, `adresse`, `role`, `date_inscription`) VALUES
(1, 'admin@ecole.com', '0192023a7bbd73250516f069df18b500', 'Admin', 'Système', NULL, NULL, 'admin', '2026-04-10 10:16:53');

-- mot de passe est - admin123, username = admin@ecole.com 

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `paiement_id` (`paiement_id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_bordereau` (`reference_bordereau`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `tranche_id` (`tranche_id`),
  ADD KEY `valide_par` (`valide_par`);

--
-- Index pour la table `tranches_paiement`
--
ALTER TABLE `tranches_paiement`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `eleves`
--
ALTER TABLE `eleves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `tranches_paiement`
--
ALTER TABLE `tranches_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD CONSTRAINT `eleves_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `factures_ibfk_2` FOREIGN KEY (`paiement_id`) REFERENCES `paiements` (`id`);

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`tranche_id`) REFERENCES `tranches_paiement` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `utilisateurs` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
