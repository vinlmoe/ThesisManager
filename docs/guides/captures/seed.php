<?php
// Jeu de données des captures du guide, sur un Moodle de test vierge où mod_these et
// mod_thesesynthesis sont installés.
//
// Usage : php docs/guides/captures/seed.php /chemin/vers/moodle
//
// Chaque dossier est amené à son étape par les fonctions du circuit (dépôt, validations,
// signatures, rapport, documents, procès-verbal), comme dans les tests : historiques, signatures
// et documents sont ceux qu'un vrai dossier aurait. Les identifiants utiles aux captures sont
// écrits dans ids.json, à côté de ce script.

define('CLI_SCRIPT', true);
$moodle = $argv[1] ?? '/home/user/moodle/moodle';
require($moodle . '/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

global $DB, $CFG;
\core\session\manager::set_user(get_admin());
// Historiques et documents sont rédigés dans la langue de celui qui agit : tout se fait en français.
$DB->set_field('user', 'lang', 'fr', ['id' => get_admin()->id]);
force_current_language('fr');
$now = time();
$day = function (string $date): int {
    return strtotime($date);
};

// Site en français pour le module : son paquet de langue est complet ; le cœur de Moodle, dont le
// paquet n'est pas installé, reste en anglais.
$langdir = $CFG->dataroot . '/lang/fr';
check_dir_exists($langdir);
$formats = [
    'strftimedate' => '%d %B %Y', 'strftimedatefullshort' => '%d/%m/%Y', 'strftimedateshort' => '%d %B',
    'strftimedatetime' => '%d %B %Y, %H:%M', 'strftimedatetimeshort' => '%d/%m/%Y %H:%M', 'strftimedaydate' => '%A %d %B %Y',
    'strftimedaydatetime' => '%A %d %B %Y, %H:%M', 'strftimedayshort' => '%A %d %B', 'strftimedaytime' => '%a %H:%M',
    'strftimemonthyear' => '%B %Y', 'strftimerecent' => '%d %b, %H:%M', 'strftimerecentfull' => '%a %d %B %Y, %H:%M',
    'strftimetime' => '%H:%M',
];
$php = "<?php\n\$string['thislanguage'] = 'Français';\n\$string['thislanguageint'] = 'French';\n"
    . "\$string['parentlanguage'] = '';\n\$string['locale'] = 'fr_FR.UTF-8';\n\$string['decsep'] = ',';\n"
    . "\$string['thousandssep'] = ' ';\n";
foreach ($formats as $key => $value) {
    $php .= "\$string['$key'] = '$value';\n";
}
file_put_contents($langdir . '/langconfig.php', $php);
set_config('lang', 'fr');
set_config('timezone', 'Europe/Paris');
set_config('forcetimezone', 'Europe/Paris');

$gen = new testing_data_generator();


$course = $gen->create_course(['fullname' => 'Thèses vétérinaires', 'shortname' => 'THESES', 'numsections' => 1]);
$these = $gen->create_module('these', [
    'course' => $course->id,
    'name' => "Thèses d'exercice vétérinaire",
    'intro' => 'Dépôt du sujet et de la soutenance.',
    'establishmentname' => 'VetAgro Sup',
    'establishmentaddress' => "1 avenue Bourgelat\n69280 Marcy-l'Étoile",
    'establishmentcity' => "Marcy-l'Étoile",
    'signatoryname' => 'Pr Emmanuelle Soubeyran',
    'signatorytitle' => 'Directrice générale',
    'defenselocation' => 'Amphithéâtre Bourgelat',
    'minjury' => 3,
    'reminderdays' => 14,
    'universityemail' => 'theses@univ-lyon1.example.org',
    'deveemail' => 'deve@example.org',
]);
$cm = get_coursemodule_from_instance('these', $these->id);
$these = $DB->get_record('these', ['id' => $these->id]);
$context = context_module::instance($cm->id);

$users = [];
$mk = function (string $username, string $first, string $last, ?string $role, string $email = '') use ($gen, $course, &$users) {
    $user = $gen->create_user([
        'username' => $username, 'password' => 'Test1234!', 'firstname' => $first, 'lastname' => $last,
        'email' => $email !== '' ? $email : $username . '@example.org', 'lang' => 'fr',
    ]);
    if ($role) {
        $gen->enrol_user($user->id, $course->id, $role);
    }
    set_user_preference('drawer-open-index', 0, $user);
    set_user_preference('drawer-open-block', 0, $user);
    $users[$username] = $user;
    return $user;
};
$deve = $mk('sbonnet', 'Sylvie', 'Bonnet', 'editingteacher');
$agent1 = $mk('agarnier', 'Anne', 'Garnier', 'editingteacher');
$agent2 = $mk('proux', 'Paul', 'Roux', 'editingteacher');
$library = $mk('blefort', 'Bruno', 'Lefort', 'teacher');
$director = $mk('hdurand', 'Hélène', 'Durand', 'teacher');
$director2 = $mk('mleroy', 'Marc', 'Leroy', 'teacher');
$teacher = $mk('ncolin', 'Nadia', 'Colin', 'teacher');
$president = $mk('jdupont', 'Jean', 'Dupont', null, 'dupont@example.org');
$staff = $mk('scolarite', 'Sophie', 'Martel', null);

// Personnel qui voit tous les dossiers sans être la DEVE ni la bibliothèque.
$roleid = $gen->create_role(['shortname' => 'thesesviewall', 'name' => 'Scolarité (thèses)']);
assign_capability('mod/these:view', CAP_ALLOW, $roleid, context_system::instance()->id);
assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, context_system::instance()->id);
$gen->enrol_user($staff->id, $course->id, $roleid);
accesslib_clear_all_caches(true);

