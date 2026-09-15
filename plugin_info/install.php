<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../../core/php/core.inc.php';

function solplanetbe_install() {
}

/*
 * Une mise à jour qui ajoute des commandes ne les fait pas apparaître toute
 * seule sur les appareils déjà créés : createCommands() ne tourne qu'au postSave.
 *
 * Mais on ne peut pas se contenter d'un save() par équipement. Le coeur exécute
 * cette fonction DANS la requête HTTP et sans la détacher (plugin::launch()
 * lance le sous-processus sans « & » pour les fonctions d'installation) : avec
 * un postSave qui interroge la clé et un onduleur éteint, la page « Gestion des
 * plugins » partirait en timeout.
 *
 * rebuildCommands() ne fait donc que la partie hors ligne : créer ce qui manque.
 * Les valeurs viendront du prochain cron.
 */
function solplanetbe_update() {
    if (!class_exists('solplanetbe')) {
        log::add('solplanetbe', 'error', __('Classe du plugin introuvable : les commandes n\'ont pas été mises à jour.', __FILE__));
        return;
    }
    try {
        $count = solplanetbe::rebuildCommands();
        log::add('solplanetbe', 'info', sprintf(__('Mise à jour : %d appareil(s) revu(s).', __FILE__), $count));
    } catch (Throwable $e) {
        log::add('solplanetbe', 'error', __('Mise à jour des commandes :', __FILE__) . ' ' . $e->getMessage());
    }
}

/*
 * Attention : le coeur appelle cette fonction à la DÉSACTIVATION du plugin
 * (plugin::setIsEnable(0)), pas seulement à sa désinstallation. Il n'y a ici
 * rien à jeter qui ne soit pas déjà attaché aux équipements : le cache de
 * chacun part avec lui.
 */
function solplanetbe_remove() {
}
