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

/**
 * Fragments du contenu d'une cellule de tableau, rendus par le partiel mod_these/part.
 *
 * Chaque fragment porte toutes les clés de type, à false sauf la sienne : Mustache cherche une clé
 * absente dans les contextes parents, et un fragment sans la clé « phtml » pourrait sinon hériter
 * d'une valeur du gabarit appelant.
 *
 * Seuls html() et stronghtml() sont rendus sans échappement : ils ne reçoivent que du HTML déjà passé
 * par format_text() ou format_string().
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parts {
    /** @var string[] Clés de type d'un fragment. */
    const TYPES = ['ptext', 'phtml', 'pstrong', 'pstronghtml', 'pem', 'pbadge', 'pspan', 'plink', 'pmuted', 'pbr',
        'pfiles', 'pstatus'];

    /**
     * Fragment d'un type donné.
     *
     * @param string $type Une des clés de TYPES.
     * @param array $data Données du fragment.
     * @return array
     */
    private static function make(string $type, array $data): array {
        $part = array_fill_keys(self::TYPES, false);
        $part[$type] = $data;
        return $part;
    }

    /**
     * Texte brut, échappé par le gabarit.
     *
     * @param string|null $value
     * @return array
     */
    public static function text(?string $value): array {
        return self::make('ptext', ['value' => (string) $value]);
    }

    /**
     * HTML déjà passé par format_text() ou format_string(), rendu tel quel.
     *
     * @param string $value
     * @return array
     */
    public static function html(string $value): array {
        return self::make('phtml', ['value' => $value]);
    }

    /**
     * Texte en gras (libellé d'une ligne de détail).
     *
     * @param string $value
     * @return array
     */
    public static function strong(string $value): array {
        return self::make('pstrong', ['value' => $value]);
    }

    /**
     * Libellé en gras déjà passé par format_string() (intitulé d'une question).
     *
     * @param string $value
     * @return array
     */
    public static function stronghtml(string $value): array {
        return self::make('pstronghtml', ['value' => $value]);
    }

    /**
     * Texte en italique (sujet masqué d'une thèse confidentielle).
     *
     * @param string $value
     * @return array
     */
    public static function em(string $value): array {
        return self::make('pem', ['value' => $value]);
    }

    /**
     * Badge.
     *
     * @param string $value
     * @param string $class Classes CSS, « badge » compris.
     * @return array
     */
    public static function badge(string $value, string $class): array {
        return self::make('pbadge', ['value' => $value, 'class' => $class]);
    }

    /**
     * Texte dans un span de classe donnée.
     *
     * @param string $value
     * @param string $class
     * @return array
     */
    public static function span(string $value, string $class): array {
        return self::make('pspan', ['value' => $value, 'class' => $class]);
    }

    /**
     * Lien.
     *
     * @param \moodle_url $url
     * @param string $value
     * @param string $class
     * @return array
     */
    public static function link(\moodle_url $url, string $value, string $class): array {
        return self::make('plink', ['url' => $url->out(false), 'value' => $value, 'class' => $class]);
    }

    /**
     * Petit texte grisé sur sa propre ligne.
     *
     * @param string $value
     * @return array
     */
    public static function muted(string $value): array {
        return self::make('pmuted', ['value' => $value]);
    }

    /**
     * Saut de ligne.
     *
     * @return array
     */
    public static function br(): array {
        return self::make('pbr', ['value' => '']);
    }

    /**
     * Liens vers les fichiers d'une zone (contexte de mod_these/file_links).
     *
     * @param array $context Voir file_links::export_for_template().
     * @return array
     */
    public static function files(array $context): array {
        return self::make('pfiles', $context);
    }

    /**
     * Badge de statut (contexte de mod_these/status_badge).
     *
     * @param array $context Voir status_badge::export_for_template().
     * @return array
     */
    public static function status(array $context): array {
        return self::make('pstatus', $context);
    }
}
