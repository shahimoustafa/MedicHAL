# 🩺 Santé Connect — API de Prise de Rendez-vous Médicaux

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B%20%7C%208.2%2B%20%7C%208.3%2B%20%7C%208.4%2B%20%7C%208.5%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-10%2F10%20Pass-brightgreen.svg)]()
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.0.3-orange.svg)]()

**Santé Connect** est une solution d'API REST complète, moderne et haute performance dédiée à la prise de rendez-vous pour les professionnels et praticiens de santé (médecins généralistes, spécialistes, dentistes, kinésithérapeutes, etc.) et leurs patients.

---

## 🌟 Fonctionnalités Principales

- **Gestion des Professionnels de Santé (Praticiens)** :
  - Profil complet : coordonnées, spécialité médicale, numéro RPPS, tarif de consultation, durée unitaire des créneaux.
  - Recherche multicritère : filtrage par spécialité, ville et mot-clé.
- **Gestion des Plages de Travail & Disponibilités** :
  - Configuration des horaires hebdomadaires récurrents (matin/après-midi).
  - Définition d'exceptions et de jours de congés/indisponibilités pour une date donnée.
- **Moteur Algorithmique de Calcul des Créneaux Libres (`SlotCalculatorService`)** :
  - Génération dynamique des créneaux réservables selon la durée de consultation configurée.
  - Exclusion automatique des heures passées.
  - Exclusion des créneaux déjà occupés par des rendez-vous confirmés ou en attente.
  - Exclusion des congés et indisponibilités du praticien.
- **Gestion des Dossiers Patients** :
  - Création, mise à jour, recherche de patients et historique des consultations.
