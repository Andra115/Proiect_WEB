CREATE SEQUENCE IF NOT EXISTS users_user_id_seq;
CREATE SEQUENCE IF NOT EXISTS cloud_accounts_account_id_seq;
CREATE SEQUENCE IF NOT EXISTS files_file_id_seq;
CREATE SEQUENCE IF NOT EXISTS file_chunks_chunk_id_seq;

CREATE TABLE IF NOT EXISTS public.users (
    user_id bigint NOT NULL DEFAULT nextval('users_user_id_seq'::regclass),
    username character varying(255) COLLATE pg_catalog."default",
    email character varying(255) COLLATE pg_catalog."default" NOT NULL,
    password character(60) COLLATE pg_catalog."default" NOT NULL,
    created_at timestamp without time zone NOT NULL,
    CONSTRAINT users_pkey PRIMARY KEY (user_id),
    CONSTRAINT users_email_key UNIQUE (email)
) TABLESPACE pg_default;

ALTER TABLE public.users OWNER TO mariusss;

CREATE TABLE IF NOT EXISTS public.cloud_accounts (
    account_id bigint NOT NULL DEFAULT nextval('cloud_accounts_account_id_seq'::regclass),
    user_id bigint NOT NULL,
    provider character varying(255) COLLATE pg_catalog."default" NOT NULL,
    email character varying(255) COLLATE pg_catalog."default" NOT NULL,
    access_token text COLLATE pg_catalog."default" NOT NULL,
    refresh_token text COLLATE pg_catalog."default",
    token_expiry timestamp without time zone,
    total_space bigint NOT NULL,
    space_available bigint NOT NULL,
    CONSTRAINT cloud_accounts_pkey PRIMARY KEY (account_id),
    CONSTRAINT cloud_accounts_email_provider_key UNIQUE (email, provider, user_id),
    CONSTRAINT cloud_accounts_user_id_fkey FOREIGN KEY (user_id)
        REFERENCES public.users (user_id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
) TABLESPACE pg_default;

ALTER TABLE public.cloud_accounts OWNER TO mariusss;

CREATE TABLE IF NOT EXISTS public.files (
    file_id bigint NOT NULL DEFAULT nextval('files_file_id_seq'::regclass),
    account_id bigint,
    file_name character varying(255) COLLATE pg_catalog."default" NOT NULL,
    file_size bigint NOT NULL,
    uploaded_at timestamp without time zone NOT NULL,
    type character(51) COLLATE pg_catalog."default" NOT NULL,
    CONSTRAINT files_pkey PRIMARY KEY (file_id),
    CONSTRAINT files_account_id_fkey FOREIGN KEY (account_id)
        REFERENCES public.cloud_accounts (account_id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
) TABLESPACE pg_default;

ALTER TABLE public.files OWNER TO mariusss;


CREATE TABLE IF NOT EXISTS public.file_chunks (
    chunk_id bigint NOT NULL DEFAULT nextval('file_chunks_chunk_id_seq'::regclass),
    file_id bigint NOT NULL,
    account_id bigint NOT NULL,
    chunk_index integer NOT NULL,
    chunk_size bigint NOT NULL,
    nr_of_chunks integer NOT NULL,
    chunk_file_id character varying(255) COLLATE pg_catalog."default" NOT NULL,
    CONSTRAINT file_chunks_pkey PRIMARY KEY (chunk_id),
    CONSTRAINT file_chunks_account_id_fkey FOREIGN KEY (account_id)
        REFERENCES public.cloud_accounts (account_id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
) TABLESPACE pg_default;

ALTER TABLE public.file_chunks OWNER TO mariusss;


CREATE OR REPLACE FUNCTION public.changepassword(
	userid bigint,
	oldpassword text,
	newpassword text)
    RETURNS void
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
AS $BODY$
BEGIN
    IF (SELECT password FROM public.users WHERE user_id = changePassword.userId) <> changePassword.oldPassword THEN
        RAISE EXCEPTION 'Old password is incorrect.' USING ERRCODE = 'P0009';
    END IF;

    IF changePassword.oldPassword == changePassword.newPassword THEN
        RAISE EXCEPTION 'New password must be different from the old one.' USING ERRCODE = 'P0010';
    END IF;

    IF LENGTH(changePassword.newPassword) < 8 THEN
        RAISE EXCEPTION 'Password must be at least 8 characters long.' USING ERRCODE = 'P0011';
    END IF;

    UPDATE public.users SET password = changePassword.newPassword WHERE user_id = changePassword.userId;
END;
$BODY$;

ALTER FUNCTION public.changepassword(userid bigint, oldpassword text, newpassword text)
    OWNER TO mariusss;


CREATE OR REPLACE FUNCTION public.changeusername(
	userid bigint,
	newusername text)
    RETURNS void
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
AS $BODY$
BEGIN
    IF LENGTH(newUsername) < 3 THEN
        RAISE EXCEPTION 'Username must be at least 3 characters long' USING ERRCODE = 'P0008';
    END IF;
    
    UPDATE public.users SET username = newUsername WHERE user_id = userId;
END;
$BODY$;

ALTER FUNCTION public.changeusername(userid bigint, newusername text)
    OWNER TO mariusss;



CREATE OR REPLACE FUNCTION public.checklogin(
	email text,
	password text)
    RETURNS boolean
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
AS $BODY$
DECLARE
    stored_password TEXT;
BEGIN
    SELECT users.password
    INTO stored_password
    FROM users
    WHERE users.email = checkLogin.email;

    IF stored_password IS NULL THEN
        RAISE EXCEPTION 'There is no account using this email address.' USING ERRCODE = 'P0001';
    END IF;

    IF stored_password != crypt(password, stored_password) THEN
        RAISE EXCEPTION 'Incorrect password.' USING ERRCODE = 'P0002';
    END IF;

    RETURN TRUE;
END;
$BODY$;

ALTER FUNCTION public.checklogin(email text, password text)
    OWNER TO mariusss;
    
    
CREATE OR REPLACE FUNCTION public.createuser(
	email text,
	username text,
	password text,
	passwordagain text)
    RETURNS void
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
AS $BODY$
BEGIN
    IF EXISTS (SELECT 1 FROM users WHERE users.email = createUser.email) THEN
        RAISE EXCEPTION 'An account with this email address already exists.' USING ERRCODE = 'P0003';
    END IF;

    IF NOT createUser.username ~* '^[A-Z0-9._-]+$' THEN
        RAISE EXCEPTION 'Username can only contain letters, numbers, underscores, and hyphens.' USING ERRCODE = 'P0004';
    END IF;
    
    IF LENGTH(createUser.username) < 3 THEN
        RAISE EXCEPTION 'Username must be at least 3 characters long.' USING ERRCODE = 'P0005';
    END IF;

    IF LENGTH(createUser.password) < 8 THEN
        RAISE EXCEPTION 'Password must be at least 8 characters long.' USING ERRCODE = 'P0006';
    END IF;

    IF createUser.password <> createUser.passwordAgain THEN
        RAISE EXCEPTION 'Passwords do not match.' USING ERRCODE = 'P0007';
    END IF;

    INSERT INTO users (username, email, password, created_at)
    VALUES (username, email, crypt(password, gen_salt('bf')), 
            NOW());
END;
$BODY$;

ALTER FUNCTION public.createuser(email text, username text, password text, passwordagain text)
    OWNER TO mariusss;


CREATE OR REPLACE FUNCTION public.get_user_files(
	p_user_id bigint)
    RETURNS TABLE(file_id bigint, file_name character varying, uploaded_at timestamp without time zone, file_size bigint, file_type character) 
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
    ROWS 1000

AS $BODY$
DECLARE
    file_cursor CURSOR FOR
        SELECT f.file_id, f.file_name, f.uploaded_at, f.file_size, f.type
        FROM public.files f
        JOIN public.cloud_accounts ca ON f.account_id = ca.account_id
        WHERE ca.user_id = p_user_id
        ORDER BY f.uploaded_at DESC;

    file_record RECORD;
BEGIN
    OPEN file_cursor;

    LOOP
        FETCH file_cursor INTO file_record;
        EXIT WHEN NOT FOUND;

        file_id := file_record.file_id;
        file_name := file_record.file_name;
        uploaded_at := file_record.uploaded_at;
        file_size := file_record.file_size;
        file_type := file_record.type;

        RETURN NEXT;
    END LOOP;

    CLOSE file_cursor;
END;
$BODY$;

ALTER FUNCTION public.get_user_files(p_user_id bigint)
    OWNER TO mariusss;


CREATE OR REPLACE FUNCTION public.get_user_info(
	p_user_id integer)
    RETURNS TABLE(username character varying, email character varying) 
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
    ROWS 1000

AS $BODY$
BEGIN
    RETURN QUERY
    SELECT u.username, u.email
    FROM users u
    WHERE u.user_id = p_user_id;
END;
$BODY$;

ALTER FUNCTION public.get_user_info(p_user_id integer)
    OWNER TO mariusss;



CREATE FUNCTION public.infer_file_type()
    RETURNS trigger
    LANGUAGE plpgsql
    COST 100
    VOLATILE NOT LEAKPROOF
AS $BODY$
DECLARE
    file_ext TEXT;
BEGIN
    file_ext := lower(split_part(NEW.file_name, '.', array_length(string_to_array(NEW.file_name, '.'), 1)));

    IF file_ext IN ('jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg') THEN
        NEW.type := 'image';
    ELSIF file_ext = 'pdf' THEN
        NEW.type := 'pdf';
    ELSIF file_ext IN ('doc', 'docx', 'txt', 'odt') THEN
        NEW.type := 'document';
    ELSIF file_ext IN ('xls', 'xlsx', 'ods') THEN
        NEW.type := 'spreadsheet';
    ELSIF file_ext IN ('ppt', 'pptx', 'odp') THEN
        NEW.type := 'presentation';
    ELSIF file_ext IN ('mp3', 'wav', 'flac', 'aac') THEN
        NEW.type := 'audio';
    ELSIF file_ext IN ('mp4', 'avi', 'mkv', 'mov') THEN
        NEW.type := 'video';
    ELSIF file_ext IN ('zip', 'rar', 'tar', '7z') THEN
        NEW.type := 'archive';
    ELSE
        NEW.type := 'other';
    END IF;

    RETURN NEW;
END;
$BODY$;

ALTER FUNCTION public.infer_file_type()
    OWNER TO mariusss;
    
    
CREATE OR REPLACE TRIGGER trg_infer_file_type
    BEFORE INSERT OR UPDATE 
    ON public.files
    FOR EACH ROW
    EXECUTE FUNCTION public.infer_file_type();
CREATE OR REPLACE FUNCTION public.distribute_file_chunks()
    RETURNS trigger
    LANGUAGE plpgsql
AS $function$
DECLARE
    v_remaining_size bigint;
    v_chunk_size bigint;
    v_chunk_index integer;
    v_account_record record;
    v_box_max_chunk_size bigint := 250 * 1024 * 1024;
    v_user_id bigint;
    v_total_space_available bigint;
BEGIN
    v_user_id := NEW.user_id;

    v_remaining_size := NEW.file_size;
    v_chunk_index := 1;

    SELECT SUM(space_available) INTO v_total_space_available
    FROM cloud_accounts
    WHERE user_id = v_user_id;

    IF v_total_space_available < v_remaining_size THEN
        RAISE EXCEPTION 'Not enough total space available across all accounts to store the file';
    END IF;

    IF v_remaining_size <= v_total_space_available THEN
        FOR v_account_record IN 
            SELECT account_id, space_available, provider
            FROM cloud_accounts
            WHERE user_id = v_user_id
            AND provider IN ('google', 'dropbox')
            AND space_available >= v_remaining_size
            ORDER BY 
                CASE 
                    WHEN provider = 'google' THEN 1
                    WHEN provider = 'dropbox' THEN 2
                END,
                space_available DESC
        LOOP
            INSERT INTO file_chunks (
                file_id, account_id, chunk_index, 
                chunk_size, nr_of_chunks, chunk_file_id
            ) VALUES (
                NEW.file_id, v_account_record.account_id, 1,
                v_remaining_size, 1, 'pending'
            );

            UPDATE cloud_accounts 
            SET space_available = space_available - v_remaining_size
            WHERE account_id = v_account_record.account_id;

            v_remaining_size := 0;
            EXIT;
        END LOOP;
    END IF;

    IF v_remaining_size > 0 THEN
        WHILE v_remaining_size > 0 LOOP
            SELECT 
                ca.account_id,
                ca.provider,
                ca.space_available,
                CASE 
                    WHEN ca.provider = 'box' THEN 
                        LEAST(
                            v_remaining_size, 
                            v_box_max_chunk_size,
                            ca.space_available
                        )
                    ELSE 
                        LEAST(v_remaining_size, ca.space_available)
                END as chunk_size
            INTO v_account_record
            FROM cloud_accounts ca
            WHERE ca.user_id = v_user_id
            AND ca.space_available > 0
            AND (
                ca.provider != 'box' 
                OR ca.space_available >= LEAST(v_box_max_chunk_size, v_remaining_size)
            )
            ORDER BY 
                CASE 
                    WHEN ca.provider = 'box' THEN 
                        LEAST(v_remaining_size, v_box_max_chunk_size, ca.space_available)
                    ELSE 
                        LEAST(v_remaining_size, ca.space_available)
                END DESC,
                CASE 
                    WHEN ca.provider = 'google' THEN 1
                    WHEN ca.provider = 'dropbox' THEN 2
                    WHEN ca.provider = 'box' THEN 3
                END,
                space_available DESC
            LIMIT 1;

            IF v_account_record IS NULL THEN
                RAISE EXCEPTION 'Could not find suitable account for remaining chunks (% bytes left)', v_remaining_size;
            END IF;

            v_chunk_size := v_account_record.chunk_size;

            INSERT INTO file_chunks (
                file_id, account_id, chunk_index, 
                chunk_size, nr_of_chunks, chunk_file_id
            ) VALUES (
                NEW.file_id, v_account_record.account_id, v_chunk_index,
                v_chunk_size, v_chunk_index, 'pending' 
            );

            UPDATE cloud_accounts 
            SET space_available = space_available - v_chunk_size
            WHERE account_id = v_account_record.account_id;

            v_remaining_size := v_remaining_size - v_chunk_size;
            v_chunk_index := v_chunk_index + 1;
        END LOOP;

        UPDATE file_chunks
        SET nr_of_chunks = v_chunk_index - 1
        WHERE file_id = NEW.file_id;
    END IF;

    RETURN NEW;
END;
$function$;

DROP TRIGGER IF EXISTS trg_distribute_file_chunks ON public.files;
CREATE TRIGGER trg_distribute_file_chunks
    BEFORE INSERT OR UPDATE 
    ON public.files
    FOR EACH ROW
    EXECUTE FUNCTION public.distribute_file_chunks(); 
