CREATE OR REPLACE FUNCTION public.distribute_file_chunks()
    RETURNS trigger
    LANGUAGE plpgsql
AS $function$
DECLARE
    v_remaining_size bigint;
    v_chunk_size bigint;
    v_chunk_index integer;
    v_account_record record;
    v_box_max_chunk_size bigint := 250 * 1024 * 1024; -- 250MB in bytes
    v_user_id bigint;
    v_total_space_available bigint;
BEGIN
    -- Get the user_id from the account that owns this file
    SELECT ca.user_id INTO v_user_id
    FROM cloud_accounts ca
    WHERE ca.account_id = NEW.account_id;

    -- Initialize remaining size
    v_remaining_size := NEW.file_size;
    v_chunk_index := 1;

    -- Calculate total available space across all accounts
    SELECT SUM(space_available) INTO v_total_space_available
    FROM cloud_accounts
    WHERE user_id = v_user_id;

    -- Check if we have enough total space
    IF v_total_space_available < v_remaining_size THEN
        RAISE EXCEPTION 'Not enough total space available across all accounts to store the file';
    END IF;

    -- First try: Look for Google Drive or Dropbox accounts that can store the whole file
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
            -- Insert single chunk
            INSERT INTO file_chunks (
                file_id, account_id, chunk_index, 
                chunk_size, nr_of_chunks, chunk_file_id
            ) VALUES (
                NEW.file_id, v_account_record.account_id, 1,
                v_remaining_size, 1, 'pending'
            );

            -- Update available space
            UPDATE cloud_accounts 
            SET space_available = space_available - v_remaining_size
            WHERE account_id = v_account_record.account_id;

            v_remaining_size := 0;
            EXIT;
        END LOOP;
    END IF;

    -- If file still needs to be stored, distribute it in chunks
    IF v_remaining_size > 0 THEN
        -- Distribute chunks across accounts
        WHILE v_remaining_size > 0 LOOP
            -- Find best account for next chunk
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
                -- Prioritize accounts that can store larger chunks
                CASE 
                    WHEN ca.provider = 'box' THEN 
                        LEAST(v_remaining_size, v_box_max_chunk_size, ca.space_available)
                    ELSE 
                        LEAST(v_remaining_size, ca.space_available)
                END DESC,
                -- Prefer Google Drive, then Dropbox, then Box
                CASE 
                    WHEN ca.provider = 'google' THEN 1
                    WHEN ca.provider = 'dropbox' THEN 2
                    WHEN ca.provider = 'box' THEN 3
                END,
                -- For equal providers, prefer accounts with more space
                space_available DESC
            LIMIT 1;

            -- If no suitable account found, raise error
            IF v_account_record IS NULL THEN
                RAISE EXCEPTION 'Could not find suitable account for remaining chunks (% bytes left)', v_remaining_size;
            END IF;

            -- Calculate chunk size for this account
            v_chunk_size := v_account_record.chunk_size;

            -- Insert chunk record
            INSERT INTO file_chunks (
                file_id, account_id, chunk_index, 
                chunk_size, nr_of_chunks, chunk_file_id
            ) VALUES (
                NEW.file_id, v_account_record.account_id, v_chunk_index,
                v_chunk_size, v_chunk_index, 'pending'  -- Use v_chunk_index for nr_of_chunks
            );

            -- Update available space
            UPDATE cloud_accounts 
            SET space_available = space_available - v_chunk_size
            WHERE account_id = v_account_record.account_id;

            -- Update remaining size and chunk index
            v_remaining_size := v_remaining_size - v_chunk_size;
            v_chunk_index := v_chunk_index + 1;
        END LOOP;

        -- Update all chunks with the final total number of chunks
        UPDATE file_chunks
        SET nr_of_chunks = v_chunk_index - 1
        WHERE file_id = NEW.file_id;
    END IF;

    RETURN NEW;
END;
$function$;

-- Create or replace the trigger
DROP TRIGGER IF EXISTS trg_distribute_file_chunks ON public.files;
CREATE TRIGGER trg_distribute_file_chunks
    BEFORE INSERT OR UPDATE 
    ON public.files
    FOR EACH ROW
    EXECUTE FUNCTION public.distribute_file_chunks(); 