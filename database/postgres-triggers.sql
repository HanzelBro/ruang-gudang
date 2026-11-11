CREATE FUNCTION after_alokasi_rak_insert() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v_berat numeric(6,2);
BEGIN
    SELECT berat_kg INTO v_berat FROM paket WHERE no_resi=NEW.no_resi;
    UPDATE rak_gudang SET kapasitas_terpakai_kg=kapasitas_terpakai_kg+v_berat WHERE id_rak=NEW.id_rak;
    UPDATE paket SET status_paket='Tersimpan' WHERE no_resi=NEW.no_resi;
    RETURN NEW;
END;
$$;
CREATE TRIGGER trg_after_alokasi_rak_insert AFTER INSERT ON alokasi_rak FOR EACH ROW EXECUTE FUNCTION after_alokasi_rak_insert();

CREATE FUNCTION after_pengiriman_insert() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v_id_rak integer; v_berat numeric(6,2);
BEGIN
    SELECT id_rak INTO v_id_rak FROM alokasi_rak WHERE no_resi=NEW.no_resi AND status_alokasi='Aktif' LIMIT 1;
    SELECT berat_kg INTO v_berat FROM paket WHERE no_resi=NEW.no_resi;
    IF v_id_rak IS NOT NULL THEN
        UPDATE rak_gudang SET kapasitas_terpakai_kg=GREATEST(0,kapasitas_terpakai_kg-v_berat) WHERE id_rak=v_id_rak;
        UPDATE alokasi_rak SET status_alokasi='Selesai' WHERE no_resi=NEW.no_resi AND status_alokasi='Aktif';
    END IF;
    UPDATE paket SET status_paket='Terkirim' WHERE no_resi=NEW.no_resi;
    RETURN NEW;
END;
$$;
CREATE TRIGGER trg_after_pengiriman_insert AFTER INSERT ON pengiriman FOR EACH ROW EXECUTE FUNCTION after_pengiriman_insert();
