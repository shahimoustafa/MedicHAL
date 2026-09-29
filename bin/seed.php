<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Domain\Model\Appointment;
use App\Domain\Model\AppointmentStatus;
use App\Domain\Model\Availability;
use App\Domain\Model\Patient;
use App\Domain\Model\Practitioner;
use App\Domain\Model\Speciality;
use App\Domain\Model\TimeSlot;
use App\Infrastructure\Persistence\Json\JsonDatabase;

$dbPath = __DIR__ . '/../data/database.json';
$db = new JsonDatabase($dbPath);
$db->clear();

echo "🌱 Début du peuplement de la base de données Santé Connect...\n";

// 1. Praticiens
$practitioners = [
    [
        'id' => 'pract-101',
        'first_name' => 'Sophie',
        'last_name' => 'Martin',
        'email' => 'sophie.martin@sante-connect.local',
        'phone' => '01 42 68 59 01',
        'speciality' => Speciality::GENERAL_PRACTICE,
        'address' => '14 Rue de Rivoli',
        'city' => 'Paris',
        'zip_code' => '75001',
        'consultation_fee' => 26.50,
        'slot_duration_minutes' => 30,
        'bio' => 'Médecin généraliste d\'expérience, conventionnée secteur 1.',
        'rpps_number' => '10002345678',
    ],
    [
        'id' => 'pract-102',
        'first_name' => 'Alexandre',
        'last_name' => 'Dupont',
        'email' => 'alexandre.dupont@sante-connect.local',
        'phone' => '04 78 42 10 20',
        'speciality' => Speciality::CARDIOLOGY,
        'address' => '25 Rue Bellecour',
        'city' => 'Lyon',
        'zip_code' => '69002',
        'consultation_fee' => 65.00,
        'slot_duration_minutes' => 45,
        'bio' => 'Cardiologue spécialisé en échocardiographie et prévention cardiovasculaire.',
        'rpps_number' => '10003456789',
    ],
    [
        'id' => 'pract-103',
        'first_name' => 'Camille',
        'last_name' => 'Bernard',
        'email' => 'camille.bernard@sante-connect.local',
        'phone' => '04 91 33 22 11',
        'speciality' => Speciality::DENTISTRY,
        'address' => '8 La Canebière',
        'city' => 'Marseille',
        'zip_code' => '13001',
        'consultation_fee' => 40.00,
        'slot_duration_minutes' => 30,
        'bio' => 'Chirurgien-dentiste, soins dentaires généraux et esthétique.',
        'rpps_number' => '10004567890',
    ],
    [
        'id' => 'pract-104',
        'first_name' => 'Thomas',
        'last_name' => 'Dubois',
        'email' => 'thomas.dubois@sante-connect.local',
        'phone' => '05 56 01 02 03',
        'speciality' => Speciality::PEDIATRICS,
        'address' => '3 Place des Quinconces',
        'city' => 'Bordeaux',
        'zip_code' => '33000',
        'consultation_fee' => 35.00,
        'slot_duration_minutes' => 30,
        'bio' => 'Pédiatre bienveillant, suivi des nourrissons, enfants et adolescents.',
        'rpps_number' => '10005678901',
    ],
    [
        'id' => 'pract-105',
        'first_name' => 'Émilie',
        'last_name' => 'Robert',
        'email' => 'emilie.robert@sante-connect.local',
        'phone' => '03 20 55 44 33',
        'speciality' => Speciality::DERMATOLOGY,
        'address' => '18 Rue Faidherbe',
        'city' => 'Lille',
        'zip_code' => '59000',
        'consultation_fee' => 55.00,
        'slot_duration_minutes' => 20,
        'bio' => 'Dermatologue vénérologue, dépistage cutané et traitements laser.',
        'rpps_number' => '10006789012',
    ],
];