// Annuaire du jury.
$jurors = [];
foreach ([
    ['Pr', 'DUPONT', 'Jean', 'dupont@example.org', 'pr', 'Université Claude Bernard Lyon 1'],
    ['Dr', 'DURAND', 'Hélène', 'hdurand@example.org', 'mcfhdr', 'VetAgro Sup'],
    ['Dr', 'LEROY', 'Marc', 'mleroy@example.org', 'mcf', 'VetAgro Sup'],
    ['Dr', 'COLIN', 'Nadia', 'ncolin@example.org', 'mcf', 'VetAgro Sup'],
    ['Pr', 'FONTAINE', 'Marie', 'fontaine@example.org', 'pr', 'VetAgro Sup'],
    ['Dr', 'CURIE', 'Marie', 'curie@example.org', 'mcf', 'Oniris Nantes'],
] as [$title, $last, $first, $email, $status, $institution]) {
    $jurors[$email] = $DB->insert_record('these_juror', (object) ['title' => $title, 'lastname' => $last,
        'firstname' => $first, 'email' => $email, 'status' => $status, 'institution' => $institution, 'active' => 1,
        'timecreated' => $now, 'timemodified' => $now]);
}

// Suivi des thèses : il porte les paramètres partagés (questionnaire, modèles, grille).
$suivicourse = $gen->create_course(['fullname' => 'Suivi des thèses', 'shortname' => 'SUIVI', 'numsections' => 1]);
foreach ([$deve, $library, $director, $director2, $teacher, $agent1] as $user) {
    $gen->enrol_user($user->id, $suivicourse->id, in_array($user, [$deve, $agent1], true) ? 'editingteacher' : 'teacher');
}
$synthesis = $gen->create_module('thesesynthesis', ['course' => $suivicourse->id, 'name' => 'Suivi des thèses',
    'intro' => 'Suivi des thèses de toutes les promotions.']);
$DB->insert_record('thesesynthesis_link', (object) ['synthesisid' => $synthesis->id, 'thesecmid' => $cm->id,
    'timecreated' => $now]);
