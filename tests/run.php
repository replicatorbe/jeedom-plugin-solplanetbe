<?php
/* Rejeu hors ligne du plugin sur des réponses de la clé AiSWEI.
 *
 *   php tests/run.php
 *
 * Les fixtures de tests/fixtures/ sont des réponses telles que la clé les rend.
 * Chaque valeur douteuse rencontrée en production doit y laisser un fichier :
 * c'est ce qui transforme un incident en test de non-régression permanent. */

require_once __DIR__ . '/stub.php';

/* La classe charge le coeur de Jeedom en première ligne ; hors installation, on
 * la recopie sans ce require et on l'inclut. La copie est déposée à côté de
 * l'originale et effacée en sortant, même sur erreur fatale. */
$original = __DIR__ . '/../core/class/solplanetbe.class.php';
$copy = __DIR__ . '/../core/class/.solplanetbe.test.php';
$source = preg_replace('#^\s*require_once .*core\.inc\.php.*$#m', '', file_get_contents($original));
file_put_contents($copy, $source);
register_shutdown_function(function () use ($copy) {
    if (file_exists($copy)) { unlink($copy); }
});
require_once $copy;

$passed = 0;
$failed = 0;

function check($_label, $_actual, $_expected) {
    global $passed, $failed;
    if ($_actual === $_expected) {
        $passed++;
        printf("  ok    %-54s %s\n", $_label, var_export($_actual, true));
    } else {
        $failed++;
        printf("  ECHEC %-54s obtenu %s, attendu %s\n", $_label,
            var_export($_actual, true), var_export($_expected, true));
    }
}

function section($_title) {
    echo "\n" . $_title . "\n" . str_repeat('-', strlen($_title)) . "\n";
}

function fixture($_name) {
    return json_decode(file_get_contents(__DIR__ . '/fixtures/' . $_name . '.json'), true);
}

/* Donne accès aux méthodes privées : on teste la logique, pas la visibilité. */
function invoke($_object, $_method, $_args = array()) {
    $m = new ReflectionMethod('solplanetbe', $_method);
    $m->setAccessible(true);
    return $m->invokeArgs($_object, $_args);
}

function device($_devtype, $_shape = null) {
    $eq = new solplanetbe();
    $eq->setConfiguration('devtype', $_devtype);
    $eq->setConfiguration('ip', '192.168.0.42');
    $eq->setConfiguration('sn', 'MA30006012290197');
    if ($_shape !== null) {
        $eq->setCache('shape', $_shape);
    }
    return $eq;
}

/* ============================================== MISE À L'ÉCHELLE ÉLÉMENTAIRE */
section('Mise à l\'échelle');

/* Le piège du portage : (float) null vaut 0.0 en PHP sans rien dire. Un champ
 * que la clé cesserait d'envoyer deviendrait 0 W silencieusement, et tous les
 * scénarios bâtis sur « production nulle » se déclencheraient. */
check('scaled() sur une clé absente', solplanetbe::scaled(array(), 'pac'), null);
check('scaled() sur une valeur non numérique', solplanetbe::scaled(array('pac' => 'n/a'), 'pac'), null);
check('scaled() sur zéro', solplanetbe::scaled(array('pac' => 0), 'pac', 1, 0), 0.0);
check('2301 dixièmes de volt', solplanetbe::scaled(array('vac' => 2301), 'vac', 0.1, 1), 230.1);
check('345 dixièmes de degré', solplanetbe::scaled(array('tmp' => 345), 'tmp', 0.1, 1), 34.5);

/* ====================================================== ONDULEUR MONOPHASÉ */
section('Onduleur monophasé, deux chaînes photovoltaïques');

$raw = fixture('inverter_single_phase');
$eq = device(solplanetbe::TYPE_INVERTER, array('phases' => 1, 'mppt' => 2));
$v = $eq->values($raw);

