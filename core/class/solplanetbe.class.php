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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

/*
 * Relevé local d'une installation Solplanet équipée d'une clé AiSWEI.
 *
 * La clé expose un petit serveur HTTP en clair sur le port 8484, sans
 * authentification, et rend du JSON. Tout est en nombres entiers à échelle
 * fixe : 2301 pour 230,1 V, 345 pour 34,5 °C. Oublier la mise à l'échelle est
 * l'erreur qui saute le moins aux yeux, parce que les ordres de grandeur
 * restent plausibles tant qu'on ne les compare pas à l'application du
 * constructeur.
 *
 * Un équipement Jeedom = un appareil physique. Les trois familles partagent une
 * adresse IP mais n'ont ni les mêmes commandes, ni le même rythme utile.
 */
class solplanetbe extends eqLogic {

    /* ============================================================= L'APPAREIL */

    const PORT = 8484;

    const TYPE_INVERTER = 'inverter';
    const TYPE_METER    = 'meter';
    const TYPE_BATTERY  = 'battery';

    /*
     * La clé est sur le réseau local : elle répond en quelques dizaines de
     * millisecondes ou pas du tout. Cinq secondes sont déjà généreuses, et il
     * ne faut pas plus : le cron d'une minute est partagé avec les autres
     * plugins.
     */
    const DEFAULT_TIMEOUT = 5;

    /* Temps que cron() s'autorise, tous équipements confondus. */
    const CRON_BUDGET = 45;

    /* Minutes entre deux relevés, si l'utilisateur n'a rien choisi. */
    const DEFAULT_INTERVAL = 5;

    /* Garde-fou du bouton « Relever maintenant » : sans lui, un clic répété
     * interrogerait la clé en boucle. */
    const FORCE_MIN_INTERVAL = 15;

    /*
     * Une clé qui ne répond plus, c'est le cas de tous les soirs pour un
     * onduleur sans batterie : elle est alimentée par l'onduleur, qui s'arrête
     * avec le soleil. On espace les tentatives plutôt que de remplir le journal.
     */
    const FAILURES_BEFORE_BACKOFF = 3;
    const BACKOFF_MIN = 300;
    const BACKOFF_MAX = 3600;

    /* ============================================== TABLES DE CODES DE L'API */

    const INVERTER_STATUS = array(
        0 => 'En attente',
        1 => 'Normal',
        2 => 'Défaut',
        4 => 'Contrôle en cours',
    );

    const BATTERY_STATUS = array(
        1 => 'Au repos',
        2 => 'En charge',
        3 => 'En décharge',
        4 => 'Défaut',
    );

    /*
     * Codes d'erreur de l'onduleur. La table du constructeur réserve de larges
     * plages « Reserved » sans signification : elles ne sont pas recopiées, un
     * code absent d'ici est rendu tel quel. Le 305 est le plus fréquent et le
     * moins inquiétant : la clé répond, l'onduleur derrière elle est éteint.
     */
    const INVERTER_ERRORS = array(
        0  => 'Aucune erreur',
        1  => 'Échec de communication maître-esclave',
        2  => 'Échec de lecture/écriture de l\'EEPROM',
        3  => 'Échec du test des relais',
        4  => 'Injection de courant continu trop élevée',
        5  => 'Échec de l\'autotest',
        6  => 'Tension du bus continu trop élevée',
        7  => 'Tension de référence interne anormale',
        8  => 'Défaut du capteur de courant alternatif',
        9  => 'Défaut du dispositif différentiel (GFCI)',
        10 => 'Défaut matériel',
        11 => 'Versions maître et esclave incompatibles',
        32 => 'Vitesse de variation de la fréquence réseau (ROCOF)',
        33 => 'Fréquence réseau hors plage',
        34 => 'Tension réseau hors plage',
        35 => 'Perte du réseau',
        36 => 'Défaut du dispositif différentiel',
        37 => 'Surtension photovoltaïque',
        38 => 'Défaut d\'isolement',
        39 => 'Ventilateur bloqué',
        40 => 'Surchauffe de l\'onduleur',
        41 => 'Écart de tension réseau entre maître et esclave',
        42 => 'Écart de fréquence entre maître et esclave',
        43 => 'Écart de courant de terre entre maître et esclave',
        44 => 'Écart d\'injection continue entre maître et esclave',
        45 => 'Écart de tension et de fréquence entre maître et esclave',
        46 => 'Tension du bus continu trop élevée',
        47 => 'Incohérence entre maître et esclave',
        48 => 'Tension moyenne sur dix minutes hors plage',
        49 => 'Défaut du parafoudre PV1',
        50 => 'Défaut du parafoudre PV2',
        51 => 'Fusible défectueux',
        52 => 'Neutre absent',
        53 => 'Contrôle d\'isolement : tension résiduelle avant injection',
        54 => 'Contrôle d\'isolement : tension hors plage après injection',
        55 => 'Contrôle d\'isolement : chute de tension au basculement des relais',
        56 => 'Déclenchement différentiel à 30 mA',
        57 => 'Déclenchement différentiel à 60 mA',
        58 => 'Déclenchement différentiel à 150 mA',
        59 => 'Courant anormal sur la chaîne PV1',
        60 => 'Courant anormal sur la chaîne PV2',
        61 => 'Perte de communication DRMS',
        62 => 'Déconnexion demandée par le DRMS',
        63 => 'Court-circuit phase-terre',
        64 => 'Mode d\'entrée photovoltaïque incorrect',
        65 => 'Défaut de raccordement à la terre',
        70 => 'Échec de l\'autotest de détection d\'arc (AFCI)',
        71 => 'Arc électrique détecté ou mauvais contact',
        305 => 'Onduleur hors ligne',
        2000 => 'Surintensité en décharge',
        2001 => 'Surcharge',
        2002 => 'Batterie déconnectée',
        2003 => 'Sous-tension batterie',
        2004 => 'Batterie trop déchargée',
        2005 => 'Surtension batterie',
        2006 => 'Sous-tension réseau',
        2007 => 'Sous-tension réseau',
        2008 => 'Sous-fréquence réseau',
        2009 => 'Surfréquence réseau',
        2010 => 'Courant de fuite trop élevé',
        2011 => 'Échec du bus CAN en parallèle',
        2012 => 'Transformateur de courant réseau inversé',
        2013 => 'Sous-tension du bus',
        2014 => 'Surtension du bus',
        2015 => 'Surintensité onduleur',
        2016 => 'Surintensité en charge',
        2017 => 'Oscillation de la tension du bus',
        2018 => 'Sous-tension onduleur',
        2019 => 'Surtension onduleur',
        2020 => 'Fréquence onduleur anormale',
        2021 => 'Température IGBT trop élevée',
        2023 => 'Surchauffe batterie',
        2024 => 'Batterie trop froide',
        2027 => 'Perte de communication avec le BMS',
        2028 => 'Ventilateur défectueux',
        2030 => 'Erreur de phase réseau',
        2031 => 'Arc électrique',
        2032 => 'Échec du démarrage progressif du bus',
        2033 => 'Échec du démarrage progressif de l\'onduleur',
        2034 => 'Court-circuit du bus',
        2035 => 'Court-circuit onduleur',
        2037 => 'Isolement photovoltaïque trop faible',
        2038 => 'Défaut du relais de bus',
        2039 => 'Défaut du relais réseau',
        2040 => 'Défaut du relais de secours (EPS)',
        2041 => 'Défaut différentiel',
        2042 => 'Défaut du transformateur de courant',
        2043 => 'Court-circuit photovoltaïque',
        2044 => 'Défaut du relais de contournement',
        2045 => 'Défaut système',
        2046 => 'Surintensité continue',
        2047 => 'Surtension continue',
    );

