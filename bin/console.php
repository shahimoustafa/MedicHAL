<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Bootstrap;
use App\Infrastructure\Http\Request;

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'routes':
        echo "=== Routes de l'API Santé Connect ===\n";
        echo "GET    /api/health                       -> État de santé de l'API\n";
        echo "GET    /api/docs | /                     -> Documentation Swagger UI\n";
        echo "GET    /openapi.json                     -> Schéma OpenAPI 3.0\n";
        echo "GET    /api/specialities                 -> Liste des spécialités\n";
        echo "GET    /api/practitioners                -> Rechercher des praticiens (?speciality, ?city, ?search)\n";
        echo "POST   /api/practitioners                -> Créer un praticien\n";
        echo "GET    /api/practitioners/{id}           -> Détail praticien\n";
        echo "PUT    /api/practitioners/{id}           -> Modifier praticien\n";
        echo "DELETE /api/practitioners/{id}           -> Supprimer praticien\n";
        echo "GET    /api/practitioners/{id}/availabilities -> Plages de travail\n";
        echo "POST   /api/practitioners/{id}/availabilities -> Définir plages\n";
        echo "GET    /api/practitioners/{id}/slots     -> Calcul des créneaux libres (?date=...)\n";
        echo "GET    /api/practitioners/{id}/appointments -> Liste des RDV praticien\n";
        echo "GET    /api/patients                     -> Rechercher des patients\n";
        echo "POST   /api/patients                     -> Créer un patient\n";
        echo "GET    /api/patients/{id}                -> Détail patient\n";
        echo "PUT    /api/patients/{id}                -> Modifier patient\n";
        echo "DELETE /api/patients/{id}                -> Supprimer patient\n";
        echo "GET    /api/patients/{id}/appointments   -> Historique RDV patient\n";
        echo "POST   /api/appointments                 -> Réserver un rendez-vous\n";
        echo "GET    /api/appointments/{id}            -> Détail rendez-vous\n";
        echo "PUT    /api/appointments/{id}            -> Modifier motif/notes\n";
        echo "PATCH  /api/appointments/{id}/reschedule -> Reporter rendez-vous\n";
        echo "PATCH  /api/appointments/{id}/cancel     -> Annuler rendez-vous\n";
        echo "PATCH  /api/appointments/{id}/complete   -> Terminer rendez-vous\n";
        echo "PATCH  /api/appointments/{id}/no-show    -> Marquer absence patient\n";
        echo "GET    /api/documents                    -> Lister les documents médicaux\n";
        echo "POST   /api/documents                    -> Créer/téléverser un document médical\n";
        echo "GET    /api/documents/{id}               -> Détail d'un document médical\n";
        echo "DELETE /api/documents/{id}               -> Supprimer un document médical\n";
        echo "POST   /api/documents/export             -> Export groupé de documents (STUB)\n";
        echo "POST   /api/documents/test-ocr           -> Analyse OCR expérimentale (TODO / TEST)\n";
        echo "GET    /api/patients/{id}/documents      -> Documents d'un patient\n";
        break;

    case 'seed':
        require __DIR__ . '/seed.php';
        break;

    case 'health':
        $container = Bootstrap::createContainer();
        $router = $container->get(\App\Infrastructure\Http\Router::class);
        $res = $router->dispatch(new Request('GET', '/api/health'));
        echo json_encode($res->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        break;

    case 'openapi':
    case 'swagger':
        $container = Bootstrap::createContainer();
        $router = $container->get(\App\Infrastructure\Http\Router::class);
        $res = $router->dispatch(new Request('GET', '/openapi.json'));
        $json = json_encode($res->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents(__DIR__ . '/../openapi.json', $json);
        echo $json . "\n";
        break;

    case 'help':
    default:
        echo "=== Santé Connect CLI ===\n";
        echo "Usage: php bin/console.php [command]\n\n";
        echo "Commandes disponibles:\n";
        echo "  routes   : Affiche l'ensemble des routes et endpoints de l'API REST\n";
        echo "  openapi  : Affiche et exporte la spécification OpenAPI 3.0 / Swagger en JSON\n";
        echo "  seed     : Réinitialise et peuple la base de données de test\n";
        echo "  health   : Vérifie la santé et les métriques de l'API\n";
        echo "  help     : Affiche cette aide\n";
        break;
}
