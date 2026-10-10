# Plugin Solplanet

Relève en réseau local les onduleurs photovoltaïques **Solplanet** équipés d'une
clé de communication **AiSWEI** (Wi-Fi ou Ethernet), sans passer par le cloud du
constructeur et sans compte à créer.

Le plugin découvre ce qui se trouve derrière l'adresse IP de la clé — onduleur,
compteur bidirectionnel, batterie — et crée un équipement Jeedom pour chacun.
Un cron les relève ensuite à l'intervalle que vous choisissez.

## Prérequis

- Une installation photovoltaïque Solplanet, VoltX ou AiSWEI dont l'onduleur est
  équipé de sa clé de communication.
- Cette clé connectée au même réseau que Jeedom. Aucune ouverture de port,
  aucune configuration côté onduleur : le service écoute sur le port 8484 et
  répond sans authentification.

Réservez l'adresse IP de la clé dans votre routeur. Si elle change, le plugin ne
trouve plus rien, et rien ne vous le dira avant de regarder les courbes.

## Installation

1. Installez le plugin, puis activez-le.
2. Ouvrez **Plugins → Énergie → Solplanet**.
3. Cliquez sur **Rechercher les appareils** et saisissez l'adresse IP de la clé.

Le plugin interroge la clé et affiche ce qu'elle déclare. Validez, et les
équipements sont créés avec leurs commandes ; le premier relevé part dans la
foulée.

L'ajout à la main reste possible : type d'appareil, adresse IP, numéro de série
de l'onduleur. Le numéro de série est visible dans l'application Solplanet, ou
dans la réponse de la découverte.