- **Prise & Cycle de Vie des Rendez-vous** :
  - Réservation instantanée avec contrôle d'intégrité strict (détection et prévention des doubles réservations pour le praticien ET pour le patient).
  - Modification et report de créneau avec re-vérification de disponibilité en temps réel.
  - Annulation avec motif obligatoire (libérant immédiatement le créneau pour d'autres patients).
  - Statuts gérés : `CONFIRMED`, `PENDING`, `CANCELLED`, `COMPLETED`, `NO_SHOW`.
- **Documentation OpenAPI 3.0 & Swagger UI Intégrée** :
  - Interface interactive accessible directement sur `http://localhost:8000/docs` ou `/`.
  - Schéma JSON complet disponible sur `/openapi.json`.
- **Persistance Autonome & Zéro Dépendance Lourde** :
  - Dépôt de données JSON thread-safe avec verrouillage de fichier (`LOCK_EX`) prêt à l'emploi.

---

## 🏗️ Architecture du Projet

Le projet applique les principes de la **Clean Architecture** / **Architecture Hexagonale** en séparant rigoureusement le domaine métier, l'application et les détails d'infrastructure :

```text
├── bin/
│   ├── console.php                # Outil CLI pour administrer l'API
│   └── seed.php                   # Script de peuplement de données de test
├── data/
│   └── database.json              # Stockage de données persistant
├── public/
│   └── index.php                  # Point d'entrée web (Front Controller)
├── src/
│   ├── Application/
│   │   ├── DTO/                   # Objets de transfert de données avec validation
│   │   │   ├── CreateAppointmentDTO.php
│   │   │   ├── CreatePatientDTO.php
│   │   │   ├── CreatePractitionerDTO.php
│   │   │   ├── RescheduleAppointmentDTO.php
│   │   │   ├── SetAvailabilityDTO.php
│   │   │   └── ...
│   │   └── Service/               # Services applicatifs
│   │       ├── AppointmentService.php
│   │       ├── AvailabilityService.php
│   │       ├── PatientService.php
│   │       └── PractitionerService.php
│   ├── Domain/
│   │   ├── Exception/             # Exceptions métier (Conflict, NotFound, Validation)
│   │   ├── Model/                 # Entités, Value Objects & Enums
│   │   │   ├── Appointment.php
│   │   │   ├── AppointmentStatus.php
│   │   │   ├── Availability.php
│   │   │   ├── Patient.php
│   │   │   ├── Practitioner.php
│   │   │   ├── Speciality.php
│   │   │   └── TimeSlot.php
│   │   ├── Repository/            # Interfaces des dépôts (Contrats)
│   │   └── Service/               # Moteur algorithmique de créneaux (SlotCalculatorService)
│   └── Infrastructure/
│       ├── Container/             # Conteneur d'injection de dépendances
│       ├── Controller/            # Contrôleurs HTTP REST & OpenAPI
│       ├── Http/                  # Request, Response, Router, Gestion des erreurs & CORS
│       └── Persistence/           # Implémentations des Repositories (JSON)
├── tests/
│   ├── Unit/                      # Tests unitaires du domaine et de validation
│   ├── Integration/               # Tests d'intégration API de bout en bout
│   └── run_tests.php              # Exécuteur de tests automatisé
├── composer.json
└── README.md
```

---

## 🚀 Démarrage Rapide

### 1. Prérequis
- **PHP 8.1+** (compatible 8.2, 8.3, 8.4, 8.5)
- **Composer**

### 2. Installation des dépendances et de l'autoloader
```bash
composer dump-autoload
```

### 3. Initialiser les données de démonstration (Seed)
```bash
composer seed
# ou : php bin/seed.php
```

### 4. Lancer l'API & l'interface Swagger UI
```bash
composer swagger
# ou : composer start
# ou : php -S 127.0.0.1:8000 -t public public/index.php
```

L'API et la documentation Swagger UI sont immédiatement disponibles sur **`http://127.0.0.1:8000/docs`** (ou `http://127.0.0.1:8000`).
La spécification OpenAPI 3.0 au format JSON est accessible sur **`http://127.0.0.1:8000/openapi.json`**.

---

## 🧪 Exécution des Tests

Le projet inclut une suite de tests unitaires et de scénarios d'intégration pour la gestion documentaire :

```bash
composer test
# ou : php tests/run_tests.php
```

---

## 📋 Référence Complète des Endpoints REST

Format d'échange standard : **JSON (`Content-Type: application/json`)**.

### 🩺 1. Système & Documentation
| Méthode | Route | Description |
| :--- | :--- | :--- |
| `GET` | `/` ou `/docs` | Documentation interactive Swagger UI |
| `GET` | `/openapi.json` | Spécification OpenAPI 3.0 au format JSON |
| `GET` | `/api/health` | Diagnostic de santé de l'API et métriques |

---

### 👨‍⚕️ 2. Professionnels de Santé (Praticiens)
| Méthode | Route | Description | Paramètres / Body |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/specialities` | Liste des spécialités médicales disponibles | - |
| `GET` | `/api/practitioners` | Rechercher des praticiens | `?speciality=Cardiologue&city=Paris&search=Martin` |
| `POST` | `/api/practitioners` | Créer un nouveau praticien | `{ "first_name": "Sophie", "last_name": "Martin", "email": "...", "phone": "...", "speciality": "Médecin généraliste", "address": "...", "city": "Paris", "zip_code": "75001", "consultation_fee": 26.5, "slot_duration_minutes": 30 }` |
| `GET` | `/api/practitioners/{id}` | Obtenir les détails d'un praticien | - |
| `PUT` | `/api/practitioners/{id}` | Modifier les informations d'un praticien | `{ "consultation_fee": 30.0, "city": "Paris" }` |
| `DELETE` | `/api/practitioners/{id}` | Supprimer un praticien | - |

---

### 📅 3. Plages Horaires & Calcul des Créneaux Disponibles
| Méthode | Route | Description | Paramètres / Body |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/practitioners/{id}/availabilities` | Consulter les plages de travail hebdomadaires | - |
| `POST` | `/api/practitioners/{id}/availabilities` | Configurer les plages de travail ou congés | `{ "items": [{ "day_of_week": 1, "start_time": "08:30", "end_time": "12:30", "is_recurring": true }] }` |
| `GET` | `/api/practitioners/{id}/slots` | **Calculer les créneaux libres pour réservation** | `?date=2026-09-22` ou `?start_date=2026-09-22&end_date=2026-09-29` |
| `GET` | `/api/practitioners/{id}/appointments` | Liste des rendez-vous d'un praticien | `?from=2026-09-01&to=2026-09-30&status=CONFIRMED` |

---

### 🧑 4. Patients
| Méthode | Route | Description | Paramètres / Body |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/patients` | Lister et rechercher des patients | `?search=Dupont` |
| `POST` | `/api/patients` | Créer un dossier patient | `{ "first_name": "Jean", "last_name": "Dupont", "email": "jean.dupont@email.local", "phone": "0612345678", "birth_date": "1985-06-12" }` |
| `GET` | `/api/patients/{id}` | Obtenir les détails d'un patient | - |
| `PUT` | `/api/patients/{id}` | Mettre à jour les informations du patient | `{ "phone": "0699887766" }` |
| `DELETE` | `/api/patients/{id}` | Supprimer un dossier patient | - |
| `GET` | `/api/patients/{id}/appointments` | Historique des rendez-vous du patient | `?status=CONFIRMED` |

---

### 🗓️ 5. Réservation & Gestion des Rendez-vous
| Méthode | Route | Description | Paramètres / Body |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/appointments` | **Réserver un rendez-vous** | `{ "practitioner_id": "pract-101", "patient_id": "pat-201", "start_time": "2026-09-22T09:30:00", "duration_minutes": 30, "reason": "Consultation générale" }` |
| `GET` | `/api/appointments/{id}` | Détails d'un rendez-vous | - |
| `PUT` | `/api/appointments/{id}` | Modifier motif ou notes | `{ "reason": "Bilan de santé", "notes": "Apporter analyses" }` |
| `PATCH` | `/api/appointments/{id}/reschedule` | **Reporter le rendez-vous** | `{ "start_time": "2026-09-23T14:00:00", "reason": "Changement planning patient" }` |
| `PATCH` | `/api/appointments/{id}/cancel` | **Annuler le rendez-vous** | `{ "reason": "Empêchement imprévu" }` |
| `PATCH` | `/api/appointments/{id}/complete` | Marquer comme terminé | - |
| `PATCH` | `/api/appointments/{id}/no-show` | Déclarer une absence patient (No-show) | - |

---

## 💡 Exemples Concrets d'Utilisation avec `curl`

### 1. Consulter les créneaux disponibles d'un médecin
```bash
curl -X GET "http://127.0.0.1:8000/api/practitioners/pract-101/slots?date=2026-09-22"
```

**Exemple de réponse (200 OK) :**
```json
{
  "success": true,
  "data": {
    "practitioner_id": "pract-101",
    "period": {
      "start_date": "2026-09-22",
      "end_date": "2026-09-22"
    },
    "slots": {
      "2026-09-22": [
        {
          "start_time": "2026-09-22T08:30:00+02:00",
          "end_time": "2026-09-22T09:00:00+02:00",
          "duration_minutes": 30
        },
        {
          "start_time": "2026-09-22T09:00:00+02:00",
          "end_time": "2026-09-22T09:30:00+02:00",
          "duration_minutes": 30
        }
      ]
    }
  }
}
```

### 2. Réserver un créneau
```bash
curl -X POST "http://127.0.0.1:8000/api/appointments" \
     -H "Content-Type: application/json" \
     -d '{
       "practitioner_id": "pract-101",
       "patient_id": "pat-201",
       "start_time": "2026-09-22T09:00:00",
       "duration_minutes": 30,
       "reason": "Consultation de suivi tensionnel"
     }'
```

**Exemple de réponse (201 Created) :**
```json
{
  "success": true,
  "data": {
    "id": "apt-a1b2c3d4",
    "practitioner_id": "pract-101",
    "patient_id": "pat-201",
    "start_time": "2026-09-22T09:00:00+02:00",
    "end_time": "2026-09-22T09:30:00+02:00",
    "duration_minutes": 30,
    "status": "CONFIRMED",
    "reason": "Consultation de suivi tensionnel",
    "notes": null,
    "cancellation_reason": null,
    "created_at": "2026-09-18T21:15:00+02:00",
    "updated_at": "2026-09-18T21:15:00+02:00"
  }
}
```

### 3. Tenter une réservation en conflit (Double réservation)
Si un autre patient tente de réserver le même créneau :
```json
{
  "success": false,
  "error": {
    "code": 409,
    "message": "Le praticien (ID: pract-101) a déjà un rendez-vous planifié sur le créneau du 22/09/2026 09:00."
  }
}
```

---

## 🛠️ Outil CLI d'Administration

Le projet inclut l'utilitaire `bin/console.php` pour administrer l'application en ligne de commande :

```bash
# Lister l'ensemble des routes HTTP enregistrées
php bin/console.php routes

# Vérifier la santé du service
php bin/console.php health

# Réinitialiser la base de données de démonstration
php bin/console.php seed
```

---

## 📜 Licence

Ce projet est distribué sous licence libre **MIT**.
