// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Zone de tracé de signature : la signature dessinée à la souris, au doigt ou au stylet est
 * recopiée, en PNG, dans le champ caché « signaturedata » du formulaire à chaque trait.
 *
 * @module     mod_these/signaturepad
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Branche une zone de tracé.
 *
 * @param {HTMLElement} pad Conteneur .these-signature-pad.
 */
const setup = (pad) => {
    const canvas = pad.querySelector('canvas');
    const form = pad.closest('form');
    const target = form ? form.querySelector('input[name="signaturedata"]') : null;
    if (!canvas || !target || pad.dataset.ready) {
        return;
    }
    pad.dataset.ready = '1';
    const context = canvas.getContext('2d');
    context.lineWidth = 2.2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#0b2a6f';
    let drawing = false;
    let drawn = false;

    const position = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * canvas.width / rect.width,
            y: (event.clientY - rect.top) * canvas.height / rect.height,
        };
    };

    canvas.style.touchAction = 'none';
    canvas.addEventListener('pointerdown', (event) => {
        drawing = true;
        canvas.setPointerCapture(event.pointerId);
        const point = position(event);
        context.beginPath();
        context.moveTo(point.x, point.y);
        context.lineTo(point.x + 0.1, point.y + 0.1);
        context.stroke();
        event.preventDefault();
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!drawing) {
            return;
        }
        const point = position(event);
        context.lineTo(point.x, point.y);
        context.stroke();
        drawn = true;
        event.preventDefault();
    });
    const stop = () => {
        if (drawing && drawn) {
            target.value = canvas.toDataURL('image/png');
        }
        drawing = false;
    };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointerleave', stop);
    canvas.addEventListener('pointercancel', stop);

    const clear = pad.querySelector('[data-action="these-signature-clear"]');
    if (clear) {
        clear.addEventListener('click', () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            target.value = '';
            drawn = false;
        });
    }
};

export const init = () => {
    document.querySelectorAll('.these-signature-pad').forEach(setup);
};