    /* Erreurs batterie : un champ de bits, pas un code. 65535 signifie « pas
     * d'information », ce qui n'est pas la même chose que « aucune erreur ». */
    const BATTERY_ERRORS = array(
        0  => 'Erreur de données de communication',
        1  => 'Surtension d\'une cellule ou d\'un module',
        2  => 'Sous-tension d\'une cellule ou d\'un module',
        3  => 'Cellule trop chaude',
        4  => 'Cellule trop froide',
        5  => 'Surintensité en décharge',
        6  => 'Surintensité en charge',
        7  => 'Erreur de communication interne',
        8  => 'Déséquilibre entre cellules',
        9  => 'Isolement du système trop faible',
        10 => 'Défaut du capteur de tension',
        11 => 'Défaut du capteur de température',
        12 => 'Défaut du contacteur',
        13 => 'Échec de l\'autotest au démarrage',
        14 => 'Échec de l\'autotest du circuit intégré',
    );

    /* Un onduleur hybride, donc susceptible de porter une batterie. */
    const STORAGE_TYPES = array(11, 12, 13, 14, 15, 16, 17, 18, 19, 20);

    /* =============================================================== WIDGET */

    /*
     * Le gabarit de la commande « En ligne ». Il s'appuie sur tmplicon, fourni
     * par le coeur : inutile de livrer du HTML pour afficher une prise verte ou
     * rouge, et un gabarit du coeur suit les thèmes de Jeedom sans rien demander.
     */
    public static function templateWidget() {
        return array('info' => array('binary' => array(
            'connexion' => array('template' => 'tmplicon', 'replace' => array(
                '#_icon_on_#'  => "<i class='icon_green fas fa-plug'></i>",
                '#_icon_off_#' => "<i class='icon_red fas fa-plug'></i>",
            )),
        )));
    }

    /* ================================================================= CRON */

