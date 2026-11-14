# Mailing Batterie & Compagnie

Outil d'envoi de mailings pour l'association **Batterie & Compagnie**. Il est autohébergé chez o2switch et envoie par SMTP depuis `info@m.batterieetcompagnie.fr`.

- **Contacts et listes** : import CSV ou Excel (export Yapla), recherche, actions groupées, export CSV.
- **Campagnes** : éditeur visuel avec images, variables `{{prenom}}`, `{{nom}}` et `{{email}}`, aperçu, mail de test, envoi immédiat ou programmé, pause, reprise, annulation et duplication.
- **Envoi cadencé** : une file d'attente est traitée par un cron, avec un plafond par heure glissante pour rester sous la limite de l'hébergeur.
- **Désinscription** : lien en pied de mail, plus les en-têtes `List-Unsubscribe` et `List-Unsubscribe-Post` (désinscription « en un clic » exigée par Gmail et Yahoo). Un contact désinscrit n'est jamais réabonné par un import.
- **Suivi** : envoyés, échecs (une adresse inexistante passe automatiquement « en erreur ») et ouvertures (indicatives).
- **Contacts Microsoft 365** : synchronisation directe des contacts Outlook des boîtes de l'association (Microsoft Graph), avec les catégories Outlook converties en listes.
- **Plusieurs utilisateurs**, journal d'activité.

Stack : PHP 8.1+ (extensions pdo_sqlite, openssl, mbstring, zip, curl) et SQLite. Aucune base MySQL à créer, aucune dépendance à installer : PHPMailer et l'éditeur Quill sont inclus dans le dépôt.

```
cron.php            traitement de la file (lancé par le cron, en ligne de commande)
config.sample.php   modèle de configuration → à copier en config.php
lib/                code applicatif + PHPMailer
views/              gabarits des pages
data/               base SQLite (créée automatiquement, jamais versionnée)
public/             ← RACINE WEB du sous-domaine
```

---

## 1. Préparer l'adresse d'envoi `info@m.batterieetcompagnie.fr`

Envoyer depuis un **sous-domaine dédié** (`m.`) isole la réputation des mailings de celle de `batterieetcompagnie.fr`, qui reste chez Microsoft 365 pour le courrier courant. Rien ne change pour les boîtes Microsoft.

1. Dans cPanel, menu **Domaines**, ajouter `m.batterieetcompagnie.fr`.
2. Dans **Comptes de messagerie**, créer `info@m.batterieetcompagnie.fr`.
3. Dans **Email Deliverability** (Délivrabilité des e-mails), cPanel affiche les enregistrements **SPF** et **DKIM** attendus pour `m.batterieetcompagnie.fr`.
   - Si la zone DNS de `batterieetcompagnie.fr` est gérée **chez o2switch**, cliquer sur « Réparer ».
   - Si elle est gérée **ailleurs** (registrar, Microsoft…), ajouter à la main dans cette zone, **sans toucher aux enregistrements Microsoft du domaine principal** :

   | Type | Nom | Valeur |
   |---|---|---|
   | MX | `m` | serveur o2switch indiqué par cPanel (pour recevoir réponses et retours) |
   | TXT | `m` | SPF fourni par cPanel (`v=spf1 … ~all`) |
   | TXT | `default._domainkey.m` | clé DKIM fournie par cPanel |
   | TXT | `_dmarc.m` | `v=DMARC1; p=none; rua=mailto:info@batterieetcompagnie.fr` |