check('puissance', $v['puissance'], 2416.0);
check('énergie du jour (87 → 8,7 kWh)', $v['energie_jour'], 8.7);
check('énergie totale (123456 → 12345,6 kWh)', $v['energie_totale'], 12345.6);
check('température', $v['temperature'], 34.5);
check('fréquence (4998 → 49,98 Hz)', $v['frequence'], 49.98);
check('facteur de puissance', $v['facteur_puissance'], 0.99);
check('heures de fonctionnement', $v['heures_fonctionnement'], 4210.0);
check('tension réseau', $v['tension_ac_1'], 230.1);
check('courant réseau (105 → 10,5 A)', $v['courant_ac_1'], 10.5);
check('tension PV1', $v['tension_pv_1'], 321.0);
check('courant PV1 (412 → 4,12 A)', $v['courant_pv_1'], 4.12);
check('puissance PV1 calculée (321,0 V x 4,12 A)', $v['puissance_pv_1'], 1323.0);
check('puissance PV2 calculée', $v['puissance_pv_2'], 1268.0);
check('somme des chaînes', $v['puissance_pv'], 2591.0);
check('état', $v['etat'], 'Normal');
check('code erreur', $v['code_erreur'], 0);
check('libellé d\'erreur', $v['libelle_erreur'], 'Aucune erreur');

/* Le monophasé n'a qu'une tension : le script d'origine écrasait la même
 * variable dans une boucle et ne gardait que la dernière valeur. Ici, une phase
 * absente rend null et n'écrit rien. */
check('pas de seconde phase', isset($v['tension_ac_2']), false);

/* ======================================================= ONDULEUR TRIPHASÉ */
section('Onduleur triphasé, trois chaînes');

$raw = fixture('inverter_three_phase');
$eq = device(solplanetbe::TYPE_INVERTER, array('phases' => 3, 'mppt' => 3));
$v = $eq->values($raw);

check('tension phase 1', $v['tension_ac_1'], 229.8);
check('tension phase 2', $v['tension_ac_2'], 231.2);
check('tension phase 3', $v['tension_ac_3'], 230.5);
check('puissance phase 2', $v['puissance_ac_2'], 2450.0);
check('tension PV3', $v['tension_pv_3'], 399.0);
check('courant PV3 (590 → 5,90 A)', $v['courant_pv_3'], 5.9);
check('puissance totale', $v['puissance'], 7320.0);

/* La forme vient de la réponse, pas de la forme mémorisée : un cache de Jeedom
 * vidé ne doit pas faire disparaître les phases 2 et 3 des relevés, alors que
 * leurs commandes, elles, sont toujours en base. */
$vierge = device(solplanetbe::TYPE_INVERTER);
$w = $vierge->values($raw);
check('sans forme mémorisée : phase 3 relevée', $w['tension_ac_3'], 230.5);
check('sans forme mémorisée : chaîne 3 relevée', $w['courant_pv_3'], 5.9);

/* ============================================= ONDULEUR ÉTEINT, RÉPONSE PARTIELLE */
section('Réponse partielle : onduleur éteint derrière une clé encore alimentée');

$raw = fixture('inverter_offline');
$eq = device(solplanetbe::TYPE_INVERTER, array('phases' => 1, 'mppt' => 2));
$v = $eq->values($raw);

check('code erreur 305', $v['code_erreur'], 305);
check('libellé du 305', $v['libelle_erreur'], 'Onduleur hors ligne');
check('état inconnu rendu lisible', $v['etat'], 'Inconnu (255)');
check('tension absente → null', $v['tension_ac_1'], null);
check('courant PV absent → null', $v['courant_pv_1'], null);
check('puissance PV incalculable → null', $v['puissance_pv_1'], null);
/* L'index d'énergie, lui, est bien là et doit être gardé. */
check('énergie totale conservée', $v['energie_totale'], 45678.9);

/* =========================================================== CODES D'ERREUR */
section('Codes d\'erreur');

check('code connu', solplanetbe::errorLabel(35), 'Perte du réseau');
check('code d\'onduleur hybride', solplanetbe::errorLabel(2004), 'Batterie trop déchargée');
/* Les plages « réservées » du constructeur n'ont pas de libellé : on rend le
 * code plutôt que d'inventer un message rassurant. */