$scope = these_settings_scope($these);
// Paramètres du circuit de l'activité de suivi (aucun réglage du site) : signataires, deux agents
// DEVE. Les modèles d'origine ne contiennent aucune valeur propre à l'établissement : tout vient d'ici.
\mod_these\local\scopesettings::reset_cache();
\mod_these\local\scopesettings::set($scope, [
    'deveagent' => "agarnier, proux",
    'libraryagent' => 'blefort',
    'institution' => 'VetAgro Sup', 'university' => "l'Université Claude Bernard Lyon 1", 'visaplace' => 'Lyon',
    'devename' => 'Anne GARNIER', 'dgtitle' => 'La Directrice générale de VetAgro Sup',
    'ucbltitle' => "Le Président de l'Université Claude Bernard Lyon 1",
    'dgname' => 'Pr Emmanuelle Soubeyran', 'dgemail' => 'dg@example.org',
    'ccemname' => 'Pr Jean-Luc Cadoré', 'ccememail' => 'ccem@example.org',
    'ucblname' => 'M. Bruno Lina', 'ucblemail' => 'presidence@example.org',
    'defenserooms' => "Amphithéâtre Bourgelat\nSalle du conseil\nSalle 12 (bâtiment Pasteur)",
]);
$questions = [];
foreach ([
    ['list', 'Espèce étudiée', "Chien\nChat\nCheval\nBovins\nNAC", 1],
    ['multi', 'Méthodes', "Enquête\nÉtude rétrospective\nÉtude prospective\nEssai clinique", 0],
    ['text', "Laboratoire d'accueil", '', 0],
] as [$qtype, $label, $options, $required]) {
    these_save_question($scope, (object) ['qtype' => $qtype, 'label' => $label, 'options' => $options,
        'required' => $required]);
}
$questions = these_get_questions($scope);

// Fichiers et signatures.
$pdf = function (string $title, string $text): string {
    $doc = new pdf();
    $doc->setPrintHeader(false);
    $doc->setPrintFooter(false);
    $doc->AddPage();
    $doc->SetFont('helvetica', 'B', 18);
    $doc->Write(10, $title . "\n\n");
    $doc->SetFont('helvetica', '', 11);
    $doc->Write(6, $text);
    return $doc->Output('', 'S');
};
$fs = get_file_storage();
$file = function (string $filearea, int $dossierid, string $filename, string $content) use ($fs, $context): void {
    $fs->delete_area_files($context->id, 'mod_these', $filearea, $dossierid);
    $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_these', 'filearea' => $filearea,
        'itemid' => $dossierid, 'filepath' => '/', 'filename' => $filename], $content);
};
$sig = function (stdClass $user): stdClass {
    return (object) ['name' => fullname($user), 'png' => these_sample_signature_image()];
};
$reload = function (stdClass $dossier) use ($DB): stdClass {
    return $DB->get_record('these_dossier', ['id' => $dossier->id], '*', MUST_EXIST);
};

$jury = function (stdClass $dirjuror, string $diremail) use ($jurors): array {
    return [
        (object) ['juryrole' => 'president', 'fullname' => 'Jean Dupont', 'jurytitle' => 'Pr', 'jurystatus' => 'pr',
            'institution' => 'Université Claude Bernard Lyon 1', 'email' => 'dupont@example.org',
            'jurorid' => $jurors['dupont@example.org']],
        (object) ['juryrole' => 'director', 'fullname' => fullname($dirjuror), 'jurytitle' => 'Dr',
            'jurystatus' => $diremail === 'hdurand@example.org' ? 'mcfhdr' : 'mcf', 'institution' => 'VetAgro Sup',
            'email' => $diremail, 'jurorid' => $jurors[$diremail]],
        (object) ['juryrole' => 'member', 'fullname' => 'Marie Curie', 'jurytitle' => 'Dr', 'jurystatus' => 'mcf',
            'institution' => 'Oniris Nantes', 'email' => 'curie@example.org', 'jurorid' => $jurors['curie@example.org']],
        (object) ['juryrole' => 'guest', 'fullname' => 'Luc Petit', 'jurytitle' => 'Dr', 'jurystatus' => 'other',
            'institution' => "Clinique vétérinaire des Monts d'Or", 'email' => 'petit@example.org'],
    ];
};

/**
 * Amène un dossier, du dépôt du sujet jusqu'à l'étape voulue, par les fonctions du circuit.
 */