4. Vérifier avec [mail-tester.com](https://www.mail-tester.com) : envoyer un test depuis l'outil vers l'adresse qu'il fournit. Viser 9/10 ou plus.

> Quand tout est au vert pendant quelques semaines, DMARC peut passer en `p=quarantine`.

## 2. Déployer chez o2switch

1. cPanel, menu **Git™ Version Control**, puis **Créer** :
   - URL de clonage : `https://github.com/pschwaller69/batterieetcompagnie.git`
   - Chemin : `/home/VOTRE_COMPTE/mailing`
2. cPanel, menu **Domaines** : créer `mailing.batterieetcompagnie.fr` avec comme racine `/home/VOTRE_COMPTE/mailing/public`. Activer le **SSL** (AutoSSL / Let's Encrypt).
3. Dans le **Gestionnaire de fichiers** (ou en SSH), copier `config.sample.php` en `config.php` dans `/home/VOTRE_COMPTE/mailing/`, puis renseigner `app_secret` :
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
4. Vérifier que **PHP ≥ 8.1** est sélectionné, avec les extensions `pdo_sqlite`, `openssl`, `mbstring`, `zip` et `curl` (cPanel, menu **Sélecteur de version PHP**).
5. Ouvrir `https://mailing.batterieetcompagnie.fr` et créer le compte administrateur.
6. Dans **Réglages**, renseigner :
   - le serveur SMTP : celui indiqué par cPanel dans Comptes de messagerie → *Connect Devices* ;
   - le port 465 en SSL ;
   - l'identifiant `info@m.batterieetcompagnie.fr` et son mot de passe ;
   - l'adresse postale de l'association, le logo et l'URL de base `https://mailing.batterieetcompagnie.fr`.

   Cliquer ensuite sur **Envoyer un mail de test**.

### Tâche cron (indispensable)

cPanel, menu **Tâches Cron**, toutes les 5 minutes (`*/5 * * * *`) :

```
/usr/local/bin/php /home/VOTRE_COMPTE/mailing/cron.php >/dev/null 2>&1
```

Le tableau de bord affiche l'heure du dernier passage.

*Alternative* : renseigner `cron_url_key` dans `config.php` et faire appeler `https://mailing.batterieetcompagnie.fr/cron.php?key=…` par un service externe.

### Mettre à jour

cPanel → Git Version Control → **Gérer** → *Pull or Deploy* → **Update from Remote**. `config.php` et `data/` ne sont jamais écrasés.

## 3. Cadence d'envoi et limites o2switch

L'hébergeur plafonne le nombre de mails sortants par heure. **Demandez au support o2switch la limite exacte de votre offre** et réglez le « Max / heure » en dessous (100 par défaut).

- Exemple : 600 adhérents à 100 mails/h, l'envoi s'étale sur environ 6 h.
- Avec un cron toutes les 5 minutes, il y a 12 passages par heure : « Max / passage » × 12 doit être au moins égal au plafond horaire (25 par défaut).
- Si le serveur répond « quota dépassé », le lot s'arrête et reprend tout seul au passage suivant, sans pénaliser les destinataires.
- Les mails de test comptent dans le plafond.

## 4. Importer les adhérents depuis Yapla

1. Dans Yapla, exporter la liste des membres (Excel ou CSV).
2. Dans l'outil, ouvrir **Importer** et choisir le fichier. Les colonnes e-mail, prénom, nom et téléphone sont reconnues automatiquement ; corriger si besoin.
3. Choisir ou créer la liste de destination, par exemple « Adhérents 2026-2027 ».

Un contact présent dans plusieurs listes ne reçoit qu'**un seul** exemplaire d'une campagne.

## 5. Synchronisation des contacts Microsoft 365

L'outil peut lire directement les contacts Outlook des boîtes de l'association (`info@batterieetcompagnie.fr`, et `patrick@…` si besoin) via Microsoft Graph.

Il se connecte en mode **application** : aucun compte utilisateur ni mot de passe n'est stocké. L'outil s'authentifie avec un secret propre à une application déclarée dans le tenant, et l'administrateur choisit les boîtes qu'elle peut lire.

Ce que fait la synchro :
- Elle lit les dossiers de contacts : « Contacts » et ses sous-dossiers, tous ou seulement ceux choisis.
- Elle crée ou met à jour les contacts dans l'outil et les place dans la liste « Microsoft 365 ».
- Chaque catégorie Outlook devient une liste « Outlook · *catégorie* » (optionnel). Exemple : catégorisez vos contacts « Bénévoles » ou « CA » dans Outlook et ciblez ces listes dans vos campagnes.
- Un contact supprimé d'Outlook est retiré des listes synchronisées. Il n'est jamais supprimé de l'outil, et l'historique est conservé.
- Le statut **désinscrit / en erreur** n'est jamais modifié par la synchro.
- Elle tourne automatiquement toutes les 24 h via le cron (réglable), ou à la demande.

### 5.1 Déclarer l'application dans Entra (5 minutes)

1. Sur [entra.microsoft.com](https://entra.microsoft.com), aller dans **Applications**, puis **Inscriptions d'applications**, puis **Nouvelle inscription**.
   - Nom : `Mailing Batterie & Compagnie`
   - Types de comptes : *Comptes dans cet annuaire d'organisation uniquement*
   - URI de redirection : laisser vide
2. Sur la page de l'application, noter l'**ID d'application (client)** et l'**ID de l'annuaire (locataire)**.
3. Dans **Certificats et secrets**, créer un **Nouveau secret client** (24 mois maximum). Copier tout de suite la **Valeur**, pas l'« ID du secret » : elle ne sera plus affichée.
   Notez la date d'expiration. À échéance, la synchro affichera une erreur `AADSTS7000222` : il suffira de créer un nouveau secret et de le coller dans l'outil.

### 5.2 Donner accès aux contacts — choisir UNE des deux options

**Option A, recommandée : accès limité aux seules boîtes de l'association** (RBAC Exchange Online pour les applications).

Ne PAS ajouter de permission « Contacts.Read » dans Entra : une permission accordée côté Entra s'appliquerait à toutes les boîtes et passerait outre la restriction.

Dans Entra, ouvrir **Applications d'entreprise** → *Mailing Batterie & Compagnie* et noter son **ID d'objet**. C'est celui de l'application d'entreprise, pas celui de l'inscription d'application. Puis, en PowerShell :

```powershell
Install-Module ExchangeOnlineManagement   # une seule fois
Connect-ExchangeOnline -UserPrincipalName patrick@batterieetcompagnie.fr

$AppId    = "<ID d'application (client)>"
$ObjectId = "<ID d'objet de l'application d'entreprise>"

New-ServicePrincipal -AppId $AppId -ObjectId $ObjectId -DisplayName "Mailing Batterie & Compagnie"

# Boîtes autorisées (ajouter "-or (PrimarySmtpAddress -eq '...')" pour en autoriser d'autres)
New-ManagementScope -Name "Mailing B&C - boites" `
  -RecipientRestrictionFilter "PrimarySmtpAddress -eq 'info@batterieetcompagnie.fr'"

New-ManagementRoleAssignment -App $AppId -Role "Application Contacts.Read" `
  -CustomResourceScope "Mailing B&C - boites"

# Vérification
Test-ServicePrincipalAuthorization -Identity $AppId -Resource info@batterieetcompagnie.fr
```

La prise en compte peut demander quelques dizaines de minutes. En attendant, le test dans l'outil renvoie « accès refusé ».

**Option B, plus simple mais plus large : accès à toutes les boîtes du tenant.** À réserver à un tenant qui ne contient que l'association.

Dans l'inscription d'application, aller dans **Autorisations d'API** → **Ajouter** → **Microsoft Graph** → **Autorisations d'application**. Cocher **Contacts.Read**, puis cliquer sur **Accorder un consentement d'administrateur**.

### 5.3 Brancher l'outil

Dans l'outil, menu **Microsoft 365** :
1. Renseigner le tenant (ID ou `batterieetcompagnie.fr`), l'ID d'application et le secret.
2. Indiquer la ou les boîtes à lire, puis enregistrer.
3. Cliquer sur **Tester la connexion** : les dossiers de contacts et leur nombre de contacts s'affichent.
4. Cliquer sur **Synchroniser maintenant**.

Le secret est stocké chiffré en base, comme le mot de passe SMTP.

## 6. Bonnes pratiques et RGPD

- N'écrire qu'à des personnes qui ont un lien avec l'association (adhérents, bénévoles, public ayant laissé son adresse).
- Ne jamais réabonner un contact désinscrit sans son accord explicite.
- Les adresses qui rebondissent passent « en erreur » et sont exclues des envois suivants.
- Les retours différés, ceux que le serveur du destinataire renvoie après coup, arrivent dans la boîte `info@m.batterieetcompagnie.fr`. Les consulter de temps en temps et passer les contacts concernés « en erreur ».
- Sauvegarde : le fichier `data/mailing.sqlite` contient toutes les données. Il est inclus dans les sauvegardes o2switch (JetBackup).

## Sécurité

- Le dépôt est **public** : aucun secret n'y figure. `config.php`, la base et les images envoyées sont exclus par `.gitignore`.
- Le mot de passe SMTP est chiffré en base (AES-256-GCM, clé dérivée de `app_secret`).
- Tous les formulaires sont protégés par jeton CSRF. Mots de passe hachés avec `password_hash`.
- Seul `public/` est exposé. Un `.htaccess` bloque le reste si la racine web était mal configurée.

## Développement local

```bash
cp config.sample.php config.php   # puis renseigner app_secret
php -S 127.0.0.1:8080 -t public
php cron.php                      # traiter la file à la main
```
