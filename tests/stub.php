<?php
/* Remplaçants minimaux du coeur de Jeedom, pour rejouer la classe du plugin
 * hors d'une installation. Ils ne simulent que ce dont le décodage a besoin :
 * traduction, configuration, journal, cache et les deux classes de base.
 *
 * Le but n'est pas de tester Jeedom, mais de pouvoir rejouer une réponse de la
 * clé AiSWEI et vérifier que le plugin en tire les bonnes grandeurs. La mise à
 * l'échelle est précisément ce qu'un essai sur l'interface ne montre pas :
 * 2301 V au lieu de 230,1 V reste un nombre, et il faut le comparer à
 * l'application du constructeur pour s'apercevoir de l'erreur. */

date_default_timezone_set('Europe/Brussels');

function __($_text, $_file = null) { return $_text; }

class config {
    public static $values = array();
    public static function byKey($_key, $_plugin = 'core', $_default = '') {
        $k = $_plugin . '::' . $_key;
        return isset(self::$values[$k]) ? self::$values[$k] : $_default;
    }
}

class log {
    public static $lines = array();
    public static function add($_plugin, $_level, $_message, $_logicalId = '') {
        self::$lines[] = $_level . ' : ' . $_message;
    }
}

class cmd {
    public $logicalId = '';
    public $value = '';
    public $id = 0;
    public function getLogicalId() { return $this->logicalId; }
    public function getType() { return 'info'; }
    public function getEqLogic() { return null; }
    public function execCmd($_options = array()) { return $this->value; }
    public static function byId($_id) { return null; }
}

class eqLogic {
    public $published = array();
    public $configuration = array();
    public $commands = array();
    public function getId() { return 1; }
    public function getHumanName() { return '[Test][Onduleur]'; }
    public function getConfiguration($_key, $_default = '') {
        return array_key_exists($_key, $this->configuration) ? $this->configuration[$_key] : $_default;
    }
    public function setConfiguration($_key, $_value) {
        $this->configuration[$_key] = $_value;
        return $this;
    }
    /* Une commande n'existe dans le stub que si le test l'a déclarée : c'est ce
     * qui permet de vérifier que publishCmd() ignore sans broncher une commande
     * absente, au lieu de partir en erreur fatale. */
    public function getCmd($_type, $_logicalId) {
        if (!in_array($_logicalId, $this->commands, true)) {
            return null;
        }
        $cmd = new cmd();
        $cmd->logicalId = $_logicalId;
        return $cmd;
    }
    public function getName() { return 'Onduleur'; }
    public function checkAndUpdateCmd($_cmd, $_value, $_when = null) {
        $id = is_object($_cmd) ? $_cmd->getLogicalId() : $_cmd;
        $this->published[$id] = $_value;
    }

    /* getCache() et setCache() sont publiques dans eqLogic : les redéclarer en
     * privé dans le plugin serait une erreur fatale au chargement de la classe,
     * donc un Jeedom entier en HTTP 500. Le stub les fournit à l'identique. */
    public $store = array();
    public function getCache($_key = '', $_default = '') {
        return isset($this->store[$_key]) ? $this->store[$_key] : $_default;
    }
    public function setCache($_key, $_value = null) {
        $this->store[$_key] = $_value;
    }

    public function refreshWidget() {}
    public function getTimeout() { return 0; }
    public function setTimeout($_v) {}
    public function setIsEnable($_v) {}
    public function setIsVisible($_v) {}
    public function setDisplay($_k, $_v) {}
    public function setLogicalId($_v) {}
    public function setName($_v) {}
    public function setEqType_name($_v) {}
    public function save($_direct = false) {}
    public static function byType($_type, $_onlyEnable = false) { return array(); }
    public static function byLogicalId($_logicalId, $_eqType, $_multiple = false) { return null; }
}