$advance = function (stdClass $student, array $spec, int $target) use (
    $these, $cm, $context, $deve, $library, $sig, $reload, $file, $pdf, $jury, $questions, $users, $fs
): stdClass {
    $director = $users[$spec['director'] ?? 'hdurand'];
    $diremail = $director->email;
    // Signatures encore attendues pour quitter l'étape (voir \mod_these\local\prerequisites), apposées
    // par leur signataire : agent DEVE (direction générale par délégation), président du jury, et, pour
    // l'université, scan déposé par la DEVE. Sauf les emplacements « document:emplacement » exclus.
    $complete = function (stdClass $d, array $except = []) use ($context, $student, $director, $library, $deve, $users, $fs) {
        $signers = ['student' => $student, 'director' => $director, 'library' => $library, 'president' => $users['jdupont'],
            'deve' => $users['agarnier'], 'dgrep' => $users['agarnier'], 'dg' => $users['agarnier']];
        foreach (\mod_these\local\prerequisites::missing($d) as $item) {
            [$doctype, $slot] = [$item['doctype'], $item['slot']];
            if (in_array($doctype . ':' . $slot, $except, true)) {
                continue;
            }
            if (in_array($slot, \mod_these\local\prerequisites::UNIVERSITY_SLOTS, true)) {
                $date = time() - DAYSECS;
                $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_these',
                    'filearea' => 'signedscan', 'itemid' => $d->id, 'filepath' => '/',
                    'filename' => userdate($date, '%Y-%m-%d') . "-$doctype-$slot-scan.pdf"], '%PDF-1.4 scan');
                these_sign_document($context, $d, $doctype, $slot, 0, \mod_these\local\scans::slot_signer_name($d, $slot),
                    '', '', ['method' => 'scan', 'verifiedby' => $deve->id, 'time' => $date]);
                continue;
            }
            $user = $signers[$slot] ?? null;
            these_sign_document($context, $d, $doctype, $slot, $user ? $user->id : 0,
                $user ? fullname($user) : these_inperson_signer($d, $slot)['name'], these_sample_signature_image(),
                '', ['delegated' => $slot === 'dg']);
        }
    };
    $dossier = these_create_dossier($these->id, $student->id, $student->id);
    $subject = (object) [
        'title' => $spec['title'], 'titleen' => $spec['titleen'] ?? '', 'summary' => $spec['summary'],
        'keywords' => $spec['keywords'], 'studyyear' => 'A5', 'directorid' => $director->id, 'codirectorname' => '',
        'confidential' => $spec['confidential'] ?? 0, 'externaldirector' => 0,
        'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => 1,
    ];
    $steps = [
        THESE_STATUS_DRAFT => fn($d) => these_save_subject($these, $cm, $d, $subject, false, $student->id),
        THESE_STATUS_SUBJECT_SUBMITTED => fn($d) => these_save_subject($these, $cm, $d, $subject, true, $student->id,
            $sig($student)),
        THESE_STATUS_SUBJECT_APPROVED => fn($d) => these_validate_step($these, $cm, $d, $director->id, [], '',
            $sig($director)),
        THESE_STATUS_SUBJECT_REGISTERED => fn($d) => these_validate_step($these, $cm, $d, $deve->id),
        THESE_STATUS_ACTIVE => fn($d) => these_validate_step($these, $cm, $d, $library->id,
            ['libraryref' => $spec['libraryref'] ?? 'SUDOC-' . (124000 + $d->id)]),
        THESE_STATUS_DEFENSE_SUBMITTED => function ($d) use ($these, $cm, $student, $spec, $jury, $director, $diremail,
                $sig, $file, $pdf, $questions) {
            $answers = [];
            foreach ($questions as $question) {
                if ($question->qtype === 'list') {
                    $answers['q' . $question->id] = $spec['species'] ?? 'Chien';
                } else if ($question->qtype === 'multi') {
                    $answers['q' . $question->id . '_1'] = 1;
                } else {
                    $answers['q' . $question->id] = $spec['lab'] ?? 'Unité de bactériologie, VetAgro Sup';
                }
            }
            these_save_answers($d, $questions, $answers);
            $file('manuscript', $d->id, 'manuscrit.pdf', $pdf('Manuscrit', $spec['title']));
            $data = (object) array_merge(these_jury_form_data($jury($director, $diremail)), [
                'proposeddate' => $spec['date'], 'defenselocation' => 'Amphithéâtre Bourgelat',
                'titleen' => $spec['titleen'] ?? '',
                'conclusions' => "Cette étude montre l'intérêt d'un suivi prolongé.\n\nElle ouvre des pistes pour la pratique.",
                'bbirthname' => strtoupper($student->lastname), 'bfirstnames' => $student->firstname,
                'bnbvolumes' => 1, 'bnbpages' => 112, 'bnbrefs' => 86,
                'bsummaryfr' => $spec['summary'], 'bsummaryen' => 'Summary of the study.',
                'bdiffusion' => 1, 'bembargo' => !empty($spec['embargo']) ? 1 : 0,
                'bembargoreason' => $spec['embargo'] ?? '',
                'ethicsnumber' => '',
            ]);
            // Grille de conformité remplie par l'étudiant : tous les éléments cochés.
            foreach (these_get_grid_items(these_settings_scope($these)) as $items) {
                foreach (array_keys($items) as $item) {
                    $data->{'grid_' . $item} = 1;
                }
            }
            these_save_defense($these, $cm, $d, $data, true, $student->id, $sig($student));
        },
        THESE_STATUS_DEFENSE_APPROVED => function ($d) use ($these, $cm, $director, $sig, $reload) {
            these_save_report($d, $director->id, "Travail rigoureux, bien conduit et clairement rédigé.\n\n"
                . "La discussion situe les résultats par rapport à la littérature.", 1);
            these_validate_step($these, $cm, $reload($d), $director->id, ['directortitle' => 'Maître de conférences'], '',
                $sig($director));
        },
        THESE_STATUS_DEFENSE_VALIDATED => function ($d) use ($these, $cm, $deve, $spec) {
            global $DB;
            $DB->set_field('these_jury', 'eligible', 1, ['dossierid' => $d->id]);
            these_validate_step($these, $cm, $d, $deve->id, ['defensedate' => $spec['date'],
                'devenumber' => these_suggest_devenumber($these->id)]);
        },
        THESE_STATUS_CONFORM => fn($d) => these_validate_step($these, $cm, $d, $library->id, [], '', $sig($library)),
        THESE_STATUS_SCHEDULED => function ($d) use ($these, $cm, $context, $deve, $reload) {
            these_generate_documents($these, $context, $d, $deve->id);
            these_transmit_documents($these, $cm, $context, $reload($d), $deve->id);
        },
        THESE_STATUS_CORRECTIONS => function ($d) use ($these, $cm, $deve, $file, $pdf) {
            $file('pv', $d->id, 'pv-signe.pdf', $pdf('Procès-verbal signé', 'Scan du procès-verbal signé par le jury.'));
            these_record_minutes($these, $cm, $d, $deve->id, (object) ['result' => THESE_RESULT_ADMITTED,
                'mention' => 'treshonorable', 'prizeproposal' => false, 'correctionsrequested' => true,
                'correctionsnote' => "Compléter la discussion sur les biais de recrutement.\n"
                    . 'Harmoniser les références bibliographiques.']);
        },
        THESE_STATUS_CORRECTIONS_SUBMITTED => function ($d) use ($these, $cm, $student, $file, $pdf) {
            $file('corrections', $d->id, 'manuscrit-corrige.pdf', $pdf('Manuscrit corrigé', 'Version corrigée.'));
            these_submit_corrections($these, $cm, $d, $student->id, "Discussion complétée (section 4.3).\n"
                . 'Références reprises au format de la bibliothèque universitaire.');
        },
        THESE_STATUS_CORRECTIONS_APPROVED => fn($d) => these_validate_step($these, $cm, $d, $director->id),
        THESE_STATUS_CORRECTIONS_CONFORM => fn($d) => these_validate_step($these, $cm, $d, $library->id),
        THESE_STATUS_CLOSED => fn($d) => these_validate_step($these, $cm, $d, $deve->id),
    ];
    foreach ($steps as $status => $step) {
        if ($status === THESE_STATUS_DRAFT) {
            $step($dossier);
            if ($target === THESE_STATUS_DRAFT) {
                break;
            }
            continue;
        }
        $complete($reload($dossier));
        $step($reload($dossier));
        $dossier = $reload($dossier);
        if ((int) $dossier->status !== $status) {
            throw new coding_exception("Étape $status non atteinte pour " . fullname($student) . " : statut {$dossier->status}");
        }
        if ($status === $target) {
            break;
        }
    }
    // Dossier montré « tout signé » à son étape, sauf les signatures volontairement laissées en attente.
    if (isset($spec['complete'])) {
        $complete($reload($dossier), $spec['complete']);
    }
    return $reload($dossier);
};

