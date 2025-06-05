TRUNCATE TABLE file_chunks CASCADE;
TRUNCATE TABLE files CASCADE;
TRUNCATE TABLE cloud_accounts CASCADE;
TRUNCATE TABLE users CASCADE;

ALTER SEQUENCE users_user_id_seq RESTART WITH 1;
ALTER SEQUENCE cloud_accounts_account_id_seq RESTART WITH 1;
ALTER SEQUENCE files_file_id_seq RESTART WITH 1;
ALTER SEQUENCE file_chunks_chunk_id_seq RESTART WITH 1;

DO $$
DECLARE
    lista_nume TEXT[] := ARRAY['Ababei','Acasandrei','Adascalitei','Afanasie','Agafitei','Agape','Aioanei','Alexandrescu','Alexandru','Alexe','Alexii','Amarghioalei','Ambroci','Andonesei','Andrei','Andrian','Andrici','Andronic','Andros','Anghelina','Anita','Antochi','Antonie','Apetrei','Apostol','Arhip','Arhire','Arteni','Arvinte','Asaftei','Asofiei','Aungurenci','Avadanei','Avram','Babei','Baciu','Baetu','Balan','Balica','Banu','Barbieru','Barzu','Bazgan','Bejan','Bejenaru','Belcescu','Belciuganu','Benchea','Bilan','Birsanu','Bivol','Bizu','Boca','Bodnar','Boistean','Borcan','Bordeianu','Botezatu','Bradea','Braescu','Budaca','Bulai','Bulbuc-aioanei','Burlacu','Burloiu','Bursuc','Butacu','Bute','Buza','Calancea','Calinescu','Capusneanu','Caraiman','Carbune','Carp','Catana','Catiru','Catonoiu','Cazacu','Cazamir','Cebere','Cehan','Cernescu','Chelaru','Chelmu','Chelmus','Chibici','Chicos','Chilaboc','Chile','Chiriac','Chirila','Chistol','Chitic','Chmilevski','Cimpoesu','Ciobanu','Ciobotaru','Ciocoiu','Ciofu','Ciornei','Citea','Ciucanu','Clatinici','Clim','Cobuz','Coca','Cojocariu','Cojocaru','Condurache','Corciu','Corduneanu','Corfu','Corneanu','Corodescu','Coseru','Cosnita','Costan','Covatariu','Cozma','Cozmiuc','Craciunas','Crainiceanu','Creanga','Cretu','Cristea','Crucerescu','Cumpata','Curca','Cusmuliuc','Damian','Damoc','Daneliuc','Daniel','Danila','Darie','Dascalescu','Dascalu','Diaconu','Dima','Dimache','Dinu','Dobos','Dochitei','Dochitoiu','Dodan','Dogaru','Domnaru','Dorneanu','Dragan','Dragoman','Dragomir','Dragomirescu','Duceac','Dudau','Durnea'];
    
    lista_prenume_fete TEXT[] := ARRAY['Adina','Alexandra','Alina','Ana','Anca','Anda','Andra','Andreea','Andreia','Antonia','Bianca','Camelia','Claudia','Codrina','Cristina','Daniela','Daria','Delia','Denisa','Diana','Ecaterina','Elena','Eleonora','Elisa','Ema','Emanuela','Emma','Gabriela','Georgiana','Ileana','Ilona','Ioana','Iolanda','Irina','Iulia','Iuliana','Larisa','Laura','Loredana','Madalina','Malina','Manuela','Maria','Mihaela','Mirela','Monica','Oana','Paula','Petruta','Raluca','Sabina','Sanziana','Simina','Simona','Stefana','Stefania','Tamara','Teodora','Theodora','Vasilica','Xena'];
    
    lista_prenume_baieti TEXT[] := ARRAY['Adrian','Alex','Alexandru','Alin','Andreas','Andrei','Aurelian','Beniamin','Bogdan','Camil','Catalin','Cezar','Ciprian','Claudiu','Codrin','Constantin','Corneliu','Cosmin','Costel','Cristian','Damian','Dan','Daniel','Danut','Darius','Denise','Dimitrie','Dorian','Dorin','Dragos','Dumitru','Eduard','Elvis','Emil','Ervin','Eugen','Eusebiu','Fabian','Filip','Florian','Florin','Gabriel','George','Gheorghe','Giani','Giulio','Iaroslav','Ilie','Ioan','Ion','Ionel','Ionut','Iosif','Irinel','Iulian','Iustin','Laurentiu','Liviu','Lucian','Marian','Marius','Matei','Mihai','Mihail','Nicolae','Nicu','Nicusor','Octavian','Ovidiu','Paul','Petru','Petrut','Radu','Rares','Razvan','Richard','Robert','Roland','Rolland','Romanescu','Sabin','Samuel','Sebastian','Sergiu','Silviu','Stefan','Teodor','Teofil','Theodor','Tudor','Vadim','Valentin','Valeriu','Vasile','Victor','Vlad','Vladimir','Vladut'];
    
    cloud_providers TEXT[] := ARRAY['box', 'dropbox', 'google'];
    
    file_types TEXT[] := ARRAY['image/jpeg', 'image/png', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.ms-word', 'application/vnd.ms-excel', 'application/vnd.ms-excel', 'video/mp4', 'audio/mpeg', 'application/zip', 'text/csv'];
    
    file_extensions TEXT[] := ARRAY['.jpg', '.png', '.pdf', '.txt', '.doc', '.docx', '.xls', '.xlsx', '.mp4', '.mp3', '.zip', '.csv'];
    
    file_names TEXT[] := ARRAY['document', 'report', 'presentation', 'photo', 'video', 'music', 'backup', 'project', 'thesis', 'contract', 'invoice', 'meeting_notes', 'vacation', 'family', 'work', 'personal', 'draft', 'final', 'archive', 'data'];
    
	v_variabila BIGINT;
    v_nume TEXT;
    v_prenume TEXT;
    v_prenume1 TEXT;
    v_prenume2 TEXT;
    v_username TEXT;
    v_email TEXT;
    v_password TEXT;
    v_provider TEXT;
    v_access_token TEXT;
    v_refresh_token TEXT;
    v_total_space BIGINT;
    v_space_available BIGINT;
    v_file_name TEXT;
    v_file_size BIGINT;
    v_file_type TEXT;
    v_temp INTEGER;
    v_temp1 INTEGER;
    v_temp2 INTEGER;
    v_user_id BIGINT;
    v_account_id BIGINT;
    v_file_id BIGINT;
    v_nr_chunks INTEGER;
    v_chunk_size BIGINT;
    v_chunk_file_id TEXT;
    v_i INTEGER;
    v_j INTEGER;
    
    user_rec RECORD;
    account_rec RECORD;
    file_rec RECORD;
    
BEGIN
    RAISE NOTICE 'Inserarea a 500 utilizatori...';
    
    FOR v_i IN 1..500 LOOP
        v_nume := lista_nume[FLOOR(RANDOM() * array_length(lista_nume, 1)) + 1];
        
        IF RANDOM() < 0.5 THEN
            v_prenume1 := lista_prenume_fete[FLOOR(RANDOM() * array_length(lista_prenume_fete, 1)) + 1];
        ELSE
            v_prenume1 := lista_prenume_baieti[FLOOR(RANDOM() * array_length(lista_prenume_baieti, 1)) + 1];
        END IF;
        
        v_username := LOWER(v_nume || '.' || v_prenume1);
        
        v_temp1 := 0;
        LOOP
            SELECT COUNT(*) INTO v_temp FROM users WHERE username = v_username || CASE WHEN v_temp1 > 0 THEN v_temp1::TEXT ELSE '' END;
            EXIT WHEN v_temp = 0;
            v_temp1 := FLOOR(RANDOM() * 1000) + 1;
        END LOOP;
        
        IF v_temp1 > 0 THEN
            v_username := v_username || v_temp1::TEXT;
        END IF;
        
        v_email := v_username;
        IF RANDOM() < 0.5 THEN
            v_email := v_email || '@gmail.com';
        ELSE
            v_email := v_email || '@yahoo.com';
        END IF;
        
        v_password := '$2a$10$' || MD5(RANDOM()::TEXT || CLOCK_TIMESTAMP()::TEXT);
        v_password := RPAD(v_password, 60, 'x');
        
        INSERT INTO users (username, email, password, created_at)
        VALUES (v_username, v_email, v_password, NOW() - (RANDOM() * INTERVAL '365 days'));
    END LOOP;
    
    RAISE NOTICE 'Inserarea a 500 utilizatori... GATA!';
    
    RAISE NOTICE 'Inserarea conturilor cloud...';
    
    FOR user_rec IN (SELECT user_id FROM users) LOOP
        v_temp := FLOOR(RANDOM() * 3) + 1;
        
        FOR v_j IN 1..v_temp LOOP
            v_provider := cloud_providers[FLOOR(RANDOM() * array_length(cloud_providers, 1)) + 1];
            
            SELECT COUNT(*) INTO v_temp1 FROM cloud_accounts 
            WHERE user_id = user_rec.user_id AND provider = v_provider;
            
            IF v_temp1 = 0 THEN
                SELECT username INTO v_username FROM users WHERE user_id = user_rec.user_id;
                
                CASE v_provider
                    WHEN 'google' THEN v_email := v_username || '@gmail.com';
                    WHEN 'dropbox' THEN v_email := v_username || '@dropbox.com';
                    WHEN 'box' THEN v_email := v_username || '@box.com';
                END CASE;
                
                v_access_token := 'access_' || MD5(RANDOM()::TEXT || user_rec.user_id::TEXT || v_provider);
                v_refresh_token := 'refresh_' || MD5(RANDOM()::TEXT || user_rec.user_id::TEXT || v_provider);
                
                v_total_space := (FLOOR(RANDOM() * 1000) + 1) * 1024 * 1024 * 1024; 
                v_space_available := v_total_space - FLOOR(RANDOM() * v_total_space * 0.8); 
                
                INSERT INTO cloud_accounts (user_id, provider, email, access_token, refresh_token, 
                                          token_expiry, total_space, space_available)
                VALUES (user_rec.user_id, v_provider, v_email, v_access_token, v_refresh_token,
                       NOW() + INTERVAL '30 days', v_total_space, v_space_available);
            END IF;
        END LOOP;
    END LOOP;
    
    RAISE NOTICE 'Inserarea conturilor cloud... GATA!';
    
    RAISE NOTICE 'Inserarea fișierelor...';
    
    FOR account_rec IN (SELECT account_id FROM cloud_accounts) LOOP
        v_temp := FLOOR(RANDOM() * 46) + 5; 
        
        FOR v_j IN 1..v_temp LOOP
            v_file_name := file_names[FLOOR(RANDOM() * array_length(file_names, 1)) + 1];
            v_file_name := v_file_name || '_' || FLOOR(RANDOM() * 100);
            
            v_temp1 := FLOOR(RANDOM() * array_length(file_types, 1)) + 1;
            v_file_type := file_types[v_temp1];
            v_file_name := v_file_name || file_extensions[v_temp1];
            
            IF LENGTH(v_file_name) > 51 THEN
                v_file_name := SUBSTRING(v_file_name FROM 1 FOR 47) || file_extensions[v_temp1];
            END IF;
            
            CASE 
                WHEN v_file_type LIKE 'image/%' THEN 
                    v_file_size := FLOOR(RANDOM() * 10 * 1024 * 1024) + 100 * 1024; 
                WHEN v_file_type LIKE 'video/%' THEN 
                    v_file_size := FLOOR(RANDOM() * 1024 * 1024 * 1024) + 10 * 1024 * 1024; 
                WHEN v_file_type LIKE 'audio/%' THEN 
                    v_file_size := FLOOR(RANDOM() * 50 * 1024 * 1024) + 1024 * 1024; 
                WHEN v_file_type = 'application/pdf' THEN 
                    v_file_size := FLOOR(RANDOM() * 20 * 1024 * 1024) + 50 * 1024;
                ELSE 
                    v_file_size := FLOOR(RANDOM() * 5 * 1024 * 1024) + 1024;
            END CASE;

			SELECT user_id INTO v_variabila FROM cloud_accounts ca WHERE ca.account_id = account_rec.account_id;
            INSERT INTO files (account_id, file_name, file_size, uploaded_at, type, user_id)
            VALUES (account_rec.account_id, v_file_name, v_file_size, 
                   NOW() - (RANDOM() * INTERVAL '180 days'), v_file_type, v_variabila);
        END LOOP;
    END LOOP;
    
    RAISE NOTICE 'Inserarea fișierelor... GATA!';
    
    RAISE NOTICE 'Inserarea chunk-urilor de fișiere...';
    
    FOR file_rec IN (SELECT file_id, account_id, file_size FROM files WHERE file_size > 10 * 1024 * 1024) LOOP
        v_nr_chunks := CEIL(file_rec.file_size::NUMERIC / (10 * 1024 * 1024));
        
        FOR v_j IN 1..v_nr_chunks LOOP
            IF v_j = v_nr_chunks THEN
                v_chunk_size := file_rec.file_size - ((v_j - 1) * 10 * 1024 * 1024);
            ELSE
                v_chunk_size := 10 * 1024 * 1024;
            END IF;
            
            v_chunk_file_id := 'chunk_' || file_rec.file_id || '_' || v_j || '_' || MD5(RANDOM()::TEXT);
            
            INSERT INTO file_chunks (file_id, account_id, chunk_index, chunk_size, nr_of_chunks, chunk_file_id)
            VALUES (file_rec.file_id, file_rec.account_id, v_j - 1, v_chunk_size, v_nr_chunks, v_chunk_file_id);
        END LOOP;
    END LOOP;
    
    RAISE NOTICE 'Inserarea chunk-urilor de fișiere... GATA!';
    
END $$;

SELECT COUNT(*) || ' utilizatori inserați' FROM users;
SELECT COUNT(*) || ' conturi cloud inserate' FROM cloud_accounts;
SELECT COUNT(*) || ' fișiere inserate' FROM files;
SELECT COUNT(*) || ' chunk-uri de fișiere inserate' FROM file_chunks;
