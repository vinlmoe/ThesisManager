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
 * English strings for mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @category  string
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Thesis follow-up';
$string['modulename'] = 'Thesis follow-up';
$string['modulenameplural'] = 'Thesis follow-ups';
$string['modulename_help'] = 'Single view of the theses of several cohorts, each managed in its own thesis management activity: '
    . 'directors find the theses they supervise, the registrar and the library all files, and each teacher the juries they sit on '
    . 'with access to the documents. The jury directory (teachers, title, status) is also kept here.';
$string['pluginadministration'] = 'Thesis follow-up administration';
$string['thesesynthesisname'] = 'Name';
$string['thesesynthesis:addinstance'] = 'Add a thesis follow-up';
$string['thesesynthesis:view'] = 'View the thesis follow-up';
$string['thesesynthesis:managelinks'] = 'Choose the followed cohorts';
$string['thesesynthesis:managejurors'] = 'Keep the jury directory';
$string['linksnotice'] = 'After saving, choose from the activity page the thesis management activities (one per cohort) to follow.';
$string['managelinks'] = 'Followed cohorts';
$string['managelinks_help'] = 'Tick the thesis management activities whose files must appear here. Only those where you see all '
    . 'files (registrar, library) are offered. Unticking an activity removes it from the follow-up without changing anything in '
    . 'the original activity.';
$string['linked'] = 'Followed';
$string['linkssaved'] = 'The followed cohorts have been saved.';
$string['noactivities'] = 'No thesis management activity where you see all files was found.';
$string['nolinks'] = 'No cohort is followed in this activity yet.';
$string['hiddencourse'] = 'Hidden course';
$string['hiddenactivity'] = 'Hidden activity';
$string['promotion'] = 'Cohort';
$string['waitingfor'] = 'Waiting for';
$string['open'] = 'Open';
$string['myjuries'] = 'My juries';
$string['nojuries'] = 'You do not sit on any jury of the followed cohorts.';
$string['juryaccesspending'] = 'Access given when the registrar validates the jury';
$string['followed'] = 'Followed files';
$string['nodossiers'] = 'No file.';
$string['allpromotions'] = 'All cohorts';
$string['allstatuses'] = 'All statuses';
$string['privacy:metadata'] = 'The thesis follow-up activity stores no personal data: it reads the files in the linked thesis '
    . 'management activities.';

// Shared settings and search.
$string['thesesynthesis:managesettings'] = 'Define the document templates, grid and questionnaire of the followed activities';
$string['thesesynthesis:search'] = 'Search theses';
$string['searchtheses'] = 'Search theses';
$string['searchtheses_desc'] = 'Search in the followed "Thesis management" activities: the text is searched in the title, summary, '
    . 'keywords, names of the student, director and jury members, and answers to the pre-jury questionnaire. The DEVE and the '
    . 'library find every record of the activities where they have their rights; teachers find theses registered by the DEVE '
    . 'and not confidential, and those they direct.';
$string['searchtext'] = 'Text';
$string['defenseyear'] = 'Defence year';
$string['allyears'] = 'All years';
$string['searchresults'] = '{$a} thesis/theses found';
$string['searchnoresult'] = 'No thesis matches the search.';