$ids = ['cmid' => (int) $cm->id, 'contextid' => (int) $context->id, 'synthesiscmid' =>
    (int) get_coursemodule_from_instance('thesesynthesis', $synthesis->id)->id, 'courseid' => (int) $course->id];
$specs = [
    // Clé => [prénom, nom, étape, sujet...].
    'hugo' => ['Hugo', 'Petit', THESE_STATUS_SUBJECT_SUBMITTED, [
        'title' => "Prise en charge de la douleur chronique chez le chien âgé",
        'summary' => "Évaluation d'un protocole multimodal en clinique.", 'keywords' => 'chien ; douleur ; gériatrie']],
    'nathan' => ['Nathan', 'Garnier', THESE_STATUS_SUBJECT_SUBMITTED, [
        'title' => 'Bien-être des poules pondeuses en élevage plein air',
        'summary' => 'Enquête dans vingt élevages.', 'keywords' => 'volailles ; bien-être ; élevage']],
    'ines' => ['Inès', 'Moreau', THESE_STATUS_SUBJECT_APPROVED, [
        'title' => "Parasitisme digestif des équidés de loisir en Rhône-Alpes",
        'summary' => 'Coproscopies et pratiques de vermifugation.', 'keywords' => 'cheval ; parasitologie']],
    'jules' => ['Jules', 'Fabre', THESE_STATUS_SUBJECT_REGISTERED, [
        'title' => "Évaluation de l'analgésie multimodale chez le chat",
        'summary' => 'Étude prospective randomisée.', 'keywords' => 'chat ; analgésie ; anesthésie']],
    'lucas' => ['Lucas', 'Bernard', THESE_STATUS_ACTIVE, [
        'title' => 'Antibiorésistance des entérobactéries chez le chat : enquête en clinique',
        'titleen' => 'Antimicrobial resistance of Enterobacterales in cats',
        'summary' => 'Enquête menée dans douze cliniques de la région.', 'keywords' => 'antibiorésistance ; chat',
        'date' => $day('2026-11-19 14:00')]],
    'romain' => ['Romain', 'Caron', THESE_STATUS_ACTIVE, [
        'title' => 'Leptospirose canine en zone périurbaine : étude rétrospective',
        'summary' => "Contexte, objectifs et démarche de l'étude.", 'keywords' => 'leptospirose ; chien']],
    'chloe' => ['Chloé', 'Lambert', THESE_STATUS_DEFENSE_SUBMITTED, [
        'title' => 'Dépistage de la cardiomyopathie hypertrophique chez le Maine Coon',
        'titleen' => 'Screening for hypertrophic cardiomyopathy in Maine Coon cats',
        'summary' => 'Échocardiographie de 150 chats.', 'keywords' => 'chat ; cardiologie ; génétique',
        'date' => $day('2026-11-12 14:00'),
        'embargo' => 'Brevet en cours de dépôt sur le protocole de dépistage rapide.']],
    'arthur' => ['Arthur', 'Blanc', THESE_STATUS_DEFENSE_SUBMITTED, [
        'title' => 'Usage des antiparasitaires chez les bovins allaitants',
        'summary' => 'Enquête auprès de cent éleveurs.', 'keywords' => 'bovins ; antiparasitaires',
        'date' => $day('2026-11-26 14:00')]],
    'zoe' => ['Zoé', 'Mercier', THESE_STATUS_DEFENSE_APPROVED, [
        'title' => "Douleur chronique chez le cheval âgé : évaluation d'une grille comportementale",
        'summary' => "Validation d'une grille d'évaluation.", 'keywords' => 'cheval ; douleur ; gériatrie',
        'date' => $day('2026-10-29 14:00'), 'species' => 'Cheval']],
    'manon' => ['Manon', 'Girard', THESE_STATUS_DEFENSE_VALIDATED, [
        'title' => 'Les zoonoses chez les nouveaux animaux de compagnie',
        'summary' => 'Revue et enquête auprès des praticiens.', 'keywords' => 'NAC ; zoonoses',
        'date' => $day('2026-11-05 10:00'), 'species' => 'NAC']],
    // Dossier validé par la DEVE et entièrement signé : conformité de la bibliothèque (capture 72).
    'lea' => ['Léa', 'Fontaine', THESE_STATUS_DEFENSE_VALIDATED, [
        'title' => 'Teigne du chat en refuge : prévalence et facteurs de risque',
        'summary' => 'Prélèvements dans six refuges.', 'keywords' => 'chat ; dermatologie ; refuge',
        'date' => $day('2026-11-03 14:00'), 'complete' => []]],
    'camille' => ['Camille', 'Martin', THESE_STATUS_CONFORM, [
        'title' => 'Leptospirose canine en zone périurbaine lyonnaise : étude rétrospective et prospective',
        'titleen' => 'Canine leptospirosis in peri-urban areas: a retrospective study',
        'summary' => "Contexte, objectifs et démarche de l'étude.", 'keywords' => 'leptospirose ; chien ; épidémiologie',
        'date' => $day('2026-10-28 14:00')]],
    'clara' => ['Clara', 'Morel', THESE_STATUS_CONFORM, [
        'title' => "Nouvelle molécule antiparasitaire chez le chien : essai préclinique",
        'summary' => 'Essai mené avec un partenaire industriel.', 'keywords' => 'chien ; antiparasitaire',
        'date' => $day('2026-10-29 10:00'), 'confidential' => 1, 'director' => 'mleroy']],
    'theo' => ['Théo', 'Lefèvre', THESE_STATUS_SCHEDULED, [
        'title' => "Prévalence de l'obésité chez le chien en milieu urbain",
        'summary' => 'Étude transversale en clinique.', 'keywords' => 'chien ; obésité ; nutrition',
        // Signatures de l'université revenues, sauf le contrat, dont le scan est déposé par la capture 98.
        'date' => $day('2026-10-22 14:00'), 'complete' => ['contract:ucbl']]],
    'sarah' => ['Sarah', 'Dumas', THESE_STATUS_SCHEDULED, [
        'title' => 'Effets du stress de transport chez le veau de boucherie',
        'summary' => 'Mesures du cortisol et comportement.', 'keywords' => 'bovins ; transport ; stress',
        'date' => $day('2026-10-21 10:00'), 'species' => 'Bovins']],
    'emilie' => ['Émilie', 'Robin', THESE_STATUS_CORRECTIONS, [
        'title' => 'Hygiène de la traite et qualité du lait en élevage caprin',
        'summary' => 'Suivi de dix élevages.', 'keywords' => 'caprins ; lait ; hygiène',
        'date' => $day('2026-10-15 14:00')]],
    'alice' => ['Alice', 'Roy', THESE_STATUS_CORRECTIONS_SUBMITTED, [
        'title' => "Infections urinaires récidivantes chez la chienne",
        'summary' => 'Étude rétrospective sur cinq ans.', 'keywords' => 'chien ; urologie',
        'date' => $day('2026-10-08 14:00')]],
    'pauline' => ['Pauline', 'Faure', THESE_STATUS_CORRECTIONS_APPROVED, [
        'title' => 'Prévention de la gale sarcoptique chez le renard en zone périurbaine',
        'summary' => 'Surveillance et modélisation.', 'keywords' => 'faune sauvage ; gale',
        'date' => $day('2026-10-01 14:00')]],
    'maxime' => ['Maxime', 'Perrin', THESE_STATUS_CORRECTIONS_CONFORM, [
        'title' => "Utilisation de l'échographie pulmonaire chez le veau",
        'summary' => 'Étude diagnostique.', 'keywords' => 'bovins ; échographie',
        'date' => $day('2026-09-24 14:00'), 'species' => 'Bovins']],
];
foreach ($specs as $key => [$first, $last, $target, $spec]) {
    $student = $mk($key, $first, $last, 'student');
    $ids[$key] = (int) $advance($student, $spec, $target)->id;
}
$mk('emma', 'Emma', 'Petit', 'student');

