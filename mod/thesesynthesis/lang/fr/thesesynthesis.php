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

/**
 * Chaînes en français de mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @category  string
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Suivi des thèses';
$string['modulename'] = 'Suivi des thèses';
$string['modulenameplural'] = 'Suivis des thèses';
$string['modulename_help'] = "Vue unique des thèses de plusieurs promotions, chacune gérée dans sa propre activité « Gestion des "
    . "thèses » : le directeur y retrouve les thèses qu'il dirige, la DEVE et la bibliothèque tous les dossiers, et chaque "
    . "enseignant les jurys où il siège avec l'accès aux documents. L'annuaire du jury (enseignants, titre, statut) s'y tient aussi.";
$string['pluginadministration'] = 'Administration du suivi des thèses';
$string['thesesynthesisname'] = 'Nom';
$string['thesesynthesis:addinstance'] = 'Ajouter un suivi des thèses';
$string['thesesynthesis:view'] = 'Consulter le suivi des thèses';
$string['thesesynthesis:managelinks'] = 'Choisir les promotions suivies';
$string['thesesynthesis:managejurors'] = "Tenir l'annuaire du jury";
$string['linksnotice'] = "Après l'enregistrement, choisissez depuis la page de l'activité les activités « Gestion des thèses » "
    . "(une par promotion) à suivre.";
$string['managelinks'] = 'Promotions suivies';
$string['managelinks_help'] = "Cochez les activités « Gestion des thèses » dont les dossiers doivent remonter ici. Seules sont "
    . "proposées celles où vous voyez tous les dossiers (DEVE, bibliothèque). Décocher une activité la retire du suivi sans rien "
    . "modifier dans l'activité d'origine.";
$string['linked'] = 'Suivie';
$string['linkssaved'] = 'Les promotions suivies sont enregistrées.';
$string['noactivities'] = "Aucune activité « Gestion des thèses » où vous voyez tous les dossiers n'a été trouvée.";
$string['nolinks'] = "Aucune promotion n'est encore suivie dans cette activité.";
$string['hiddencourse'] = 'Cours masqué';
$string['hiddenactivity'] = 'Activité masquée';
$string['promotion'] = 'Promotion';
$string['waitingfor'] = 'En attente de';
$string['open'] = 'Ouvrir';
$string['myjuries'] = 'Mes jurys';
$string['nojuries'] = "Vous ne siégez dans aucun jury des promotions suivies.";
$string['juryaccesspending'] = "Accès donné à la validation du jury par la DEVE";
$string['juryaccessclosed'] = 'Accès clos (dossier clos ou soutenance passée)';
$string['followed'] = 'Dossiers suivis';
$string['nodossiers'] = 'Aucun dossier.';
$string['allpromotions'] = 'Toutes les promotions';
$string['allstatuses'] = 'Tous les statuts';
$string['privacy:metadata'] = "L'activité Suivi des thèses ne stocke aucune donnée personnelle : elle relit les dossiers dans les "
    . "activités « Gestion des thèses » liées.";

// Paramètres partagés et recherche.
$string['thesesynthesis:managesettings'] = 'Définir les modèles des documents, la grille et le questionnaire des activités suivies';
$string['thesesynthesis:search'] = 'Rechercher des thèses';
$string['searchtheses'] = 'Rechercher des thèses';
$string['searchtheses_desc'] = 'Recherche dans les activités « Gestion des thèses » suivies : le texte est cherché dans le titre, le '
    . "résumé, les mots-clés, les noms de l'étudiant, du directeur et des membres du jury, et les réponses au questionnaire "
    . 'préalable au jury. La DEVE et la bibliothèque trouvent tous les dossiers des activités où elles ont leurs droits ; les '
    . "enseignants, les thèses enregistrées par la DEVE et non confidentielles, ainsi que celles qu'ils dirigent.";
$string['searchtext'] = 'Texte';
$string['defenseyear'] = 'Année de soutenance';
$string['allyears'] = 'Toutes les années';
$string['searchresults'] = '{$a} thèse(s) trouvée(s)';
$string['searchnoresult'] = 'Aucune thèse ne correspond à la recherche.';