    /*
     * Toutes les minutes, parce que c'est le plus petit pas que Jeedom offre et
     * que chaque équipement décide ensuite lui-même s'il est temps. Un relevé
     * sur le LAN coûte quelques millisecondes : le budget sert à se protéger
     * d'un réseau qui ne répond plus, pas du nombre d'équipements.
     */
    public static function cron() {
        $deadline = microtime(true) + self::CRON_BUDGET;

        foreach (self::byType(__CLASS__, true) as $eqLogic) {
            try {
                if (microtime(true) > $deadline) {
                    log::add(__CLASS__, 'debug', __('Budget du cron épuisé, les équipements restants attendront la minute suivante.', __FILE__));
                    break;
                }
                if (!$eqLogic->shouldPoll()) {
                    continue;
                }
                $eqLogic->update();
            } catch (Throwable $e) {
                /* Un appareil en échec ne doit pas priver les autres de leur tour. */
                log::add(__CLASS__, 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
            }
        }
    }

    /* ================================================ CYCLE DE VIE eqLogic */

    /*
     * Aucune exception ici : le coeur crée l'équipement avec son seul nom, et
     * une validation stricte rendrait le bouton « Ajouter » définitivement
     * inopérant, sans message exploitable.
     */
    public function preSave() {
        if ($this->getId() == '') {
            $this->setIsEnable(1);
            $this->setIsVisible(1);
            $this->setDisplay('width', '300px');
        }

        if (trim((string) $this->getConfiguration('devtype', '')) === '') {
            $this->setConfiguration('devtype', self::TYPE_INVERTER);
        }
        if ((int) $this->getConfiguration('interval', 0) <= 0) {
            $this->setConfiguration('interval', self::DEFAULT_INTERVAL);
        }
        /* Par défaut on remet la production à zéro quand la clé ne répond plus :
         * c'est le comportement juste pour un onduleur sans batterie, qui est le
         * cas le plus courant. Une installation hybride, elle, décoche. */
        if ($this->getConfiguration('zero_when_offline', null) === null) {
            $this->setConfiguration('zero_when_offline', $this->getConfiguration('devtype') === self::TYPE_BATTERY ? 0 : 1);
        }

        /*
         * Le délai de communication du coeur est laissé tel quel, c'est-à-dire
         * désactivé : un onduleur sans batterie ne répond pas de la nuit, et
         * Jeedom lèverait une alerte tous les soirs pour un fonctionnement
         * normal. À l'utilisateur de l'activer si son installation est censée
         * répondre en permanence.
         */

        /* Sert à retrouver un appareil déjà créé lors d'une nouvelle
         * découverte, et à ne pas le créer deux fois. */
        $this->setLogicalId($this->deviceKey());
    }

    public function postSave() {
        $this->createCommands();

        if (!$this->isConfigured()) {
            return;
        }

        /*
         * Relevé immédiat, pour que l'utilisateur voie ses valeurs à la seconde
         * où il enregistre et non au prochain cron. Mais une clé injoignable ne
         * doit pas faire échouer l'enregistrement : l'appareil est enregistré,
         * les valeurs viendront plus tard.
         */
        try {
            if ($this->getCache('signature', '') !== $this->signature()) {
                $this->setCache('signature', $this->signature());
                $this->clearFailure();
            }
            $this->update(true);
        } catch (Throwable $e) {
            log::add(__CLASS__, 'info', $this->getHumanName() . ' : ' . $e->getMessage());
        }
    }

    public function preRemove() {
        try {
            $this->clearProblem();
        } catch (Throwable $e) {
            log::add(__CLASS__, 'debug', __('Nettoyage impossible :', __FILE__) . ' ' . $e->getMessage());
        }
        return true;
    }

    /* Un appareil est configuré dès qu'il a une adresse, et un numéro de série
     * quand sa famille en réclame un. */
    public function isConfigured() {
        if (trim((string) $this->getConfiguration('ip', '')) === '') {
            return false;
        }
        if ($this->getConfiguration('devtype') === self::TYPE_METER) {
            return true;
        }
        return trim((string) $this->getConfiguration('sn', '')) !== '';
    }

    /* Ce qui, en changeant, oblige à repartir de zéro. Le nom de l'équipement
     * n'en fait pas partie. */
    public function signature() {
        return $this->getConfiguration('devtype') . '|' . $this->getConfiguration('ip')
            . '|' . $this->getConfiguration('sn');
    }

    public function deviceKey() {
        return $this->getConfiguration('devtype', self::TYPE_INVERTER)
            . '-' . str_replace(array(' ', '/'), '', (string) $this->getConfiguration('ip'))
            . '-' . str_replace(array(' ', '/'), '', (string) $this->getConfiguration('sn'));
    }

    /* ======================================================== COUCHE RÉSEAU */

    public static function timeout() {
        $timeout = (int) config::byKey('api_timeout', __CLASS__, self::DEFAULT_TIMEOUT);
        return ($timeout > 0 && $timeout <= 30) ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /*
     * Un GET sur la clé. curl et non file_get_contents : lui seul permet de
     * borner séparément le temps d'établissement de la connexion et celui de la
     * réponse, et d'écarter un éventuel proxy système — la clé est sur le
     * réseau local, la faire passer par un proxy n'a aucun sens et échouerait.
     */
    public static function httpGet($_ip, $_path) {
        $ip = trim((string) $_ip);
        if ($ip === '') {
            throw new Exception(__('Aucune adresse IP n\'est renseignée.', __FILE__));
        }

        $url = 'http://' . $ip . ':' . self::PORT . '/' . ltrim($_path, '/');
        $timeout = self::timeout();

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception(__('Impossible d\'initialiser la requête HTTP.', __FILE__));
        }
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(2, (int) min(3, $timeout)),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY          => '',
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        ));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new Exception(sprintf(__('Clé injoignable à l\'adresse %s : %s', __FILE__), $ip, $error));
        }
        if ($code !== 200) {
            throw new Exception(sprintf(__('La clé a répondu %s sur %s.', __FILE__), $code, $_path));
        }
        return $body;
    }

    /*
     * La clé annonce du text/html, glisse parfois des octets nuls en fin de
     * trame et peut faire suivre le JSON de remplissage. On ne garde donc que ce
     * qui va de la première accolade à la dernière, et json_decode fait le reste.
     */
    public static function parse($_body) {
        $text = trim(str_replace("\0", '', (string) $_body));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new Exception(__('Réponse illisible : la clé n\'a pas renvoyé de JSON.', __FILE__));
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            throw new Exception(__('Réponse illisible :', __FILE__) . ' ' . json_last_error_msg());
        }
        return $data;
    }

    public static function query($_ip, $_path) {
        return self::parse(self::httpGet($_ip, $_path));
    }

    /* ============================================================ DÉCOUVERTE */

    /*
     * Interroge une adresse et rend la liste des appareils trouvés derrière
     * elle. Aucun équipement n'est créé ici : l'utilisateur voit d'abord ce qui
     * a été trouvé.
     */
    public static function discover($_ip) {
        $found = array();

        $inverters = self::query($_ip, 'getdev.cgi?device=2');
        if (!isset($inverters['inv']) || !is_array($inverters['inv']) || count($inverters['inv']) === 0) {
            throw new Exception(sprintf(__('Aucun onduleur derrière %s. Vérifiez l\'adresse : c\'est celle de la clé de communication, pas celle de votre box.', __FILE__), $_ip));
        }

        foreach ($inverters['inv'] as $inverter) {
            $sn = isset($inverter['isn']) ? trim((string) $inverter['isn']) : '';
            if ($sn === '') {
                continue;
            }
            $model = isset($inverter['model']) ? trim((string) $inverter['model']) : '';
            $found[] = array(
                'devtype' => self::TYPE_INVERTER,
                'ip'      => $_ip,
                'sn'      => $sn,
                'model'   => $model,
                'name'    => trim('Onduleur ' . $model),
            );

            if (!self::mayHaveBattery($inverter)) {
                continue;
            }
            /* On ne se fie pas au seul type d'onduleur : un hybride sans
             * batterie installée existe, et créer un équipement qui ne répondra
             * jamais ne rend service à personne. */
            try {
                $battery = self::query($_ip, 'getdev.cgi?device=4&sn=' . rawurlencode($sn));
                if (isset($battery['num']) && (int) $battery['num'] > 0) {
                    $found[] = array(
                        'devtype' => self::TYPE_BATTERY,
                        'ip'      => $_ip,
                        'sn'      => $sn,
                        'model'   => isset($battery['battery']['devtype']) ? (string) $battery['battery']['devtype'] : '',
                        'name'    => 'Batterie',
                    );
                }
            } catch (Throwable $e) {
                log::add(__CLASS__, 'debug', __('Pas de batterie sur', __FILE__) . ' ' . $sn . ' : ' . $e->getMessage());
            }
        }

        try {
            $meter = self::query($_ip, 'getdev.cgi?device=3');
            if (isset($meter['enb']) && (int) $meter['enb'] === 1) {
                $found[] = array(
                    'devtype' => self::TYPE_METER,
                    'ip'      => $_ip,
                    'sn'      => isset($meter['sn']) ? trim((string) $meter['sn']) : '',
                    'model'   => trim((isset($meter['manufactory']) ? $meter['manufactory'] . ' ' : '') . (isset($meter['name']) ? $meter['name'] : '')),
                    'name'    => 'Compteur',
                );
            }
        } catch (Throwable $e) {
            log::add(__CLASS__, 'debug', __('Pas de compteur :', __FILE__) . ' ' . $e->getMessage());
        }

        return $found;
    }

    private static function mayHaveBattery($_inverter) {
        if (isset($_inverter['mty']) && in_array((int) $_inverter['mty'], self::STORAGE_TYPES, true)) {
            return true;
        }
        return isset($_inverter['isn']) && strpos((string) $_inverter['isn'], 'BE') === 0;
    }

    /*
     * Crée ce qui manque parmi les appareils découverts, et rend le compte.
     * Idempotent : relancer une découverte ne duplique rien, elle met seulement
     * l'adresse à jour si la clé a changé d'IP.
     */
    public static function createFromDiscovery($_devices) {
        $created = 0;

        $known = array(self::TYPE_INVERTER, self::TYPE_METER, self::TYPE_BATTERY);

        foreach ($_devices as $device) {
            /*
             * La liste revient du navigateur : elle a beau avoir été produite
             * par discover(), rien ne garantit qu'elle en soit sortie intacte.
             * On ne lit que les cinq clés attendues, et on refuse un type qui
             * n'existe pas plutôt que de créer un équipement qui n'interrogerait
             * jamais rien.
             */
            if (!is_array($device) || !isset($device['devtype']) || !in_array($device['devtype'], $known, true)) {
                continue;
            }
            $ip = isset($device['ip']) ? trim((string) $device['ip']) : '';
            if (filter_var($ip, FILTER_VALIDATE_IP) === false && !preg_match('/^[a-zA-Z0-9.-]{1,253}$/', $ip)) {
                continue;
            }

            $eqLogic = new self();
            $eqLogic->setConfiguration('devtype', $device['devtype']);
            $eqLogic->setConfiguration('ip', $ip);
            $eqLogic->setConfiguration('sn', isset($device['sn']) ? trim((string) $device['sn']) : '');
            $key = $eqLogic->deviceKey();

            $existing = self::byLogicalId($key, __CLASS__);
            if (is_object($existing)) {
                continue;
            }

            $eqLogic->setEqType_name(__CLASS__);
            $eqLogic->setConfiguration('model', isset($device['model']) ? (string) $device['model'] : '');
            $eqLogic->setConfiguration('interval', self::DEFAULT_INTERVAL);
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);

            /*
             * La table eqLogic porte une contrainte d'unicité sur
             * (name, object_id), tous plugins confondus : un « Compteur » peut
             * très bien exister ailleurs. On numérote plutôt que de faire
             * échouer la découverte pour un nom déjà pris.
             */
            $base = isset($device['name']) && trim((string) $device['name']) !== ''
                ? trim((string) $device['name']) : 'Solplanet';
            for ($try = 1; $try <= 10; $try++) {
                $eqLogic->setName($try === 1 ? $base : $base . ' ' . $try);
                try {
                    $eqLogic->save();
                    $created++;
                    break;
                } catch (Throwable $e) {
                    if ($try === 10) {
                        throw $e;
                    }
                }
            }
        }

        return $created;
    }

    /* ============================================================= COMMANDES */

    /*
     * Ce que porte chaque famille d'appareils. La forme — nombre de phases,
     * nombre de chaînes photovoltaïques — n'est pas devinée : elle vient de la
     * première réponse de la clé, et les commandes correspondantes ne sont
     * créées qu'à ce moment. Un monophasé n'a pas trois tensions.
     */
    public function catalog() {
        $devtype = $this->getConfiguration('devtype', self::TYPE_INVERTER);
        $shape = $this->shape();
        $list = array();

        if ($devtype === self::TYPE_INVERTER) {
            $list['puissance']             = array('Puissance', 'numeric', 'W', 'POWER');
            /* La somme des chaînes n'a d'intérêt que s'il y en a plusieurs :
             * avec une seule, elle ferait doublon avec « Puissance PV ». */
            if ($shape['mppt'] > 1) {
                $list['puissance_pv']      = array('Puissance photovoltaïque', 'numeric', 'W', 'POWER');
            }
            $list['energie_jour']          = array('Énergie du jour', 'numeric', 'kWh', 'DAILY_PRODUCTION');
            $list['energie_totale']        = array('Énergie totale', 'numeric', 'kWh', 'PRODUCTION');
            $list['temperature']           = array('Température', 'numeric', '°C', 'TEMPERATURE');
            $list['frequence']             = array('Fréquence réseau', 'numeric', 'Hz', '');
            $list['facteur_puissance']     = array('Facteur de puissance', 'numeric', '', '');
            $list['puissance_apparente']   = array('Puissance apparente', 'numeric', 'VA', '');
            $list['puissance_reactive']    = array('Puissance réactive', 'numeric', 'VAr', '');
            $list['heures_fonctionnement'] = array('Heures de fonctionnement', 'numeric', 'h', '');

            for ($i = 1; $i <= $shape['phases']; $i++) {
                $label = ($shape['phases'] > 1) ? ' phase ' . $i : '';
                $list['tension_ac_' . $i] = array('Tension réseau' . $label, 'numeric', 'V', 'VOLTAGE');
                $list['courant_ac_' . $i] = array('Courant réseau' . $label, 'numeric', 'A', '');
                if ($shape['phases'] > 1) {
                    $list['puissance_ac_' . $i] = array('Puissance phase ' . $i, 'numeric', 'W', '');
                }
            }
            for ($i = 1; $i <= $shape['mppt']; $i++) {
                $label = ($shape['mppt'] > 1) ? ' PV' . $i : ' PV';
                $list['tension_pv_' . $i]   = array('Tension' . $label, 'numeric', 'V', 'VOLTAGE');
                $list['courant_pv_' . $i]   = array('Courant' . $label, 'numeric', 'A', '');
                $list['puissance_pv_' . $i] = array('Puissance' . $label, 'numeric', 'W', 'POWER');
            }

            $list['etat']                 = array('État', 'string', '', '');
            $list['code_erreur']          = array('Code erreur', 'numeric', '', '');
            $list['libelle_erreur']       = array('Erreur', 'string', '', '');
            $list['code_avertissement']   = array('Code avertissement', 'numeric', '', '');
        }

        if ($devtype === self::TYPE_METER) {
            $list['puissance_reseau']   = array('Puissance réseau', 'numeric', 'W', 'POWER');
            $list['puissance_soutiree'] = array('Puissance soutirée', 'numeric', 'W', 'POWER');
            $list['puissance_injectee'] = array('Puissance injectée', 'numeric', 'W', 'POWER');
            $list['import_jour']        = array('Soutirage du jour', 'numeric', 'kWh', 'DAILY_CONSUMPTION');
            $list['export_jour']        = array('Injection du jour', 'numeric', 'kWh', 'DAILY_PRODUCTION');
            $list['import_total']       = array('Soutirage total', 'numeric', 'kWh', 'CONSUMPTION');
            $list['export_total']       = array('Injection totale', 'numeric', 'kWh', 'PRODUCTION');
        }

        if ($devtype === self::TYPE_BATTERY) {
            $list['soc']                = array('État de charge', 'numeric', '%', 'BATTERY');
            $list['soh']                = array('État de santé', 'numeric', '%', '');
            $list['puissance']          = array('Puissance batterie', 'numeric', 'W', 'POWER');
            $list['puissance_charge']   = array('Puissance de charge', 'numeric', 'W', 'POWER');
            $list['puissance_decharge'] = array('Puissance de décharge', 'numeric', 'W', 'POWER');
            $list['tension']            = array('Tension batterie', 'numeric', 'V', 'VOLTAGE');
            $list['courant']            = array('Courant batterie', 'numeric', 'A', '');
            $list['temperature']        = array('Température batterie', 'numeric', '°C', 'TEMPERATURE');
            $list['energie_chargee']    = array('Énergie chargée', 'numeric', 'kWh', '');
            $list['energie_dechargee']  = array('Énergie déchargée', 'numeric', 'kWh', '');
            $list['puissance_pv']       = array('Puissance photovoltaïque', 'numeric', 'W', 'POWER');
            $list['pv_jour']            = array('Production du jour', 'numeric', 'kWh', 'DAILY_PRODUCTION');
            $list['pv_total']           = array('Production totale', 'numeric', 'kWh', 'PRODUCTION');
            $list['eps_puissance']      = array('Puissance secourue', 'numeric', 'W', 'POWER');
            $list['eps_tension']        = array('Tension secourue', 'numeric', 'V', 'VOLTAGE');
            $list['eps_frequence']      = array('Fréquence secourue', 'numeric', 'Hz', '');
            $list['etat']               = array('État', 'string', '', '');
            $list['libelle_erreur']     = array('Erreur', 'string', '', '');
        }

        /* Communes aux trois familles. */
        $list['en_ligne'] = array('En ligne', 'binary', '', '');

        return $list;
    }

    /*
     * Création idempotente. On ne récrit jamais une commande existante : son
     * nom, sa visibilité et son historisation appartiennent à l'utilisateur dès
     * qu'il y a touché.
     */
    private function addCmdIfMissing($_logicalId, $_name, $_subType, $_unit, $_generic, $_order) {
        $cmd = $this->getCmd(null, $_logicalId);
        if (is_object($cmd)) {
            return $cmd;
        }

        $cmd = new solplanetbeCmd();
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId($_logicalId);
        $cmd->setName(__($_name, __FILE__));
        $cmd->setType('info');
        $cmd->setSubType($_subType);
        $cmd->setOrder($_order);
        $cmd->setIsVisible(1);
        /* Seules les grandeurs numériques valent d'être historisées : un état
         * textuel remplirait la base sans rien apprendre sur une courbe. */
        $cmd->setIsHistorized($_subType === 'numeric' ? 1 : 0);
        if ($_unit !== '') {
            $cmd->setUnite($_unit);
        }
        if ($_generic !== '') {
            $cmd->setGeneric_type($_generic);
        }
        /* Le nom du gabarit est préfixé de l'identifiant du plugin : c'est ce
         * que cmd::getWidgetTemplateCode() attend pour aller le chercher dans
         * templateWidget(). Sans le préfixe, le coeur cherche un fichier du
         * coeur portant ce nom, ne le trouve pas, et retombe sur l'affichage par
         * défaut sans rien signaler. */
        if ($_logicalId === 'en_ligne') {
            $cmd->setTemplate('dashboard', 'solplanetbe::connexion');
            $cmd->setTemplate('mobile', 'solplanetbe::connexion');
        }
        $cmd->save();
        return $cmd;
    }

    public function createCommands() {
        $order = 1;
        foreach ($this->catalog() as $logicalId => $definition) {
            $this->addCmdIfMissing($logicalId, $definition[0], $definition[1], $definition[2], $definition[3], $order++);
        }

        $refresh = $this->getCmd(null, 'rafraichir');
        if (!is_object($refresh)) {
            $refresh = new solplanetbeCmd();
            $refresh->setEqLogic_id($this->getId());
            $refresh->setLogicalId('rafraichir');
            $refresh->setName(__('Rafraîchir', __FILE__));
            $refresh->setType('action');
            $refresh->setSubType('other');
            $refresh->setOrder($order);
            $refresh->setIsVisible(1);
            $refresh->save();
        }
    }

    /*
     * Rattrape les équipements déjà créés quand une mise à jour du plugin ajoute
     * des commandes : createCommands() ne tourne qu'au postSave. Hors ligne,
     * volontairement — voir plugin_info/install.php.
     */
    public static function rebuildCommands() {
        $count = 0;
        foreach (self::byType(__CLASS__) as $eqLogic) {
            try {
                $eqLogic->createCommands();
                $count++;
            } catch (Throwable $e) {
                log::add(__CLASS__, 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
            }
        }
        return $count;
    }

    /*
     * Écriture d'une valeur. Ce nom, et pas setCmd() : à l'enregistrement d'un
     * équipement, utils::a2o() appelle « set » suivi de chaque clé du formulaire,
     * et la page envoie toujours une clé « cmd ». Une méthode setCmd() déclarée
     * ici tuerait la sauvegarde sur une erreur fatale, avant toute écriture.
     */
    private function publishCmd($_logicalId, $_value) {
        if ($_value === null) {
            return;
        }
        $cmd = $this->getCmd(null, $_logicalId);
        if (!is_object($cmd)) {
            return;
        }
        $this->checkAndUpdateCmd($cmd, $_value);
    }

    /* ================================================================ RELEVÉ */

    public function shape() {
        $shape = $this->getCache('shape', array());
        return array(
            'phases' => (isset($shape['phases']) && (int) $shape['phases'] > 0) ? (int) $shape['phases'] : 1,
            'mppt'   => (isset($shape['mppt']) && (int) $shape['mppt'] > 0) ? (int) $shape['mppt'] : 1,
        );
    }

    /* La forme telle que la réponse la décrit. Une réponse tronquée — l'onduleur
     * éteint derrière une clé encore alimentée — ne décrit rien : on retombe
     * alors sur la forme mémorisée, pour continuer d'écrire des null plutôt que
     * de faire disparaître des commandes de la mise à jour. */
    public function shapeOf($_raw) {
        $memorised = $this->shape();
        $phases = (isset($_raw['vac']) && is_array($_raw['vac'])) ? count($_raw['vac']) : 0;
        $mppt   = (isset($_raw['vpv']) && is_array($_raw['vpv'])) ? count($_raw['vpv']) : 0;
        return array(
            'phases' => $phases > 0 ? $phases : $memorised['phases'],
            'mppt'   => $mppt > 0 ? $mppt : $memorised['mppt'],
        );
    }

    public function endpoint() {
        $sn = rawurlencode(trim((string) $this->getConfiguration('sn', '')));
        switch ($this->getConfiguration('devtype', self::TYPE_INVERTER)) {
            case self::TYPE_METER:
                return 'getdevdata.cgi?device=3';
            case self::TYPE_BATTERY:
                return 'getdevdata.cgi?device=4&sn=' . $sn;
            default:
                return 'getdevdata.cgi?device=2&sn=' . $sn;
        }
    }

    /*
     * Un tour complet : lire, décoder, publier. $_force court-circuite
     * l'intervalle, pas le garde-fou du bouton.
     */
    public function update($_force = false) {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $raw = self::query($this->getConfiguration('ip'), $this->endpoint());
        } catch (Throwable $e) {
            $this->noteFailure($e->getMessage());
            $this->applyOffline();
            if ($_force) {
                throw $e;
            }
            return false;
        }

        $this->setCache('raw', $raw);
        $this->setCache('raw_at', date('Y-m-d H:i:s'));
        $this->clearFailure();

        $this->adjustShape($raw);

        foreach ($this->values($raw) as $logicalId => $value) {
            $this->publishCmd($logicalId, $value);
        }
        $this->publishCmd('en_ligne', 1);
        $this->refreshWidget();

        return true;
    }

    /*
     * Le nombre de phases et de chaînes photovoltaïques se lit dans la réponse.
     * Il ne change qu'à l'installation d'un nouvel onduleur, mais quand il
     * change il faut créer les commandes manquantes, sans quoi la troisième
     * phase n'apparaîtrait jamais.
     */
    private function adjustShape($_raw) {
        if ($this->getConfiguration('devtype') !== self::TYPE_INVERTER) {
            return;
        }
        $detected = $this->shapeOf($_raw);
        $current = $this->shape();
        if ($detected === $current) {
            return;
        }

        $this->setCache('shape', $detected);
        log::add(__CLASS__, 'info', sprintf(__('%s : %d phase(s) et %d chaîne(s) photovoltaïque(s) détectées, commandes mises à jour.', __FILE__), $this->getHumanName(), $detected['phases'], $detected['mppt']));
        $this->createCommands();
    }

    /* Mise à l'échelle. Une valeur absente rend null, et null ne s'écrit pas :
     * mieux vaut une commande qui garde sa dernière valeur qu'une commande à
     * zéro qui déclenchera les scénarios de l'utilisateur. */
    public static function scaled($_raw, $_key, $_factor = 1, $_decimals = 1) {
        if (!isset($_raw[$_key]) || !is_numeric($_raw[$_key])) {
            return null;
        }
        return round(((float) $_raw[$_key]) * $_factor, $_decimals);
    }

    private static function at($_raw, $_key, $_index, $_factor = 1, $_decimals = 1) {
        if (!isset($_raw[$_key]) || !is_array($_raw[$_key]) || !isset($_raw[$_key][$_index])) {
            return null;
        }
        if (!is_numeric($_raw[$_key][$_index])) {
            return null;
        }
        return round(((float) $_raw[$_key][$_index]) * $_factor, $_decimals);
    }

    /*
     * Traduction de la réponse brute en valeurs de commandes. Les facteurs sont
     * ceux du protocole AiSWEI : dixièmes de volt, centièmes d'ampère,
     * dixièmes de kilowattheure. C'est le coeur du plugin, et la seule partie
     * qu'un test hors ligne peut réellement vérifier.
     */
    public function values($_raw) {
        switch ($this->getConfiguration('devtype', self::TYPE_INVERTER)) {
            case self::TYPE_METER:
                return $this->meterValues($_raw);
            case self::TYPE_BATTERY:
                return $this->batteryValues($_raw);
            default:
                return $this->inverterValues($_raw);
        }
    }

    private function inverterValues($_raw) {
        $out = array(
            'puissance'             => self::scaled($_raw, 'pac', 1, 0),
            'energie_jour'          => self::scaled($_raw, 'etd', 0.1, 1),
            'energie_totale'        => self::scaled($_raw, 'eto', 0.1, 1),
            'temperature'           => self::scaled($_raw, 'tmp', 0.1, 1),
            'frequence'             => self::scaled($_raw, 'fac', 0.01, 2),
            'facteur_puissance'     => self::scaled($_raw, 'pf', 0.01, 2),
            'puissance_apparente'   => self::scaled($_raw, 'sac', 1, 0),
            'puissance_reactive'    => self::scaled($_raw, 'qac', 1, 0),
            'heures_fonctionnement' => self::scaled($_raw, 'hto', 1, 0),
            'code_avertissement'    => self::scaled($_raw, 'wan', 1, 0),
        );

        /*
         * Le nombre de phases et de chaînes se lit dans la réponse elle-même, et
         * non dans la forme mémorisée : si le cache de Jeedom a été vidé, les
         * commandes créées sont toujours là et doivent continuer d'être
         * alimentées. La forme mémorisée ne sert qu'à décider quelles commandes
         * créer, et elle se reconstruit au premier relevé réussi.
         */
        $shape = $this->shapeOf($_raw);

        for ($i = 1; $i <= $shape['phases']; $i++) {
            $out['tension_ac_' . $i] = self::at($_raw, 'vac', $i - 1, 0.1, 1);
            $out['courant_ac_' . $i] = self::at($_raw, 'iac', $i - 1, 0.1, 2);
            if ($shape['phases'] > 1) {
                $out['puissance_ac_' . $i] = self::scaled($_raw, 'pac' . $i, 1, 0);
            }
        }

        /* La clé ne donne pas la puissance par chaîne : elle se calcule, et la
         * somme est la vraie production continue, celle qui manque le plus dans
         * les relevés faits à la main. */
        $pv = null;
        for ($i = 1; $i <= $shape['mppt']; $i++) {
            $voltage = self::at($_raw, 'vpv', $i - 1, 0.1, 1);
            $current = self::at($_raw, 'ipv', $i - 1, 0.01, 2);
            $out['tension_pv_' . $i] = $voltage;
            $out['courant_pv_' . $i] = $current;
            if ($voltage === null || $current === null) {
                $out['puissance_pv_' . $i] = null;
                continue;
            }
            $power = round($voltage * $current, 0);
            $out['puissance_pv_' . $i] = $power;
            $pv = ($pv === null) ? $power : $pv + $power;
        }
        $out['puissance_pv'] = $pv;

        if (isset($_raw['flg']) && is_numeric($_raw['flg'])) {
            $status = (int) $_raw['flg'];
            $out['etat'] = isset(self::INVERTER_STATUS[$status])
                ? __(self::INVERTER_STATUS[$status], __FILE__)
                : sprintf(__('Inconnu (%s)', __FILE__), $status);
        }
        if (isset($_raw['err']) && is_numeric($_raw['err'])) {
            $code = (int) $_raw['err'];
            $out['code_erreur'] = $code;
            $out['libelle_erreur'] = self::errorLabel($code);
        }

        return $out;
    }

    public static function errorLabel($_code) {
        $code = (int) $_code;
        if (isset(self::INVERTER_ERRORS[$code])) {
            return __(self::INVERTER_ERRORS[$code], __FILE__);
        }
        return sprintf(__('Erreur %s', __FILE__), $code);
    }

    private function meterValues($_raw) {
        $out = array(
            'import_jour'   => self::scaled($_raw, 'itd', 0.01, 2),
            'export_jour'   => self::scaled($_raw, 'otd', 0.01, 2),
            'import_total'  => self::scaled($_raw, 'iet', 0.1, 1),
            'export_total'  => self::scaled($_raw, 'oet', 0.1, 1),
        );

        /*
         * Le compteur rend une puissance signée : positive au soutirage,
         * négative à l'injection. Les widgets énergie de Jeedom, eux, attendent
         * deux grandeurs positives distinctes — on fournit les trois, la signée
         * pour les scénarios et les deux autres pour l'affichage.
         */
        $grid = self::scaled($_raw, 'pac', 1, 0);
        $out['puissance_reseau'] = $grid;
        if ($grid !== null) {
            $out['puissance_soutiree'] = $grid > 0 ? $grid : 0;
            $out['puissance_injectee'] = $grid < 0 ? -$grid : 0;
        }

        return $out;
    }

    private function batteryValues($_raw) {
        $out = array(
            'soc'               => self::scaled($_raw, 'soc', 1, 0),
            'soh'               => self::scaled($_raw, 'soh', 1, 0),
            'tension'           => self::scaled($_raw, 'vb', 0.01, 2),
            'courant'           => self::scaled($_raw, 'cb', 0.1, 1),
            'temperature'       => self::scaled($_raw, 'tb', 0.1, 1),
            'energie_chargee'   => self::scaled($_raw, 'ebi', 0.1, 1),
            'energie_dechargee' => self::scaled($_raw, 'ebo', 0.1, 1),
            'puissance_pv'      => self::scaled($_raw, 'ppv', 1, 0),
            'pv_jour'           => self::scaled($_raw, 'etdpv', 0.1, 1),
            'pv_total'          => self::scaled($_raw, 'etopv', 0.1, 1),
            'eps_puissance'     => self::scaled($_raw, 'pesp', 1, 0),
            'eps_tension'       => self::scaled($_raw, 'vesp', 0.1, 1),
            'eps_frequence'     => self::scaled($_raw, 'fesp', 0.01, 2),
        );

        $power = self::scaled($_raw, 'pb', 1, 0);
        $out['puissance'] = $power;
        if ($power !== null) {
            $out['puissance_charge'] = $power > 0 ? $power : 0;
            $out['puissance_decharge'] = $power < 0 ? -$power : 0;
        }

        if (isset($_raw['bst']) && is_numeric($_raw['bst'])) {
            $status = (int) $_raw['bst'];
            $out['etat'] = isset(self::BATTERY_STATUS[$status])
                ? __(self::BATTERY_STATUS[$status], __FILE__)
                : sprintf(__('Inconnu (%s)', __FILE__), $status);
        }
        $out['libelle_erreur'] = self::batteryErrorLabel($_raw);

        return $out;
    }

    /*
     * eb1 est un champ de bits, pas un code : plusieurs défauts peuvent être
     * levés en même temps. 65535 n'est pas « tous les défauts » mais « le BMS
     * n'a rien dit », ce qui arrive tant que la batterie n'a pas fini de
     * démarrer.
     */
    public static function batteryErrorLabel($_raw) {
        if (!isset($_raw['eb1']) || !is_numeric($_raw['eb1'])) {
            return null;
        }
        $bits = (int) $_raw['eb1'];
        if ($bits === 65535) {
            return __('Information indisponible', __FILE__);
        }
        if ($bits === 0) {
            return __('Aucune erreur', __FILE__);
        }
        $labels = array();
        foreach (self::BATTERY_ERRORS as $bit => $label) {
            if ($bits & (1 << $bit)) {
                $labels[] = __($label, __FILE__);
            }
        }
        return empty($labels) ? sprintf(__('Erreur %s', __FILE__), $bits) : implode(', ', $labels);
    }

    /* ======================================================== HORS LIGNE */

    /*
     * La clé est alimentée par l'onduleur : sur une installation sans batterie,
     * elle s'éteint avec le soleil. Ce n'est pas une panne, c'est la nuit.
     *
     * On force alors à zéro ce qui est effectivement nul — les puissances et les
     * courants — et on ne touche surtout pas aux index d'énergie : écrire zéro
     * dans « énergie totale » creuserait un trou dans l'historique et
     * déclencherait tous les scénarios bâtis dessus.
     */
    private function applyOffline() {
        $this->publishCmd('en_ligne', 0);

        if ((int) $this->getConfiguration('zero_when_offline', 1) !== 1) {
            $this->refreshWidget();
            return;
        }

        $zeroed = array('puissance', 'puissance_pv', 'puissance_apparente', 'puissance_reactive');
        $shape = $this->shape();
        for ($i = 1; $i <= $shape['phases']; $i++) {
            $zeroed[] = 'courant_ac_' . $i;
            $zeroed[] = 'puissance_ac_' . $i;
        }
        for ($i = 1; $i <= $shape['mppt']; $i++) {
            $zeroed[] = 'courant_pv_' . $i;
            $zeroed[] = 'puissance_pv_' . $i;
        }
        foreach ($zeroed as $logicalId) {
            $this->publishCmd($logicalId, 0);
        }
        $this->refreshWidget();
    }

    /* ==================================================== RYTHME ET ÉCHECS */

    public function shouldPoll() {
        if (!$this->isConfigured()) {
            return false;
        }

        $now = time();
        $last = (int) $this->getCache('polled_at', 0);
        $interval = max(1, (int) $this->getConfiguration('interval', self::DEFAULT_INTERVAL)) * 60;

        /* L'espacement après échec ne remplace pas l'intervalle, il s'y ajoute :
         * c'est le plus long des deux qui commande. */
        $wait = max($interval, (int) $this->getCache('backoff', 0));
        if ($now - $last < $wait) {
            return false;
        }

        $this->setCache('polled_at', $now);
        return true;
    }

    /* Le bouton de la page, lui, ne respecte que le garde-fou. */
    public function forcePoll() {
        $now = time();
        if ($now - (int) $this->getCache('forced_at', 0) < self::FORCE_MIN_INTERVAL) {
            throw new Exception(sprintf(__('Patientez %s secondes entre deux relevés manuels.', __FILE__), self::FORCE_MIN_INTERVAL));
        }
        $this->setCache('forced_at', $now);
        $this->setCache('polled_at', $now);
        return $this->update(true);
    }

    private function noteFailure($_message) {
        $failures = (int) $this->getCache('failures', 0) + 1;
        $this->setCache('failures', $failures);

        if ($failures < self::FAILURES_BEFORE_BACKOFF) {
            log::add(__CLASS__, 'debug', $this->getHumanName() . ' : ' . $_message);
            return;
        }

        $backoff = min(self::BACKOFF_MAX, self::BACKOFF_MIN * (1 << min(4, $failures - self::FAILURES_BEFORE_BACKOFF)));
        $this->setCache('backoff', $backoff);

        /*
         * « info » et non « error » : pour un onduleur sans batterie, ce message
         * est celui de toutes les nuits. Un journal qui crie chaque soir finit
         * par ne plus être lu le jour où la panne est réelle.
         */
        if ($failures === self::FAILURES_BEFORE_BACKOFF) {
            log::add(__CLASS__, 'info', $this->getHumanName() . ' : ' . $_message);
            $this->reportProblem($_message);
        }
    }

    private function clearFailure() {
        if ((int) $this->getCache('failures', 0) > 0) {
            $this->setCache('failures', 0);
            $this->setCache('backoff', 0);
            $this->clearProblem();
        }
    }

    /* Le dernier message d'échec, affiché sur la page de l'équipement. */
    public function getRefreshError() {
        return (string) $this->getCache('problem', '');
    }

    private function reportProblem($_text) {
        $this->setCache('problem', $_text);
    }

    private function clearProblem() {
        $this->setCache('problem', '');
    }

    /* =============================================== DONNÉES POUR LA PAGE */

    public function toAjax() {
        $shape = $this->shape();
        return array(
            'id'        => $this->getId(),
            'devtype'   => $this->getConfiguration('devtype'),
            'online'    => (int) $this->getCache('failures', 0) === 0,
            'failures'  => (int) $this->getCache('failures', 0),
            'problem'   => $this->getRefreshError(),
            'fetchedAt' => (string) $this->getCache('raw_at', ''),
            'phases'    => $shape['phases'],
            'mppt'      => $shape['mppt'],
            'raw'       => $this->getCache('raw', array()),
        );
    }
}

/*
 * Obligatoire même réduite au minimum : sans elle, le coeur refuse de créer ou
 * d'ouvrir un équipement du plugin.
 */
class solplanetbeCmd extends cmd {

    public function execute($_options = array()) {
        if ($this->getType() !== 'action') {
            return;
        }
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic)) {
            return;
        }
        if ($this->getLogicalId() === 'rafraichir') {
            $eqLogic->update(true);
        }
    }
}