// Renvois : sujet de Nathan renvoyé par son directeur, dossier de soutenance d'Arthur renvoyé.
$nathan = $DB->get_record('these_dossier', ['id' => $ids['nathan']]);
these_return_dossier($these, $cm, $nathan, $director->id, 'student',
    'Préciser la population étudiée et le nombre d\'élevages visités.');
$arthur = $DB->get_record('these_dossier', ['id' => $ids['arthur']]);
these_return_dossier($these, $cm, $arthur, $director->id, 'student', 'Avancer la date : le président est indisponible ce jour-là.');

// Changement de sujet de Romain : trace de l'ancien sujet.
$romain = $DB->get_record('these_dossier', ['id' => $ids['romain']]);
these_change_subject($these, $cm, $romain, (object) [
    'title' => 'Leptospirose canine en zone périurbaine lyonnaise : étude rétrospective et prospective',
    'titleen' => '', 'summary' => "Contexte, objectifs et démarche de l'étude.", 'keywords' => 'leptospirose ; chien',
    'studyyear' => 'A5', 'directorid' => $director->id, 'confidential' => 0, 'externaldirector' => 0,
    'codirectorname' => '', 'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => 0,
], $users['romain']->id, "Terrain d'étude élargi au suivi prospectif des cas, en accord avec la directrice.", $sig($users['romain']));
$romain = $DB->get_record('these_dossier', ['id' => $ids['romain']]);
these_validate_step($these, $cm, $romain, $director->id, [], '', $sig($director));

