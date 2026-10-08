-- Schema PostgreSQL EchoCardio
-- Create the database first, then run: psql -U postgres -d doppler -f config/bd.sql
-- This script is safe to rerun and preserves existing records.

CREATE TABLE IF NOT EXISTS medecins (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    specialite VARCHAR(100) DEFAULT 'Cardiologue',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS patients (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    age INTEGER,
    sexe CHAR(1) CHECK (sexe IN ('M', 'F')),
    poids DECIMAL(5,2),
    taille DECIMAL(5,2),
    telephone VARCHAR(20),
    num_dossier VARCHAR(50),
    service VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS echocardiographies (
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

CREATE TABLE IF NOT EXISTS roles (
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
    can_manage_patients BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
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

CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_name VARCHAR(150),
    role_name VARCHAR(100),
    action TEXT NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Add fields introduced after the initial schema to existing installations.
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS fenetre VARCHAR(100);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS fenetre_mode VARCHAR(100);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS vd_obs TEXT;
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS s_og DECIMAL(5,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS s_od DECIMAL(5,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS taps DECIMAL(5,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS vci_diametre DECIMAL(5,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS doppler_tricuspide_vmax DECIMAL(6,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS paps DECIMAL(6,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS doppler_pulmonaire_vmax DECIMAL(6,2);
ALTER TABLE echocardiographies ADD COLUMN IF NOT EXISTS doppler_aortique_vmax DECIMAL(6,2);
ALTER TABLE roles ADD COLUMN IF NOT EXISTS can_manage_patients BOOLEAN DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_echo_date ON echocardiographies(date_examen);
CREATE INDEX IF NOT EXISTS idx_echo_patient ON echocardiographies(patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_nom ON patients(nom, prenom);
CREATE INDEX IF NOT EXISTS idx_patient_dossier ON patients(num_dossier);
CREATE INDEX IF NOT EXISTS idx_patient_tel ON patients(telephone);
CREATE INDEX IF NOT EXISTS idx_logs_created_at ON activity_logs(created_at);

INSERT INTO roles (nom, description, can_manage_users, can_manage_fiches, can_view_logs, can_create_fiches, can_update_fiches, can_delete_fiches, can_view_fiches, can_manage_patients) VALUES
    ('admin', 'Administration complète', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE),
    ('m1', 'Gestion complète des fiches', FALSE, TRUE, FALSE, TRUE, TRUE, TRUE, TRUE, FALSE),
    ('m2', 'Création et lecture des fiches', FALSE, TRUE, FALSE, TRUE, FALSE, FALSE, TRUE, FALSE)
ON CONFLICT (nom) DO NOTHING;

UPDATE roles SET can_manage_patients = TRUE WHERE nom = 'admin';

INSERT INTO users (username, nom, prenom, email, password_hash, role_id, is_active)
SELECT 'admin', 'ADMIN', 'Administrateur', 'admin@doppler.local',
       '$2y$10$KwOpLQ3WLgeIwNlK1AAov.mRCwc/NTqf1belMY5d7xwVUtJdPWZpK', id, TRUE
FROM roles
WHERE nom = 'admin'
ON CONFLICT (username) DO NOTHING;