check('code inconnu', solplanetbe::errorLabel(77), 'Erreur 77');

/* ================================================================ COMPTEUR */
section('Compteur bidirectionnel');

$raw = fixture('meter');
$eq = device(solplanetbe::TYPE_METER);
$v = $eq->values($raw);

check('puissance réseau signée', $v['puissance_reseau'], -1840.0);
check('rien de soutiré', $v['puissance_soutiree'], 0);
check('injection rendue positive', $v['puissance_injectee'], 1840.0);
check('soutirage du jour (245 → 2,45 kWh)', $v['import_jour'], 2.45);
check('injection du jour', $v['export_jour'], 11.32);
check('soutirage total (45210 → 4521,0 kWh)', $v['import_total'], 4521.0);
check('injection totale', $v['export_total'], 8834.0);

/* ================================================================ BATTERIE */
section('Batterie');

$raw = fixture('battery');
$eq = device(solplanetbe::TYPE_BATTERY);
$v = $eq->values($raw);

check('état de charge', $v['soc'], 74.0);
check('état de santé', $v['soh'], 99.0);
check('tension (40850 → 408,50 V)', $v['tension'], 408.5);
check('courant (285 → 28,5 A)', $v['courant'], 28.5);
check('température (251 → 25,1 °C)', $v['temperature'], 25.1);
check('puissance signée', $v['puissance'], 1164.0);
check('puissance de charge', $v['puissance_charge'], 1164.0);
check('puissance de décharge', $v['puissance_decharge'], 0);
check('production photovoltaïque', $v['puissance_pv'], 4210.0);
check('production totale', $v['pv_total'], 6543.2);
check('état', $v['etat'], 'En charge');
check('aucune erreur', $v['libelle_erreur'], 'Aucune erreur');

/* eb1 est un champ de bits, pas un code : plusieurs défauts s'y cumulent, et
 * 65535 ne veut pas dire « tout va mal » mais « le BMS n'a rien dit ». */
check('BMS muet', solplanetbe::batteryErrorLabel(array('eb1' => 65535)), 'Information indisponible');
check('un seul bit levé', solplanetbe::batteryErrorLabel(array('eb1' => 32)), 'Surintensité en décharge');
check('deux bits levés', solplanetbe::batteryErrorLabel(array('eb1' => 6)),
    'Surtension d\'une cellule ou d\'un module, Sous-tension d\'une cellule ou d\'un module');
check('champ absent', solplanetbe::batteryErrorLabel(array()), null);

/* ============================================================== HORS LIGNE */
section('Hors ligne : ce qui tombe à zéro et ce qui ne bouge pas');

$eq = device(solplanetbe::TYPE_INVERTER, array('phases' => 1, 'mppt' => 2));
$eq->commands = array('en_ligne', 'puissance', 'puissance_pv', 'puissance_pv_1',
    'courant_pv_1', 'courant_ac_1', 'tension_ac_1', 'energie_totale', 'energie_jour');
$eq->setConfiguration('zero_when_offline', 1);
invoke($eq, 'applyOffline');

check('marqué hors ligne', $eq->published['en_ligne'], 0);
check('puissance remise à zéro', $eq->published['puissance'], 0);
check('courant PV remis à zéro', $eq->published['courant_pv_1'], 0);
/* Le point qui compte : écrire 0 dans un index d'énergie creuserait un trou
 * dans l'historique et déclencherait tous les scénarios bâtis dessus. */
check('énergie totale intacte', isset($eq->published['energie_totale']), false);
check('énergie du jour intacte', isset($eq->published['energie_jour']), false);
/* La tension réseau ne tombe pas à zéro : le réseau, lui, est toujours là. */
check('tension réseau intacte', isset($eq->published['tension_ac_1']), false);