foreach ($practitioners as $p) {
    $practitioner = new Practitioner(
        id: $p['id'],
        firstName: $p['first_name'],
        lastName: $p['last_name'],
        email: $p['email'],
        phone: $p['phone'],
        speciality: $p['speciality'],
        address: $p['address'],
        city: $p['city'],
        zipCode: $p['zip_code'],
        consultationFee: $p['consultation_fee'],
        slotDurationMinutes: $p['slot_duration_minutes'],
        bio: $p['bio'],
        rppsNumber: $p['rpps_number']
    );
    $db->insert('practitioners', $practitioner->getId(), $practitioner->toArray());

    // Disponibilités récurrentes du lundi (1) au vendredi (5)
    for ($day = 1; $day <= 5; $day++) {
        $availMorning = new Availability(
            id: sprintf('avail-%s-%d-am', $p['id'], $day),
            practitionerId: $p['id'],
            dayOfWeek: $day,
            startTime: '08:30',
            endTime: '12:30',
            isRecurring: true
        );
        $db->insert('availabilities', $availMorning->getId(), $availMorning->toArray());

        $availAfternoon = new Availability(
            id: sprintf('avail-%s-%d-pm', $p['id'], $day),
            practitionerId: $p['id'],
            dayOfWeek: $day,
            startTime: '14:00',
            endTime: '18:00',
            isRecurring: true
        );
        $db->insert('availabilities', $availAfternoon->getId(), $availAfternoon->toArray());
    }
}
echo sprintf("✅ %d praticiens et leurs disponibilités créés.\n", count($practitioners));

// 2. Patients
$patients = [
    [
        'id' => 'pat-201',
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@email.local',
        'phone' => '06 12 34 56 78',
        'birth_date' => '1985-06-12',
        'social_security_number' => '185067512345678',
        'address' => '5 Rue de la Paix, 75002 Paris',
    ],
    [
        'id' => 'pat-202',
        'first_name' => 'Marie',
        'last_name' => 'Curie',
        'email' => 'marie.curie@email.local',
        'phone' => '06 23 45 67 89',
        'birth_date' => '1990-11-07',
        'social_security_number' => '290117523456789',
        'address' => '12 Avenue Jean Jaurès, 69007 Lyon',
    ],
    [
        'id' => 'pat-203',
        'first_name' => 'Paul',
        'last_name' => 'Valéry',
        'email' => 'paul.valery@email.local',
        'phone' => '06 34 56 78 90',
        'birth_date' => '1978-10-30',
        'social_security_number' => '178101334567890',
        'address' => '20 Boulevard Michelet, 13008 Marseille',
    ],
    [
        'id' => 'pat-204',
        'first_name' => 'Claire',
        'last_name' => 'Chazal',
        'email' => 'claire.chazal@email.local',
        'phone' => '06 45 67 89 01',
        'birth_date' => '1995-03-22',
        'social_security_number' => '295033345678901',
        'address' => '45 Cours de l\'Intendance, 33000 Bordeaux',
    ],
];

foreach ($patients as $p) {
    $patient = new Patient(
        id: $p['id'],
        firstName: $p['first_name'],
        lastName: $p['last_name'],
        email: $p['email'],
        phone: $p['phone'],
        birthDate: $p['birth_date'],
        socialSecurityNumber: $p['social_security_number'],
        address: $p['address']
    );
    $db->insert('patients', $patient->getId(), $patient->toArray());
}
echo sprintf("✅ %d patients enregistrés.\n", count($patients));

// 3. Rendez-vous de démonstration
// Déterminer une date future (lundi prochain)
$nextMonday = new DateTimeImmutable('next monday 09:00');
$slot1 = new TimeSlot($nextMonday, $nextMonday->modify('+30 minutes'));
$slot2 = new TimeSlot($nextMonday->modify('+1 hour'), $nextMonday->modify('+1 hour 30 minutes'));

$appointment1 = new Appointment(
    id: 'apt-301',
    practitionerId: 'pract-101',
    patientId: 'pat-201',
    slot: $slot1,
    status: AppointmentStatus::CONFIRMED,
    reason: 'Consultation annuelle de routine',
    notes: 'Patient régulier, tension à vérifier.'
);
$db->insert('appointments', $appointment1->getId(), $appointment1->toArray());

$appointment2 = new Appointment(
    id: 'apt-302',
    practitionerId: 'pract-101',
    patientId: 'pat-202',
    slot: $slot2,
    status: AppointmentStatus::CONFIRMED,
    reason: 'Renouvellement traitement',
    notes: null
);
$db->insert('appointments', $appointment2->getId(), $appointment2->toArray());

echo "✅ Rendez-vous de démonstration planifiés.\n";
echo "🎉 Base de données initialisée avec succès dans data/database.json !\n";
