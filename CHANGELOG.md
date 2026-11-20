# Nouveautés

Le numéro de version est affiché en bas de chaque page et dans **Réglages → Version installée**,
avec le commit Git déployé (à comparer avec le dernier commit sur GitHub).

## v1.4.0 — 20/11/2026
- Application **installable** (PWA) : icône sur l'écran d'accueil du téléphone ou du bureau,
  ouverture en plein écran sans barre de navigateur, raccourcis « Nouvelle campagne », « Campagnes », « Contacts ».
- Bouton « Installer l'application » dans l'en-tête (Chrome, Edge, Android).
- Page « Hors connexion » quand le réseau est indisponible ; affichage amélioré sur téléphone.

## v1.3.0 — 14/11/2026
- Rôles utilisateurs : **Administrateur** (accès complet) et **Rédacteur** (contacts, listes, imports, campagnes).
  Réglages, Microsoft 365 et Utilisateurs sont réservés aux administrateurs. Les comptes existants restent administrateurs.
- Page **Mon compte** (clic sur son nom en haut à droite) : changer son nom et son mot de passe.
- Garde-fous : impossible de supprimer le dernier administrateur ou de se retirer ses propres droits.

## v1.2.0 — 14/11/2026
- Affichage de la version et du commit déployé (pied de page, page de connexion, Réglages).
- Vérification de la présence de l'extension PHP curl (nécessaire à la synchro Microsoft 365).

## v1.1.0 — 14/11/2026
- Synchronisation des contacts Outlook Microsoft 365 via Microsoft Graph (menu **Microsoft 365**).
- Catégories Outlook converties en listes « Outlook · … ».
- Correctif : certaines pages dont le nom contient un chiffre ne s'ouvraient pas.

## v1.0.0 — 13/11/2026
- Première version : contacts, listes, import CSV/XLSX, campagnes, envoi cadencé, désinscription, suivi.