// Ajournement de Sarah : procès-verbal « Ajourné ».
$sarah = $DB->get_record('these_dossier', ['id' => $ids['sarah']]);
$file('pv', $sarah->id, 'pv-ajournement.pdf', $pdf('Procès-verbal signé', 'Thèse ajournée.'));
these_record_minutes($these, $cm, $sarah, $deve->id, (object) ['result' => THESE_RESULT_ADJOURNED, 'mention' => '',
    'prizeproposal' => false, 'correctionsrequested' => false, 'correctionsnote' => ''],
    'Le jury demande de reprendre l\'analyse statistique.');

// Camille : documents générés (non transmis), conclusions signées par le directeur.
$camille = $DB->get_record('these_dossier', ['id' => $ids['camille']]);
these_generate_documents($these, $context, $camille, $deve->id);
$ids['juryaccess'] = 'DemonstrationAccesJuryCamille2026'; // Alphanumérique : jury.php ne garde que lettres et chiffres.
$member = $DB->get_record('these_jury', ['dossierid' => $camille->id, 'juryrole' => 'president']);
$DB->set_field('these_jury', 'accesstoken', these_token_hash($ids['juryaccess']), ['id' => $member->id]);
$DB->set_field('these_jury', 'notifiedtime', $now, ['dossierid' => $camille->id]);

