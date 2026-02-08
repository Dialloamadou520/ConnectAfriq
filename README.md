# ConnectAfriq

Plateforme d'insertion professionnelle pour les jeunes Sénégalais.

## 🚀 Déploiement sur Render

### Prérequis
- Compte GitHub
- Compte Render (https://render.com)

### Étapes de déploiement

#### 1. Pousser le code sur GitHub

```bash
cd c:\xampp\htdocs\ConnectAfriq
git init
git add .
git commit -m "Initial commit - ConnectAfriq"
git branch -M main
git remote add origin https://github.com/VOTRE_USERNAME/connectafriq.git
git push -u origin main
```

#### 2. Créer les services sur Render

1. Connectez-vous à [Render Dashboard](https://dashboard.render.com)
2. Cliquez sur **New +** → **Blueprint**
3. Connectez votre repo GitHub
4. Render détectera automatiquement le fichier `render.yaml`
5. Cliquez sur **Apply** pour créer les services

#### 3. Initialiser la base de données

Après le déploiement, connectez-vous à la base de données MySQL et exécutez le script `database.sql` :

1. Dans Render Dashboard, allez dans votre base de données
2. Copiez les informations de connexion
3. Utilisez un client MySQL (DBeaver, MySQL Workbench) pour vous connecter
4. Exécutez le contenu de `database.sql`

### Variables d'environnement

Les variables suivantes sont automatiquement configurées par Render :
- `DB_HOST` - Hôte de la base de données
- `DB_NAME` - Nom de la base de données
- `DB_USER` - Utilisateur
- `DB_PASS` - Mot de passe
- `DB_PORT` - Port (généralement 3306)

## 💻 Développement local

### Prérequis
- XAMPP (Apache + MySQL + PHP)

### Installation

1. Clonez le projet dans `htdocs`
2. Importez `database.sql` dans phpMyAdmin
3. Accédez à http://localhost/ConnectAfriq

## 📁 Structure du projet

```
ConnectAfriq/
├── api/                    # Backend PHP
│   ├── config.php          # Configuration DB
│   ├── auth.php            # Authentification
│   ├── entreprises.php     # API entreprises
│   ├── candidatures.php    # API candidatures
│   ├── mentors.php         # API mentors
│   ├── projets.php         # API projets
│   ├── evenements.php      # API événements
│   └── gamification.php    # API gamification
├── index.html              # Page principale
├── styles.css              # Styles CSS
├── app.js                  # JavaScript frontend
├── database.sql            # Script SQL
├── Dockerfile              # Configuration Docker
├── render.yaml             # Configuration Render
└── README.md               # Documentation
```

## 🛠 Technologies

- **Frontend** : HTML5, CSS3, JavaScript (Vanilla)
- **Backend** : PHP 8.2
- **Base de données** : MySQL
- **Hébergement** : Render

## 📝 Licence

© 2025 ConnectAfriq - Tous droits réservés
