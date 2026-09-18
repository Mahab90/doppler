-- EchoCardio — Schéma PostgreSQL complet
-- Exécuter : psql -U postgres -d doppler -f config/bd.sql

DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS roles CASCADE;
DROP TABLE IF EXISTS echocardiographies CASCADE;
DROP TABLE IF EXISTS patients CASCADE;
DROP TABLE IF EXISTS medecins CASCADE;

CREATE TABLE medecins (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    specialite VARCHAR(100) DEFAULT 'Cardiologue',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE patients (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    age INTEGER,
    sexe CHAR(1) CHECK (sexe IN ('M', 'F', NULL)),
    poids DECIMAL(5,2),
    taille DECIMAL(5,2),
    telephone VARCHAR(20),
    num_dossier VARCHAR(50),
    service VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE echocardiographies (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    medecin_id INTEGER REFERENCES medecins(id),
    date_examen DATE NOT NULL,
    indication TEXT,
    appareil VARCHAR(200),
    medecin_demandeur VARCHAR(200),
    echographiste VARCHAR(200),
    surface_corporelle DECIMAL(5,2),
    fenetre VARCHAR(100),
    fenetre_mode VARCHAR(100),
    vo_dia DECIMAL(5,2),
    siv DECIMAL(5,2),
    dts DECIMAL(5,2),
    dtd DECIMAL(5,2),
    pp DECIMAL(5,2),
    ao DECIMAL(5,2),
    og DECIMAL(5,2),
    fe DECIMAL(5,2),
    fr DECIMAL(5,2),
    cinetique_globale TEXT,
    vg_obs TEXT,
    vd_obs TEXT,
    aorte_obs TEXT,
    paroi_post_obs TEXT,
    siv_obs TEXT,
    og_obs TEXT,
    s_og DECIMAL(5,2),
    od_obs TEXT,
    s_od DECIMAL(5,2),
    taps DECIMAL(5,2),
    vci_obs TEXT,
    vci_diametre DECIMAL(5,2),
    valve_mitrale TEXT,
    valve_aortique TEXT,
    valve_pulmonaire TEXT,
    valve_tricuspide TEXT,
    pericarde TEXT,
    doppler_mitrale TEXT,
    doppler_tricuspide TEXT,
    doppler_pulmonaire TEXT,
    doppler_aortique TEXT,
    doppler_tricuspide_vmax DECIMAL(6,2),
    paps DECIMAL(6,2),
    doppler_pulmonaire_vmax DECIMAL(6,2),
    doppler_aortique_vmax DECIMAL(6,2),
    doppler_sor DECIMAL(6,2),
    doppler_vor DECIMAL(6,2),
    doppler_ea DECIMAL(5,2),
    commentaire TEXT,
    conclusion TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    can_manage_users BOOLEAN DEFAULT FALSE,
    can_manage_fiches BOOLEAN DEFAULT FALSE,
    can_view_logs BOOLEAN DEFAULT FALSE,
    can_create_fiches BOOLEAN DEFAULT FALSE,
    can_update_fiches BOOLEAN DEFAULT FALSE,
    can_delete_fiches BOOLEAN DEFAULT FALSE,
    can_view_fiches BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    password_hash VARCHAR(255) NOT NULL,
    role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_name VARCHAR(150),
    role_name VARCHAR(100),
    action TEXT NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_echo_date ON echocardiographies(date_examen);
CREATE INDEX idx_echo_patient ON echocardiographies(patient_id);
CREATE INDEX idx_patient_nom ON patients(nom, prenom);
CREATE INDEX idx_patient_dossier ON patients(num_dossier);
CREATE INDEX idx_patient_tel ON patients(telephone);
CREATE INDEX idx_logs_created_at ON activity_logs(created_at);

INSERT INTO roles (nom, description, can_manage_users, can_manage_fiches, can_view_logs, can_create_fiches, can_update_fiches, can_delete_fiches, can_view_fiches) VALUES
    ('admin', 'Administration complète', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE),
    ('m1', 'Gestion complète des fiches', FALSE, TRUE, FALSE, TRUE, TRUE, TRUE, TRUE),
    ('m2', 'Création et lecture des fiches', FALSE, TRUE, FALSE, TRUE, FALSE, FALSE, TRUE);

INSERT INTO users (username, nom, prenom, email, password_hash, role_id, is_active) VALUES
    ('admin', 'ADMIN', 'Administrateur', 'admin@doppler.local', '$2y$10$KwOpLQ3WLgeIwNlK1AAov.mRCwc/NTqf1belMY5d7xwVUtJdPWZpK', 1, TRUE),
    ('m1', 'MEDECIN', 'Niveau 1', 'm1@doppler.local', '$2y$10$awY3iIus92ZrKeTJ.GjV9uyKtvbN2eN.0ogR9z775SVhYLcH8NKOO', 2, TRUE),
    ('m2', 'MEDECIN', 'Niveau 2', 'm2@doppler.local', '$2y$10$xGGDryOEjDMdyzIowwaXAeeAlRnUoeeKTEsN4vPkmykn7HkHKtLsy', 3, TRUE);

INSERT INTO medecins (nom, prenom) VALUES
    ('TCHIRGNY', 'Reynatou'),
    ('ADAMOU', 'Amadou B.');

INSERT INTO patients (nom, prenom, age, sexe, poids, taille, telephone, num_dossier, service) VALUES
    ('ALHOUSSEINI', 'Aminatou', 28, 'F', 62.5, 165, '96 12 34 56', 'DOS-2026-001', 'Cardiologie');

INSERT INTO echocardiographies (
    patient_id, date_examen, indication, appareil, medecin_demandeur, echographiste,
    vo_dia, siv, dts, dtd, pp, ao, og, fe, fr,
    cinetique_globale, vg_obs, vd_obs, aorte_obs, paroi_post_obs, siv_obs, og_obs, s_og, od_obs, s_od, taps, vci_obs, vci_diametre,
    valve_mitrale, valve_aortique, valve_pulmonaire, valve_tricuspide, pericarde,
    doppler_mitrale, doppler_tricuspide, doppler_pulmonaire, doppler_aortique,
    doppler_tricuspide_vmax, paps, doppler_pulmonaire_vmax, doppler_aortique_vmax,
    doppler_sor, doppler_vor, doppler_ea,
    commentaire, conclusion
) VALUES (
    1, '2026-06-02',
    'Dyspnée + palpitations',
    'Echographe Mindray modèle DC-N6',
    'Dr Amadou B. Adamou',
    'Dr Tchirgny M. Reynatou',
    30, 9, 34, 48, 9, 28, 38, 63, 35,
    'Bonne cinétique globale et segmentaire',
    'VG non dilaté', 'VD non dilaté', 'Aorte non dilatée', 'Paroi post non hypertrophiée',
    'SIV non hypertrophié', 'OG non dilaté', 14, 'OD non dilaté', 12, 24, 'VCI fine', 13,
    'Valve mitrale épaissie avec MVP du GVM',
    'Valve aortique fine', 'Valve pulmonaire fine', 'Valve tricuspide fine', 'Péricarde sec',
    'IM moyenne — SOR=0.47 cm², VOR=69.86 ml, E/A=1.14',
    'Normale', 'Normale', 'Normale',
    1.05, 5, 118, 1.08,
    0.47, 69.86, 1.14,
    'Cavités cardiaques de taille normale. Bonne fonction systolique (FEVG 63%). Pressions de remplissage normales. Absence d''HTAP.',
    'Insuffisance Mitrale moyenne d''allure rhumatismale sans retentissement sur les cavités cardiaques. Bonne fonction systolique.'
);