$eq = device(solplanetbe::TYPE_INVERTER, array('phases' => 1, 'mppt' => 1));
$eq->commands = array('en_ligne', 'puissance');
$eq->setConfiguration('zero_when_offline', 0);
invoke($eq, 'applyOffline');
check('option décochée : puissance figée', isset($eq->published['puissance']), false);
check('option décochée : marqué hors ligne quand même', $eq->published['en_ligne'], 0);

/* ======================================================= LECTURE DU JSON */
section('Lecture de la réponse');

/* La clé annonce du text/html, glisse des octets nuls en fin de trame et fait
 * parfois suivre le JSON de remplissage. */
check('octets nuls et remplissage', solplanetbe::parse("\0{\"pac\":42}\0\0"), array('pac' => 42));
check('espaces autour', solplanetbe::parse("  {\"pac\":42}  \n"), array('pac' => 42));

$caught = '';
try { solplanetbe::parse('<html>404</html>'); } catch (Throwable $e) { $caught = 'levée'; }
check('page HTML rejetée', $caught, 'levée');

$caught = '';
try { solplanetbe::parse('{"pac":}'); } catch (Throwable $e) { $caught = 'levée'; }
check('JSON invalide rejeté', $caught, 'levée');

/* ============================================================ CONFIGURATION */
section('Configuration');

$eq = device(solplanetbe::TYPE_INVERTER);
check('onduleur configuré', $eq->isConfigured(), true);
$eq->setConfiguration('sn', '');
check('onduleur sans numéro de série', $eq->isConfigured(), false);
$eq->setConfiguration('devtype', solplanetbe::TYPE_METER);
check('compteur sans numéro de série', $eq->isConfigured(), true);
$eq->setConfiguration('ip', '');
check('sans adresse', $eq->isConfigured(), false);

$eq = device(solplanetbe::TYPE_BATTERY);
check('point d\'accès batterie', $eq->endpoint(), 'getdevdata.cgi?device=4&sn=MA30006012290197');
$eq = device(solplanetbe::TYPE_METER);
check('point d\'accès compteur', $eq->endpoint(), 'getdevdata.cgi?device=3');
$eq = device(solplanetbe::TYPE_INVERTER);
check('point d\'accès onduleur', $eq->endpoint(), 'getdevdata.cgi?device=2&sn=MA30006012290197');

/* Tant que la clé n'a pas répondu, on suppose le cas le plus simple : une phase,
 * une chaîne. Les commandes des autres n'existent pas encore, et c'est voulu. */
$eq = device(solplanetbe::TYPE_INVERTER);
check('forme par défaut', $eq->shape(), array('phases' => 1, 'mppt' => 1));

/* =========================================================== NOMS DE COMMANDES */
section('Noms de commandes');

/* cmd::setName() passe par cleanComponanteName(), qui retire l'apostrophe sans
 * rien dire : « Facteur d'utilisation » arriverait en base sous la forme
 * « Facteur dutilisation », ce qui se lit comme une faute de frappe. */
$apostrophes = array();
$doublons = array();
foreach (array(solplanetbe::TYPE_INVERTER, solplanetbe::TYPE_METER, solplanetbe::TYPE_BATTERY) as $type) {
    $eq = device($type, array('phases' => 3, 'mppt' => 4));
    $vus = array();
    foreach ($eq->catalog() as $logicalId => $definition) {
        if (strpos($definition[0], "'") !== false) {
            $apostrophes[] = $type . ' / ' . $definition[0];
        }
        /* La table cmd porte une contrainte d'unicité sur (eqLogic_id, name) :
         * deux commandes homonymes feraient échouer la création silencieusement. */
        if (isset($vus[$definition[0]])) {
            $doublons[] = $type . ' / ' . $definition[0];
        }
        $vus[$definition[0]] = true;
    }
}
check('aucune apostrophe dans un nom de commande', $apostrophes, array());
check('aucun nom de commande en double', $doublons, array());

/* ================================================================== BILAN */
echo "\n" . str_repeat('=', 70) . "\n";
printf("%d réussite(s), %d échec(s)\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
