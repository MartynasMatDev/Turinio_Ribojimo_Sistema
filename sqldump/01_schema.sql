-- =====================================================================
-- Turinio ribojimo sistema mokyklos bevieliui
-- Duomenų bazės struktūra (MySQL 8 / MariaDB 10+)
-- Autorius: Martynas Matutis
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS turinio_ribojimas
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE turinio_ribojimas;

DROP TABLE IF EXISTS bandymai;
DROP TABLE IF EXISTS draudimai;
DROP TABLE IF EXISTS url_adresai;
DROP TABLE IF EXISTS kategorijos;
DROP TABLE IF EXISTS importai;
DROP TABLE IF EXISTS mokiniai;
DROP TABLE IF EXISTS vartotojai;
DROP TABLE IF EXISTS mokiniu_grupes;

-- Kontroliuojamų vartotojų (mokinių) rūšys
CREATE TABLE mokiniu_grupes (
  id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kodas         VARCHAR(20)  NOT NULL,
  pavadinimas   VARCHAR(60)  NOT NULL,
  klase_nuo     TINYINT UNSIGNED NOT NULL,
  klase_iki     TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_grupes_kodas (kodas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visi sistemos vartotojai (darbuotojai ir mokiniai)
CREATE TABLE vartotojai (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prisijungimo_vardas     VARCHAR(40)  NOT NULL,
  slaptazodis             VARCHAR(255) NOT NULL,           -- password_hash() (bcrypt)
  vardas                  VARCHAR(50)  NOT NULL,
  pavarde                 VARCHAR(50)  NOT NULL,
  role                    ENUM('admin','saugos','direktorius','mokinys') NOT NULL,
  aktyvus                 TINYINT(1)   NOT NULL DEFAULT 1,
  sukurta                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paskutinis_prisijungimas DATETIME    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_prisijungimo_vardas (prisijungimo_vardas),
  KEY ix_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mokinio papildomi duomenys (1:1 su vartotojai)
CREATE TABLE mokiniai (
  vartotojo_id  INT UNSIGNED NOT NULL,
  klase         VARCHAR(6)   NOT NULL,                     -- pvz. 3A, 7B, IIIG
  klases_nr     TINYINT UNSIGNED NOT NULL,                 -- 1..12
  grupes_id     TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (vartotojo_id),
  KEY ix_mok_grupe (grupes_id),
  CONSTRAINT fk_mok_vart  FOREIGN KEY (vartotojo_id) REFERENCES vartotojai(id) ON DELETE CASCADE,
  CONSTRAINT fk_mok_grupe FOREIGN KEY (grupes_id)    REFERENCES mokiniu_grupes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mokinių sąrašo importavimo istorija
CREATE TABLE importai (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  administratoriaus_id INT UNSIGNED NULL,
  failo_vardas        VARCHAR(255) NOT NULL,
  sukurta_nauju       INT UNSIGNED NOT NULL DEFAULT 0,
  atnaujinta          INT UNSIGNED NOT NULL DEFAULT 0,
  praleista           INT UNSIGNED NOT NULL DEFAULT 0,
  laikas              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_imp_admin FOREIGN KEY (administratoriaus_id) REFERENCES vartotojai(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontroliuojamų URL kategorijos
CREATE TABLE kategorijos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pavadinimas  VARCHAR(60)  NOT NULL,
  aprasymas    VARCHAR(255) NULL,
  sukurta      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kat_pavadinimas (pavadinimas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategorijoms priskirti adresai (domenai). Blokuojamas domenas ir visi jo subdomenai.
CREATE TABLE url_adresai (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kategorijos_id  INT UNSIGNED NOT NULL,
  adresas         VARCHAR(253) NOT NULL,                   -- normalizuotas domenas, pvz. facebook.com
  pastaba         VARCHAR(255) NULL,
  sukure_id       INT UNSIGNED NULL,
  sukurta         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kat_adresas (kategorijos_id, adresas),
  KEY ix_adresas (adresas),
  CONSTRAINT fk_url_kat   FOREIGN KEY (kategorijos_id) REFERENCES kategorijos(id) ON DELETE CASCADE,
  CONSTRAINT fk_url_vart  FOREIGN KEY (sukure_id)      REFERENCES vartotojai(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Direktoriaus nustatyti draudimai: kuri kategorija uždrausta kuriai mokinių grupei
CREATE TABLE draudimai (
  grupes_id       TINYINT UNSIGNED NOT NULL,
  kategorijos_id  INT UNSIGNED NOT NULL,
  nustate_id      INT UNSIGNED NULL,
  nustatyta       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (grupes_id, kategorijos_id),
  CONSTRAINT fk_dr_grupe FOREIGN KEY (grupes_id)      REFERENCES mokiniu_grupes(id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_kat   FOREIGN KEY (kategorijos_id) REFERENCES kategorijos(id)    ON DELETE CASCADE,
  CONSTRAINT fk_dr_vart  FOREIGN KEY (nustate_id)     REFERENCES vartotojai(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mokinių bandymai prisijungti prie URL (registruojami visi)
CREATE TABLE bandymai (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mokinio_id      INT UNSIGNED NOT NULL,
  ivestas_url     VARCHAR(500) NOT NULL,
  domenas         VARCHAR(253) NOT NULL,
  rezultatas      ENUM('leidziama','draudziama') NOT NULL,
  kategorijos_id  INT UNSIGNED NULL,                       -- kategorija, dėl kurios uždrausta (arba kuriai priskirta)
  url_id          INT UNSIGNED NULL,
  ip_adresas      VARCHAR(45)  NULL,
  laikas          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_band_mok (mokinio_id),
  KEY ix_band_laikas (laikas),
  KEY ix_band_rez (rezultatas),
  CONSTRAINT fk_band_mok FOREIGN KEY (mokinio_id)     REFERENCES vartotojai(id)  ON DELETE CASCADE,
  CONSTRAINT fk_band_kat FOREIGN KEY (kategorijos_id) REFERENCES kategorijos(id) ON DELETE SET NULL,
  CONSTRAINT fk_band_url FOREIGN KEY (url_id)         REFERENCES url_adresai(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
