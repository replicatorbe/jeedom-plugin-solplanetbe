# Changelog

## 0.2

- Une tuile sur le dashboard, à la place des dix-neuf widgets empilés :
  puissance instantanée, jauge par rapport à la puissance nominale, énergie du
  jour et totale, température, tension réseau, état — et le détail par chaîne
  photovoltaïque dès qu'il y en a plusieurs. Trois présentations, une par
  famille d'appareils.
- Les commandes de détail sont désormais masquées à la création. Celles des
  appareils déjà créés le deviennent une seule fois, à la mise à jour :
  réaffichez ce que vous voulez dans l'onglet Commandes de l'équipement.
- La puissance nominale de l'onduleur est lue toute seule sur la clé et sert
  d'échelle à la jauge. Elle reste modifiable à la main.
- Trois options de widget : jauge, chiffres secondaires, détail par chaîne.

## 0.1

Première version.

- Découverte des appareils derrière l'adresse IP de la clé AiSWEI : onduleurs,
  compteur, batterie.
- Un équipement Jeedom par appareil physique, avec ses commandes info
  historisées et mises à l'échelle.
- Onduleur : puissance, énergie du jour et totale, tension et courant par phase
  et par chaîne photovoltaïque, puissance calculée par chaîne, température,
  fréquence, facteur de puissance, heures de fonctionnement, état et libellé
  d'erreur en clair.
- Compteur : puissance réseau signée et ses deux moitiés, index de soutirage et
  d'injection.
- Batterie : état de charge et de santé, puissance, tension, courant,
  température, énergies, réseau secouru.
- Relevé par cron, intervalle réglable par équipement de une à trente minutes.
- Gestion de la nuit : puissances remises à zéro au choix, index d'énergie
  jamais touchés, tentatives espacées et journal silencieux.
- Onglet Diagnostic montrant la réponse brute de la clé.