// Lucas : questionnaire déjà rempli, dossier actif.
these_save_answers($DB->get_record('these_dossier', ['id' => $ids['lucas']]), $questions, []);

// Signatures enregistrées des acteurs habitués du module (pas du premier agent DEVE, qui signe pour la
// première fois dans les captures).
foreach ([$director, $director2, $library, $agent2] as $user) {
    \mod_these\local\signatureagent::save_signature_image($user->id, these_sample_signature_image());
}
// Le second agent DEVE a déjà signé les visas, sauf ceux de Manon et de Camille, laissés au premier.
foreach (these_get_user_pending_signatures($agent2) as $pending) {
    if (in_array((int) $pending->dossierid, [$ids['manon'], $ids['camille']], true)) {
        continue;
    }
    these_sign_document(context_module::instance($pending->cm->id), $pending->dossier, $pending->doctype, $pending->slot,
        $agent2->id, fullname($agent2), these_sample_signature_image());
}

// Scan d'un contrat signé à la main, déposé par Playwright.
$img = imagecreatetruecolor(1240, 1754);
$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 30, 30, 30);
$blue = imagecolorallocate($img, 20, 40, 120);
imagefill($img, 0, 0, $white);
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$y = 200;
foreach (['Contrat de diffusion électronique (E04)', '', 'Auteur : Théo Lefèvre', '', "Visa de la présidence de l'université",
        '', 'Fait à Lyon, le 22 octobre 2026'] as $i => $line) {
    is_readable($font) ? imagettftext($img, $i ? 26 : 34, 0, 140, $y, $black, $font, $line)
        : imagestring($img, 5, 140, $y, $line, $black);
    $y += 70;
}
imagesetthickness($img, 4);
for ($x = 0; $x < 360; $x += 4) {
    imageline($img, 160 + $x, 1000 + (int) (40 * sin($x / 25)), 164 + $x, 1000 + (int) (40 * sin(($x + 4) / 25)), $blue);
}
imagepng($img, __DIR__ . '/scan-contrat-signe.png');

purge_all_caches();
file_put_contents(__DIR__ . '/ids.json', json_encode($ids, JSON_PRETTY_PRINT));
echo json_encode($ids) . "\n";
