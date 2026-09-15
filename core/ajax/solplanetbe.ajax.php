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

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    /* isConnect('admin') est une égalité stricte de profil, pas une hiérarchie :
     * isConnect('user') serait faux pour un administrateur. */
    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    /* Depuis la 4.4, ajax::getToken() est déprécié et rend une chaîne vide :
     * contrôler un jeton ici casserait l'appel. L'authentification repose sur la
     * session et sur credentials: same-origin. */
    ajax::init();

    /*
     * Cherche ce qui répond derrière une adresse, sans rien créer. L'utilisateur
     * voit la liste avant de décider : une découverte qui créerait d'office
     * quatre équipements sur une mauvaise adresse serait pénible à défaire.
     */
    if (init('action') == 'discover') {
        unautorizedInDemo();
        ajax::success(solplanetbe::discover(trim(init('ip'))));
    }

    /* Crée les appareils retenus. Idempotent : ceux qui existent déjà sont
     * laissés en place. */
    if (init('action') == 'createDevices') {
        unautorizedInDemo();

        $devices = json_decode(init('devices'), true);
        if (!is_array($devices) || count($devices) === 0) {
            throw new Exception(__('Aucun appareil à créer.', __FILE__));
        }
        ajax::success(solplanetbe::createFromDiscovery($devices));
    }

    /* Relevé à la demande, depuis le bouton de la page. */
    if (init('action') == 'refresh') {
        unautorizedInDemo();

        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getEqType_name() != 'solplanetbe') {
            throw new Exception(__('Appareil introuvable :', __FILE__) . ' ' . init('id'));
        }
        $eqLogic->forcePoll();
        ajax::success($eqLogic->toAjax());
    }

    /* Alimente le panneau de diagnostic. Lit le cache, n'interroge jamais la
     * clé : ouvrir un onglet ne doit pas dépendre de l'état de l'onduleur. */
    if (init('action') == 'data') {
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getEqType_name() != 'solplanetbe') {
            throw new Exception(__('Appareil introuvable :', __FILE__) . ' ' . init('id'));
        }
        ajax::success($eqLogic->toAjax());
    }

    throw new Exception(__('Aucune méthode correspondante à :', __FILE__) . ' ' . init('action'));

/*
 * Throwable et non Exception : en PHP 8, une Error n'hérite pas d'Exception et
 * produirait un HTTP 500 sans corps JSON. Le JS du coeur réessaierait alors
 * trois fois avant d'afficher une erreur réseau incompréhensible.
 */
} catch (Throwable $e) {
    ajax::error(displayException($e), $e->getCode());
}
