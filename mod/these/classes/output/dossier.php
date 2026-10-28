<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_these\output;

use context;
use moodle_url;
use stdClass;

/**
 * Dossier complet : progression, statut, renvoi en cours, actions, sujet, diffusion, enregistrements,
 * soutenance, corrections, questionnaire, jury, documents signés et historique. Utilisé par la page de
 * l'étudiant, dossier.php et review.php.
 *
 * Toutes les décisions d'accès sont prises ici, à partir des droits reçus (these_get_rights()) : pour
 * qui ne connaît pas le sujet d'une thèse confidentielle, le titre, le titre anglais, le résumé, les
 * mots-clés, l'historique du sujet et les réponses au questionnaire n'entrent pas dans le contexte du
 * gabarit, remplacés par la mention « Thèse confidentielle ».
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier implements \renderable, \templatable {
    /** @var stdClass */
    protected $these;
    /** @var stdClass */
    protected $cm;
    /** @var context */
    protected $context;
    /** @var stdClass */
    protected $dossier;
    /** @var stdClass */
    protected $rights;
    /** @var bool */
    protected $withactions;

    /**
     * Constructeur.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     * @param bool $withactions Affiche les boutons d'action (pas sur les pages d'action elles-mêmes).
     */
    public function __construct(
        stdClass $these,
        stdClass $cm,
        context $context,
        stdClass $dossier,
        stdClass $rights,
        $withactions = true
    ) {
        $this->these = $these;
        $this->cm = $cm;
        $this->context = $context;
        $this->dossier = $dossier;
        $this->rights = $rights;
        $this->withactions = (bool) $withactions;
    }

    /**
     * Valeur texte d'une ligne de détail, omise si vide.
     *
     * @param string|null $value
     * @return array|null
     */
    protected static function text(?string $value): ?array {
        return (string) $value !== '' ? [parts::text($value)] : null;
    }

    /**
     * Valeur HTML formatée (format_text(), format_string()) d'une ligne de détail, omise si vide.
     *
     * @param string $value
     * @return array|null
     */
    protected static function html(string $value): ?array {
        return $value !== '' ? [parts::html($value)] : null;
    }

    /**
     * Fichiers d'une zone, si l'utilisateur peut la voir et qu'elle n'est pas vide.
     *
     * @param string $filearea
     * @param \renderer_base $output
     * @return array|null
     */
    protected function files(string $filearea, \renderer_base $output): ?array {
        if (!these_can_view_filearea($filearea, $this->rights)) {
            return null;
        }
        $links = new file_links($this->context, $filearea, $this->dossier->id);
        return $links->is_empty() ? null : [parts::files($links->export_for_template($output))];
    }

    /**
     * Section du sujet ; pour qui ne connaît pas le sujet d'une thèse confidentielle, ses champs sont
     * remplacés par la mention « Thèse confidentielle ».
     *
     * @param \renderer_base $output
     * @return detail_section
     */
    protected function subject_section(\renderer_base $output): detail_section {
        global $DB;

        $dossier = $this->dossier;
        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
        if ($this->rights->knowssubject) {
            $title = format_string($dossier->title);
            $title = [$title ? parts::html($title) : parts::text('-')];
            $titleen = self::html(format_string((string) $dossier->titleen));
            $summary = self::html(format_text((string) $dossier->summary, FORMAT_PLAIN));
            $keywords = self::text($dossier->keywords);
        } else {
            $masked = [parts::em(get_string('confidentialtitle', 'mod_these'))];
            $title = $titleen = $summary = $keywords = $masked;
        }
        return new detail_section(get_string('subject', 'mod_these'), [
            get_string('student', 'mod_these') => [parts::text($student ? fullname($student) : '-')],
            get_string('subjecttitle', 'mod_these') => $title,
            get_string('confidentialitylabel', 'mod_these') => !empty($dossier->confidential)
                ? [parts::badge(get_string('confidentialbadge', 'mod_these'), 'badge badge-danger')] : null,
            get_string('subjecttitleen', 'mod_these') => $titleen,
            get_string('subjectsummary', 'mod_these') => $summary,
            get_string('subjectkeywords', 'mod_these') => $keywords,
            get_string('director', 'mod_these') => [parts::text($director ? fullname($director) : '-')],
            get_string('codirector', 'mod_these') => self::text($dossier->codirectorname),
            get_string('subjectdocs', 'mod_these') => $this->files('subjectdocs', $output),
        ]);
    }

    /**
     * Section de la soutenance.
     *
     * @param \renderer_base $output
     * @return detail_section
     */
    protected function defense_section(\renderer_base $output): detail_section {
        $dossier = $this->dossier;
        $status = (int) $dossier->status;
        $dateformat = get_string('strftimedaydatetime', 'langconfig');
        $rows = [
            get_string('proposeddate', 'mod_these') =>
                $dossier->proposeddate ? [parts::text(userdate($dossier->proposeddate, $dateformat))] : null,
            get_string('defensedate', 'mod_these') =>
                $dossier->defensedate ? [parts::text(userdate($dossier->defensedate, $dateformat))] : null,
            get_string('defenselocation', 'mod_these') => self::text($dossier->defenselocation),
            get_string('manuscript', 'mod_these') => $this->files('manuscript', $output),
            get_string('defensedocs', 'mod_these') => $this->files('defensedocs', $output),
            get_string('directorreport', 'mod_these') => $this->files('directorreport', $output),
            get_string('generateddocuments', 'mod_these') => $this->files('generated', $output),
            get_string('signedscans', 'mod_these') => $this->files('signedscan', $output),
        ];
        if ($status === THESE_STATUS_CLOSED || these_in_corrections($status)) {
            $rows[get_string('defenseresult', 'mod_these')] = self::text(these_result_label($dossier->defenseresult));
            $rows[get_string('mention', 'mod_these')] = self::text(these_mention_label($dossier->mention));
            $rows[get_string('prizeproposal', 'mod_these')] = $dossier->prizeproposal ? [parts::text(get_string('yes'))] : null;
            $rows[get_string('minutes', 'mod_these')] = $this->files('pv', $output);
        }
        $rows[get_string('minutesadjourned', 'mod_these')] = $this->files('pvadjourned', $output);
        return new detail_section(get_string('defense', 'mod_these'), $rows);
    }

    /**
     * Section des corrections demandées par le jury.
     *
     * @param \renderer_base $output
     * @return detail_section
     */
    protected function corrections_section(\renderer_base $output): detail_section {
        $dossier = $this->dossier;
        return new detail_section(get_string('corrections', 'mod_these'), [
            get_string('correctionsnote', 'mod_these') => self::html(format_text((string) $dossier->correctionsnote, FORMAT_PLAIN)),
            get_string('correctionsdone', 'mod_these') => trim((string) $dossier->correctionsdone) !== ''
                ? self::html(format_text((string) $dossier->correctionsdone, FORMAT_PLAIN)) : null,
            get_string('correctedmanuscript', 'mod_these') => $this->files('corrections', $output),
        ]);
    }

    /**
     * Renvoi en cours, affiché au personnel et à son destinataire, avec le motif. Un renvoi de la DEVE
     * au directeur ne s'adresse pas à l'étudiant, qui n'en voit que le statut.
     *
     * @return array|false title, comment (HTML formaté)
     */
    protected function return_notice() {
        global $DB;

        $dossier = $this->dossier;
        $rights = $this->rights;
        $returnedtostudent = in_array((int) $dossier->status, [THESE_STATUS_SUBJECT_RETURNED, THESE_STATUS_DEFENSE_RETURNED], true);
        $showreturn = $rights->viewall || $rights->director || ($rights->owner && $returnedtostudent);
        if (!$showreturn || empty($dossier->returntime) || trim((string) $dossier->returncomment) === '') {
            return false;
        }
        $returner = $DB->get_record('user', ['id' => $dossier->returnedby]);
        $a = (object) [
            'by' => $returner ? fullname($returner) : get_string('system', 'mod_these'),
            'date' => userdate($dossier->returntime, get_string('strftimedate', 'langconfig')),
        ];
        return [
            'title' => get_string('returnedby', 'mod_these', $a),
            'comment' => format_text($dossier->returncomment, FORMAT_PLAIN),
        ];
    }

    /**
     * Données du gabarit mod_these/dossier.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $dossier = $this->dossier;
        $rights = $this->rights;
        $status = (int) $dossier->status;
        $knows = $rights->knowssubject;
        $involved = $rights->viewall || $rights->director || $rights->owner;

        $actor = these_step_actor($status);
        $waiting = false;
        if ($actor !== null) {
            $waiting = get_string('waitingfor', 'mod_these', these_actor_label($actor)) . ' — '
                . get_string('since', 'mod_these', userdate($dossier->statustime, get_string('strftimedate', 'langconfig')));
        }
        $adjourned = false;
        if (these_is_adjourned($dossier)) {
            $adjourned = get_string(
                $rights->student ? 'adjournednoticestudent' : 'adjournednotice',
                'mod_these',
                userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))
            );
        }
        $actionbar = false;
        if ($this->withactions) {
            $actions = new actions(these_get_dossier_actions($this->cm, $dossier, $rights), 'btn btn-primary mr-1 mb-1');
            $actionbar = $actions->is_empty() ? false : $actions->export_for_template($output);
        }

        $data = [
            'progress' => (new progress($dossier))->export_for_template($output),
            'status' => (new status_badge($status))->export_for_template($output),
            'waiting' => $waiting,
            'returnnotice' => $this->return_notice(),
            'adjourned' => $adjourned,
            'actionbar' => $actionbar,
            'subject' => $this->subject_section($output)->export_for_template($output),
            'embargo' => (new embargo($dossier, $rights))->export_for_template($output),
            'subjecthistory' => $knows && $involved
                ? (new subject_history($this->context, $dossier))->export_for_template($output) : false,
            'registration' => (new detail_section(get_string('registration', 'mod_these'), [
                get_string('devenumber', 'mod_these') => self::text($dossier->devenumber),
                get_string('libraryref', 'mod_these') => self::text($dossier->libraryref),
                get_string('a6validation', 'mod_these') => array_merge(
                    [parts::text(\mod_these\local\a6::label($dossier))],
                    // La DEVE la saisit sur sa page à part.
                    $rights->deve ? [parts::text(' '), parts::link(
                        \mod_these\local\a6::url($this->cm, $dossier->userid),
                        get_string('a6edit', 'mod_these'),
                        'btn btn-sm btn-secondary ml-2'
                    )] : []
                ),
            ]))->export_for_template($output),
            'defense' => false,
            'corrections' => false,
            'answers' => false,
            'jury' => false,
            'juryresend' => false,
            'roomconflicts' => '',
        ];

        if (these_rank_reached($dossier, THESE_STATUS_ACTIVE) || $dossier->proposeddate) {
            $data['defense'] = $this->defense_section($output)->export_for_template($output);
            // Salle déjà occupée sur le créneau, pour le personnel : au-dessus de la soutenance.
            $data['roomconflicts'] = $rights->viewall ? these_render_room_conflicts($dossier) : '';
            if (!empty($dossier->correctionsrequested)) {
                $data['corrections'] = $this->corrections_section($output)->export_for_template($output);
            }
            if ($knows && $involved) {
                $data['answers'] = \mod_these\local\questionnaire::answers_section((object) ['id' => $dossier->theseid], $dossier)
                    ->export_for_template($output);
            }
            $data['jury'] = (new jury(these_get_jury($dossier->id), $involved, $rights->deve))->export_for_template($output);
            // Conflits de jury, pour le personnel : au-dessus du tableau du jury.
            $data['jury']['conflicts'] = $rights->viewall ? these_render_jury_conflicts($dossier) : '';
            $juryvalidated = $status >= THESE_STATUS_DEFENSE_VALIDATED && $status < THESE_STATUS_CLOSED;
            if ($rights->deve && $juryvalidated) {
                $url = new moodle_url('/mod/these/jurynotify.php', [
                    'id' => $this->cm->id,
                    'dossierid' => $dossier->id,
                    'sesskey' => sesskey(),
                ]);
                $data['juryresend'] = ['url' => $url->out(false), 'label' => get_string('juryresendaccess', 'mod_these')];
            }
        }

        $data['signeddocuments'] = (new signed_documents($this->cm, $this->context, $dossier, $rights))
            ->export_for_template($output);
        $data['history'] = (new history($dossier->id, $rights->viewall || $rights->director, $knows))
            ->export_for_template($output);
        return $data;
    }
}
