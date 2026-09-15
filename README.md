# Plugin Jeedom — Solplanet

Relève en réseau local les onduleurs photovoltaïques **Solplanet** équipés d'une
clé de communication **AiSWEI**, sans passer par le cloud du constructeur.

Le plugin découvre ce qui répond derrière l'adresse IP de la clé — onduleur,
compteur bidirectionnel, batterie — et crée un équipement Jeedom pour chacun.
Un cron les relève ensuite à l'intervalle choisi, de la minute à la demi-heure.

## Ce qu'il remonte

- **Onduleur** : puissance, énergie du jour et totale, tension et courant par
  phase et par chaîne photovoltaïque, puissance calculée par chaîne,
  température, fréquence, facteur de puissance, heures de fonctionnement, état
  et libellé d'erreur en clair.
- **Compteur** : puissance réseau signée et ses deux moitiés, index de
  soutirage et d'injection.
- **Batterie** : état de charge et de santé, puissance, tension, courant,
  température, énergies chargées et déchargées, réseau secouru.

Toutes les valeurs sont mises à l'échelle et historisables : elles s'utilisent
directement dans les widgets énergie, les scénarios et les courbes de Jeedom.

## Installation

Plugins → Gestion des plugins → Ajouter → Github, puis
`replicatorbe / jeedom-plugin-solplanetbe`, branche `master` pour le canal
stable ou `beta` pour la version de développement.

Ensuite : Plugins → Énergie → Solplanet → **Rechercher les appareils**, et
saisissez l'adresse IP de la clé de communication.

La documentation complète est dans [`docs/fr_FR/index.md`](docs/fr_FR/index.md).

## Développement

```bash
php tests/run.php            # rejeu hors ligne sur les fixtures
php tests/check-classes.php  # contrôles par réflexion contre le coeur installé
```

Les fixtures de `tests/fixtures/` sont des réponses de la clé. Toute valeur
douteuse rencontrée en production mérite d'y laisser un fichier.

## Licence

AGPL v3. Ce plugin n'est affilié ni à Solplanet, ni à AiSWEI.

Les facteurs d'échelle et les tables de codes d'erreur proviennent de
l'intégration Home Assistant
[`zbigniewmotyka/home-assistant-solplanet`](https://github.com/zbigniewmotyka/home-assistant-solplanet)
(licence MIT).
