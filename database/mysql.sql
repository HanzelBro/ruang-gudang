-- Empty schema for a new MySQL/MariaDB database. No business records are included.
-- Select an empty database before importing this file.
SET NAMES utf8mb4;

CREATE TABLE petugas (
    id_petugas int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nama_petugas varchar(100) NOT NULL,
    role_petugas enum('Admin','Inbound Checker','Outbound Sorter') NOT NULL,
    no_telepon varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rak_gudang (
    id_rak int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kode_rak varchar(20) NOT NULL UNIQUE,
    zona varchar(20) NOT NULL,
    kapasitas_maks_kg decimal(6,2) NOT NULL,
    kapasitas_terpakai_kg decimal(6,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paket (
    no_resi varchar(30) NOT NULL PRIMARY KEY,
    nama_pengirim varchar(100) NOT NULL,
    nama_penerima varchar(100) NOT NULL,
    kota_tujuan varchar(50) NOT NULL,
    berat_kg decimal(6,2) NOT NULL,
    kategori varchar(50) NOT NULL,
    status_paket enum('Diterima','Tersimpan','Siap Kirim','Terkirim') NOT NULL DEFAULT 'Diterima'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alokasi_rak (
    id_alokasi int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    no_resi varchar(30) NOT NULL,
    id_rak int NOT NULL,
    id_petugas int NOT NULL,
    waktu_masuk datetime NOT NULL DEFAULT current_timestamp(),
    status_alokasi enum('Aktif','Selesai') NOT NULL DEFAULT 'Aktif',
    CONSTRAINT fk_alokasi_paket FOREIGN KEY (no_resi) REFERENCES paket(no_resi) ON UPDATE CASCADE,
    CONSTRAINT fk_alokasi_rak FOREIGN KEY (id_rak) REFERENCES rak_gudang(id_rak) ON UPDATE CASCADE,
    CONSTRAINT fk_alokasi_petugas FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pengiriman (
    id_pengiriman int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    no_resi varchar(30) NOT NULL UNIQUE,
    id_petugas int NOT NULL,
    nama_kurir varchar(100) NOT NULL,
    no_kendaraan varchar(20) NOT NULL,
    waktu_kirim datetime NOT NULL DEFAULT current_timestamp(),
    status_kirim enum('Dalam Perjalanan','Terkirim','Selesai') NOT NULL DEFAULT 'Dalam Perjalanan',
    CONSTRAINT fk_kirim_paket FOREIGN KEY (no_resi) REFERENCES paket(no_resi) ON UPDATE CASCADE,
    CONSTRAINT fk_kirim_petugas FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER trg_after_alokasi_rak_insert AFTER INSERT ON alokasi_rak FOR EACH ROW
BEGIN
    DECLARE v_berat DECIMAL(6,2);
    SELECT berat_kg INTO v_berat FROM paket WHERE no_resi = NEW.no_resi;
    UPDATE rak_gudang SET kapasitas_terpakai_kg = kapasitas_terpakai_kg + v_berat WHERE id_rak = NEW.id_rak;
    UPDATE paket SET status_paket = 'Tersimpan' WHERE no_resi = NEW.no_resi;
END$$

CREATE TRIGGER trg_after_pengiriman_insert AFTER INSERT ON pengiriman FOR EACH ROW
BEGIN
    DECLARE v_id_rak INT;
    DECLARE v_berat DECIMAL(6,2);
    SELECT id_rak INTO v_id_rak FROM alokasi_rak WHERE no_resi = NEW.no_resi AND status_alokasi = 'Aktif' LIMIT 1;
    SELECT berat_kg INTO v_berat FROM paket WHERE no_resi = NEW.no_resi;
    IF v_id_rak IS NOT NULL THEN
        UPDATE rak_gudang SET kapasitas_terpakai_kg = GREATEST(0.00, kapasitas_terpakai_kg - v_berat) WHERE id_rak = v_id_rak;
        UPDATE alokasi_rak SET status_alokasi = 'Selesai' WHERE no_resi = NEW.no_resi AND status_alokasi = 'Aktif';
    END IF;
    UPDATE paket SET status_paket = 'Terkirim' WHERE no_resi = NEW.no_resi;
END$$
DELIMITER ;
