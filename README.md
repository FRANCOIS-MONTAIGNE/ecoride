# EcoRide — Projet ECF DWWM
**Auteur : FRANCOIS MONTAIGNE Florence**

EcoRide est une application web de covoiturage écologique développée dans le cadre de l’ECF du titre professionnel Développeur Web et Web Mobile.

L’objectif de la plateforme est de mettre en relation des conducteurs et des passagers pour des trajets partagés, tout en favorisant l’utilisation de véhicules à faible impact environnemental.

---

## Fonctionnalités principales

### Visiteur
- Recherche de trajets par ville et date
- Consultation des détails d’un covoiturage
- Accès aux filtres (prix, durée, note, trajet écologique)

### Utilisateur (passager / chauffeur)
- Création de compte et connexion sécurisée
- Réservation de covoiturages
- Gestion des crédits
- Consultation de l’historique des trajets
- Ajout de véhicules et de préférences (chauffeur)
- Création, démarrage et clôture de trajets

### Employé
- Validation ou refus des avis déposés par les passagers
- Consultation des trajets signalés

### Administrateur
- Création de comptes employés
- Suspension de comptes utilisateurs ou employés
- Consultation des statistiques (trajets, crédits, activité)

---

## Technologies utilisées

**Front-end**
- HTML5
- CSS3
- JavaScript (ES6)
- Chart.js (statistiques administrateur)

**Back-end**
- PHP 8.2
- API REST maison
- Authentification JWT

**Base de données**
- MariaDB / MySQL
- PDO (requêtes préparées)

**Outils**
- XAMPP (Apache, PHP, MariaDB)
- Visual Studio Code
- phpMyAdmin
- Git / GitHub

---

## Sécurité

- Mots de passe stockés sous forme hachée
- Authentification par jetons JWT
- Contrôle des rôles côté serveur (utilisateur, employé, administrateur)
- Requêtes SQL préparées via PDO
- Validation des données côté serveur
- Journalisation des actions sensibles côté backend

---

## Installation en local

### Prérequis
- XAMPP (Apache + PHP 8.2 + MariaDB)
- Navigateur web

### Étapes

1. Cloner le dépôt :
```bash
git clone https://github.com/TON_COMPTE/ecoride.git
