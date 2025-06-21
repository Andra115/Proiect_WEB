CREATE OR REPLACE FUNCTION public.get_user_files(
	p_user_id bigint,
	p_type text,
	p_searched text)
    RETURNS TABLE(file_id bigint, file_name text, uploaded_at timestamp without time zone, file_size bigint, file_type text) 
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
    ROWS 1000

AS $BODY$
DECLARE
    file_cursor REFCURSOR;
    file_record RECORD;
BEGIN
    IF p_type = '' THEN
        IF p_searched = '' THEN
            OPEN file_cursor FOR
                SELECT DISTINCT f.file_id, f.file_name, f.uploaded_at, f.file_size, f.type
                FROM public.files f
                JOIN public.file_chunks fc ON f.file_id = fc.file_id
                JOIN public.cloud_accounts ca ON fc.account_id = ca.account_id
                WHERE ca.user_id = p_user_id
                ORDER BY f.uploaded_at DESC;
        ELSE
            OPEN file_cursor FOR
                SELECT DISTINCT f.file_id, f.file_name, f.uploaded_at, f.file_size, f.type
                FROM public.files f
                JOIN public.file_chunks fc ON f.file_id = fc.file_id
                JOIN public.cloud_accounts ca ON fc.account_id = ca.account_id
                WHERE ca.user_id = p_user_id AND f.file_name ILIKE '%' || p_searched || '%'
                ORDER BY f.uploaded_at DESC;
        END IF;
    ELSE
        IF p_searched = '' THEN
            OPEN file_cursor FOR
                SELECT DISTINCT f.file_id, f.file_name, f.uploaded_at, f.file_size, f.type
                FROM public.files f
                JOIN public.file_chunks fc ON f.file_id = fc.file_id
                JOIN public.cloud_accounts ca ON fc.account_id = ca.account_id
                WHERE ca.user_id = p_user_id AND f.type = p_type
                ORDER BY f.uploaded_at DESC;
        ELSE
            OPEN file_cursor FOR
                SELECT DISTINCT f.file_id, f.file_name, f.uploaded_at, f.file_size, f.type
                FROM public.files f
                JOIN public.file_chunks fc ON f.file_id = fc.file_id
                JOIN public.cloud_accounts ca ON fc.account_id = ca.account_id
                WHERE ca.user_id = p_user_id AND f.type = p_type AND f.file_name ILIKE '%' || p_searched || '%'
                ORDER BY f.uploaded_at DESC;
        END IF;
    END IF;

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

ALTER FUNCTION public.get_user_files(p_user_id bigint, p_type text, p_searched text)
    OWNER TO postgres; 