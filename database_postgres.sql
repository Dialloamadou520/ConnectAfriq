-- ═══════════════════════════════════════════════════════════════
-- ConnectAfriq - Structure de la base de données PostgreSQL
-- ═══════════════════════════════════════════════════════════════

-- ─── Table des utilisateurs (jeunes diplômés) ───
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    ville VARCHAR(100),
    region VARCHAR(100),
    diplome VARCHAR(100),
    domaine VARCHAR(150),
    type_opportunite VARCHAR(100),
    competences TEXT,
    points INT DEFAULT 0,
    badges JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des entreprises ───
CREATE TABLE IF NOT EXISTS entreprises (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    contact_nom VARCHAR(150),
    contact_poste VARCHAR(100),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    secteur VARCHAR(100),
    ville VARCHAR(100),
    description TEXT,
    logo_url VARCHAR(500),
    couleur_bg VARCHAR(100) DEFAULT 'linear-gradient(135deg,#00e5a0,#00c9ff)',
    avatar VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des offres d'emploi/stage ───
CREATE TABLE IF NOT EXISTS offres (
    id SERIAL PRIMARY KEY,
    entreprise_id INT NOT NULL REFERENCES entreprises(id) ON DELETE CASCADE,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type_contrat VARCHAR(20) DEFAULT 'Stage' CHECK (type_contrat IN ('Stage', 'Emploi', 'Alternance')),
    duree VARCHAR(50),
    ville VARCHAR(100),
    competences_requises TEXT,
    salaire VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des candidatures ───
CREATE TABLE IF NOT EXISTS candidatures (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    entreprise_id INT NOT NULL REFERENCES entreprises(id) ON DELETE CASCADE,
    offre_id INT REFERENCES offres(id) ON DELETE SET NULL,
    statut VARCHAR(20) DEFAULT 'en_attente' CHECK (statut IN ('en_attente', 'vue', 'acceptee', 'refusee')),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des mentors ───
CREATE TABLE IF NOT EXISTS mentors (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    role VARCHAR(150),
    avatar VARCHAR(10),
    keywords JSONB,
    email VARCHAR(255),
    bio TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des demandes de mentorat ───
CREATE TABLE IF NOT EXISTS demandes_mentorat (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    mentor_id INT NOT NULL REFERENCES mentors(id) ON DELETE CASCADE,
    statut VARCHAR(20) DEFAULT 'en_attente' CHECK (statut IN ('en_attente', 'acceptee', 'refusee')),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des projets collaboratifs ───
CREATE TABLE IF NOT EXISTS projets (
    id SERIAL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    createur_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    membres_actuels INT DEFAULT 1,
    membres_max INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des membres de projets ───
CREATE TABLE IF NOT EXISTS projet_membres (
    id SERIAL PRIMARY KEY,
    projet_id INT NOT NULL REFERENCES projets(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (projet_id, user_id)
);

-- ─── Table des événements ───
CREATE TABLE IF NOT EXISTS evenements (
    id SERIAL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_evenement TIMESTAMP NOT NULL,
    lieu VARCHAR(255),
    points_gagnes INT DEFAULT 50,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des inscriptions aux événements ───
CREATE TABLE IF NOT EXISTS inscriptions_evenements (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    evenement_id INT NOT NULL REFERENCES evenements(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, evenement_id)
);

-- ─── Table des notifications ───
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    titre VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Table des sessions ───
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    entreprise_id INT REFERENCES entreprises(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('jeune', 'entreprise')),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ═══════════════════════════════════════════════════════════════
-- Données initiales
-- ═══════════════════════════════════════════════════════════════

-- Insertion des entreprises partenaires
INSERT INTO entreprises (nom, email, password, secteur, ville, description, couleur_bg, avatar, contact_nom, contact_poste) VALUES
('Wavecom Sénégal', 'contact@wavecom.sn', '$2y$10$dummy', 'telecom', 'Dakar', 'Leader en solutions télécommunications, nous recherchons des stagiaires passionnés par la technologie mobile.', 'linear-gradient(135deg,#00e5a0,#00c9ff)', 'WS', 'Amadou Diallo', 'DRH'),
('BanqUe Nationale', 'rh@banquenationale.sn', '$2y$10$dummy', 'finance', 'Dakar', 'Banque historique du pays, nous offrons des opportunités dans la finance, le crédit et la gestion portefeuille.', 'linear-gradient(135deg,#f0c040,#ff9f43)', 'BN', 'Fatou Sow', 'Responsable RH'),
('TechAfriq Solutions', 'jobs@techafrique.sn', '$2y$10$dummy', 'tech', 'Dakar', 'Startup tech spécialisée en IA et développement web. Stage idéal pour les passionnés de code.', 'linear-gradient(135deg,#a855f7,#6366f1)', 'TA', 'Moussa Ndiaye', 'CTO'),
('Santé Plus Clinique', 'rh@santeplus.sn', '$2y$10$dummy', 'sante', 'Saint-Louis', 'Établissement de santé multi-spécialités cherchant des stagiaires en médecine et paramedical.', 'linear-gradient(135deg,#ef4444,#f97316)', 'SP', 'Dr. Awa Fall', 'Directrice'),
('EduConnect Afrique', 'contact@educonnect.sn', '$2y$10$dummy', 'education', 'Thiès', 'Organisation dédiée à l''éducation numérique, nous créons des contenus pédagogiques innovants.', 'linear-gradient(135deg,#10b981,#14b8a6)', 'EA', 'Ibrahima Sarr', 'Directeur'),
('ShopdakaR', 'recrutement@shopdakar.sn', '$2y$10$dummy', 'commerce', 'Dakar', 'Plus grande plateforme e-commerce au Sénégal. Rejoignez notre équipe dynamique marketing et logistique.', 'linear-gradient(135deg,#f43f5e,#ec4899)', 'SD', 'Marie Diop', 'RH Manager'),
('Orange Sénégal', 'carrieres@orange.sn', '$2y$10$dummy', 'telecom', 'Dakar', 'Opérateur télécoms n°1, nous cherchons des talents en réseau, cybersécurité et gestion client.', 'linear-gradient(135deg,#f97316,#fb923c)', 'OS', 'Cheikh Ba', 'DRH'),
('Groupe Fintech DA', 'jobs@fintechda.sn', '$2y$10$dummy', 'finance', 'Dakar', 'Fintech innovante spécialisée en paiement mobile et crédit digital pour l''Afrique de l''Ouest.', 'linear-gradient(135deg,#0ea5e9,#38bdf8)', 'GF', 'Aissatou Gueye', 'CEO'),
('Académie NumériQ', 'contact@academienumeriq.sn', '$2y$10$dummy', 'education', 'Dakar', 'École de formation numérique avec une approche pratique. Parfait pour les jeunes en reconversion.', 'linear-gradient(135deg,#8b5cf6,#c084fc)', 'AN', 'Papa Seck', 'Fondateur'),
('AgriTech Sénégal', 'info@agritech.sn', '$2y$10$dummy', 'tech', 'Kaolack', 'Startup ag-tech qui révolutionne l''agriculture sénégalaise avec des capteurs IoT et l''IA.', 'linear-gradient(135deg,#22c55e,#4ade80)', 'AS', 'Oumar Sy', 'CTO'),
('Distrib Marché Dakar', 'rh@distribmarche.sn', '$2y$10$dummy', 'commerce', 'Dakar', 'Réseau de distribution alimentaire et de grande distribution. Opportunités en logistique et supply chain.', 'linear-gradient(135deg,#eab308,#facc15)', 'DM', 'Ndèye Fatou', 'DRH'),
('Expresso Télécom', 'careers@expresso.sn', '$2y$10$dummy', 'telecom', 'Dakar', 'Opérateur internet fixe et mobile, nous cherchons des ingénieurs réseau et des chargés de client.', 'linear-gradient(135deg,#6366f1,#818cf8)', 'ET', 'Mamadou Diaw', 'HR Director');

-- Insertion des offres
INSERT INTO offres (entreprise_id, titre, type_contrat, duree, ville, description) VALUES
(1, 'Stage Développeur Mobile', 'Stage', '3 mois', 'Dakar', 'Stage en développement d''applications mobiles Android/iOS'),
(2, 'Analyste Financier Junior', 'Emploi', 'CDI', 'Dakar', 'Poste d''analyste financier pour jeune diplômé'),
(3, 'Stage Développeur Web Full Stack', 'Stage', '6 mois', 'Dakar', 'Stage en développement web avec React et Node.js'),
(4, 'Stage Infirmier', 'Stage', '3 mois', 'Saint-Louis', 'Stage pratique en soins infirmiers'),
(5, 'Chargé de Contenu Pédagogique', 'Emploi', 'CDI', 'Thiès', 'Création de contenus éducatifs numériques'),
(6, 'Stage Marketing Digital', 'Stage', '4 mois', 'Dakar', 'Stage en marketing digital et e-commerce');

-- Insertion d'un utilisateur de test (nécessaire pour les projets)
INSERT INTO users (prenom, nom, email, password, ville, region, diplome, domaine, competences, points) VALUES
('Admin', 'ConnectAfriq', 'admin@connectafriq.sn', '$2y$10$dummy', 'Dakar', 'Dakar', 'Master', 'Informatique', 'tech,ia,dev', 500);

-- Insertion des mentors
INSERT INTO mentors (nom, role, avatar, keywords, email, bio) VALUES
('Dr. Samba Ndiaye', 'Expert en Tech', 'SN', '["tech","ia","dev"]', 'samba.ndiaye@mentor.sn', 'Expert en intelligence artificielle avec 15 ans d''expérience'),
('Marie Diop', 'Consultante Finance', 'MD', '["finance","banque","gestion"]', 'marie.diop@mentor.sn', 'Consultante senior en finance d''entreprise'),
('Amadou Fall', 'Entrepreneur Tech', 'AF', '["startup","tech","business"]', 'amadou.fall@mentor.sn', 'Fondateur de 3 startups tech au Sénégal'),
('Fatou Sow', 'DRH Senior', 'FS', '["rh","recrutement","carriere"]', 'fatou.sow@mentor.sn', 'Directrice RH avec expertise en développement de carrière');

-- Insertion des projets collaboratifs
INSERT INTO projets (titre, description, createur_id, membres_actuels, membres_max) VALUES
('Développement App Mobile pour Agri', 'Projet pour créer une app aidant les agriculteurs sénégalais.', 1, 3, 5),
('Campagne Marketing Digital', 'Concevez une campagne marketing pour une startup locale.', 1, 2, 4);

-- Insertion des événements
INSERT INTO evenements (titre, description, date_evenement, lieu, points_gagnes) VALUES
('Atelier AI pour CV Parfait', 'Apprenez à utiliser l''IA pour optimiser votre CV et attirer les recruteurs.', '2026-02-15 14:00:00', 'En ligne', 50),
('Webinaire: Réseautage Digital', 'Découvrez des stratégies innovantes pour bâtir votre réseau sur LinkedIn et X.', '2026-02-22 10:00:00', 'En ligne', 50),
('Hackathon Emploi Vert', 'Participez à un hackathon pour des solutions durables dans l''emploi au Sénégal.', '2026-03-01 16:00:00', 'Dakar Innovation Hub', 100);
