# Changelog

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