![Configuration d'un onduleur](../images/configuration-onduleur.png)

### Trouver l'adresse de la clé

C'est celle que votre box attribue au module branché sur l'onduleur, pas celle
de la box. Deux moyens de la reconnaître :

- dans la liste des appareils connectés de votre routeur, un équipement dont le
  nom commence souvent par `AiSWEI` ou `Solplanet` ;
- en ligne de commande, la réponse à un appel direct :

```bash
curl -s --max-time 5 "http://192.168.0.42:8484/getdev.cgi?device=2"
```

Une réponse JSON contenant `isn` confirme l'adresse et vous donne le numéro de
série de l'onduleur.

## Ce que vous voyez sur le dashboard

Chaque équipement n'affiche **qu'une tuile**. C'est un choix : dix-neuf widgets
empilés ne se lisent pas, alors qu'un onduleur pose une question unique — « ça
produit combien ? » — et une tuile y répond en entier.

![La tuile de l'onduleur](../images/tuile-onduleur.png)

La tuile de l'onduleur montre la puissance instantanée en grand, une jauge par
rapport à la puissance nominale (2,50 kW sur un onduleur de 3 kW, c'est 83 % ;
le chiffre seul ne le dirait pas), l'énergie du jour et l'énergie totale, la
température, la tension réseau et l'état. Dès qu'il y a plus d'une chaîne
photovoltaïque, le détail de chacune s'ajoute : c'est la comparaison qui montre
qu'une chaîne est à l'ombre ou en défaut, pas la valeur isolée.

Le compteur et la batterie ont leur propre tuile : sens du courant et index
d'un côté, état de charge et puissance de l'autre.

**Toutes les autres commandes existent, mais sont masquées.** Elles sont
historisées et utilisables en scénario, en courbe ou en vue. Pour en afficher
une sur le dashboard, cochez « Afficher » dans l'onglet *Commandes* de
l'équipement.

Trois réglages sont disponibles sur la tuile elle-même, par le bouton
« Configuration avancée » du widget : `gauge`, `facts` et `strings`, à mettre à
`0` pour masquer respectivement la jauge, les chiffres secondaires et le détail
par chaîne.

La nuit, la tuile s'estompe et affiche « En veille — la clé ne répond pas ».
Elle garde la dernière valeur connue plutôt que de se vider : on voit d'un coup
d'œil que la donnée n'est plus fraîche, sans perdre l'information.

## Les équipements

Un équipement Jeedom représente **un appareil physique**. Les trois familles
partagent l'adresse de la clé mais n'ont ni les mêmes commandes, ni le même
rythme utile.

### Onduleur

| Commande | Unité | Ce que c'est |
|---|---|---|
| Puissance | W | Puissance injectée côté alternatif, l'instantané |
| Énergie du jour | kWh | Remise à zéro chaque nuit par l'onduleur |
| Énergie totale | kWh | Index depuis la mise en service |
| Puissance photovoltaïque | W | Somme des chaînes, côté continu (si plusieurs) |
| Tension / Courant réseau | V / A | Une commande par phase |
| Puissance phase 1…3 | W | Sur les onduleurs triphasés seulement |
| Tension / Courant / Puissance PV | V / A / W | Une série par chaîne (MPPT) |
| Température | °C | Température interne de l'onduleur |
| Fréquence réseau | Hz | |
| Facteur de puissance | | |
| Puissance apparente / réactive | VA / VAr | |
| Heures de fonctionnement | h | |
| État | | Normal, En attente, Défaut, Contrôle en cours |
| Code erreur / Erreur | | Le code et son libellé en clair |
| Code avertissement | | |
| En ligne | | 1 quand la clé a répondu au dernier passage |
| Rafraîchir | | Commande action, à utiliser en scénario |

Le nombre de phases et de chaînes n'est pas deviné : il est lu dans la première
réponse de la clé, et les commandes correspondantes sont créées à ce
moment-là. Un onduleur monophasé n'a donc pas de « phase 2 ».

La puissance par chaîne n'est pas donnée par la clé : elle est calculée
(tension × courant). C'est la grandeur qui manque le plus souvent dans les
relevés faits à la main, et celle qui montre qu'une chaîne est à l'ombre ou en
défaut.

### Compteur

| Commande | Unité | Ce que c'est |
|---|---|---|
| Puissance réseau | W | Signée : positive au soutirage, négative à l'injection |
| Puissance soutirée / injectée | W | Les deux moitiés, toujours positives |
| Soutirage / Injection du jour | kWh | |
| Soutirage / Injection total | kWh | Index |
| En ligne | | |

Les trois puissances disent la même chose sous trois formes : la signée se prête
aux scénarios (`< 0` veut dire « on injecte »), les deux autres aux widgets
énergie de Jeedom, qui attendent des valeurs positives distinctes.

### Batterie

État de charge et de santé, puissance signée et ses deux moitiés (charge,
décharge), tension, courant, température, énergie chargée et déchargée,
production photovoltaïque du jour et totale, grandeurs du réseau secouru (EPS),
état et libellé d'erreur.

## Le rythme des relevés

Chaque équipement a son propre intervalle, de la minute à la demi-heure,
**cinq minutes par défaut**. Le cron du plugin passe toutes les minutes et ne
relève que les appareils dont l'intervalle est écoulé.

L'appel se fait sur votre réseau local : relever toutes les minutes ne coûte
presque rien en temps, mais remplit l'historique douze fois plus vite qu'à cinq
minutes. Pour suivre une production, cinq minutes suffisent largement.

## La nuit, et les pannes

La clé de communication est alimentée par l'onduleur. Sur une installation sans
batterie, **elle s'éteint avec le soleil** : la connexion est refusée toute la
nuit. Ce n'est pas une panne, et le plugin ne la traite pas comme telle.

- La commande **En ligne** passe à 0.
- Si l'option **« mettre les puissances à zéro »** est cochée — elle l'est par
  défaut — les puissances et les courants retombent à 0, au lieu de rester figés
  sur la dernière valeur du soir. La courbe de production redescend comme elle le
  doit.
- **Les index d'énergie ne sont jamais touchés.** Écrire 0 dans « Énergie
  totale » creuserait un trou dans l'historique et déclencherait tous les
  scénarios bâtis dessus.
- Le journal reste calme : après trois échecs consécutifs, le plugin espace ses
  tentatives (de cinq minutes à une heure) et n'écrit qu'une ligne en `info`.

Décochez l'option pour un **onduleur hybride**, qui reste allumé la nuit : une
absence de réponse y serait une vraie panne, et figer la dernière valeur est
alors plus parlant qu'un zéro trompeur.

Le délai de communication de l'équipement (onglet *Configuration avancée*) est
laissé à zéro, donc désactivé : sans cela Jeedom lèverait une alerte chaque soir
pour un fonctionnement normal. Activez-le si votre installation est censée
répondre en permanence.

## Diagnostic

L'onglet **Diagnostic** de chaque équipement montre la date du dernier relevé
réussi, la forme détectée (phases, chaînes) et **le JSON brut tel que la clé l'a
renvoyé**, avant toute mise à l'échelle.

C'est la pièce à joindre quand une valeur paraît fausse : elle dit tout de suite
si le problème vient de l'onduleur ou du plugin. Les valeurs y sont des entiers à
échelle fixe — `2301` pour 230,1 V, `345` pour 34,5 °C, `123456` pour
12 345,6 kWh.

Le journal du plugin est accessible par **Analyse → Logs → solplanetbe**.

## Questions fréquentes

**Rien ne remonte, la découverte ne trouve rien.**
Vérifiez l'adresse depuis la machine Jeedom, pas depuis votre poste :
`curl -s --max-time 5 "http://<ip>:8484/getdev.cgi?device=2"`. Si Jeedom est dans
un conteneur ou sur un autre VLAN, c'est là qu'il faut chercher.

**Les valeurs sont dix fois trop grandes.**
Ce n'est pas le cas dans ce plugin — c'est précisément ce qu'il corrige. Si vous
voyez 2301 V, c'est qu'une ancienne variable globale ou un virtuel traîne encore
dans votre configuration.

**Puis-je piloter la batterie ?**
Pas dans cette version. Le plugin ne fait que lire. L'écriture (mode de travail,
seuils de charge) existe dans le protocole et pourra venir ensuite.

**Plusieurs onduleurs ?**
Oui : la découverte les crée tous, chacun avec son numéro de série.

## Crédits

Les facteurs d'échelle et les tables de codes d'erreur ont été repris de
l'intégration Home Assistant `zbigniewmotyka/home-assistant-solplanet`, publiée
sous licence MIT.

Ce plugin n'est affilié ni à Solplanet, ni à AiSWEI, et n'est ni parrainé ni
approuvé par eux.
