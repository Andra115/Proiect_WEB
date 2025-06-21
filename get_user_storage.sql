CREATE OR REPLACE FUNCTION public.get_user_storage(
	p_user_id bigint)
    RETURNS TABLE(total_storage bigint, total_available bigint) 
    LANGUAGE plpgsql
    COST 100
    VOLATILE PARALLEL UNSAFE
    ROWS 1000

AS $BODY$
BEGIN
    RETURN QUERY
    SELECT 
        COALESCE(SUM(ca.total_space), 0) as total_storage,
        COALESCE(SUM(ca.space_available), 0) as total_available
    FROM cloud_accounts ca
    WHERE ca.user_id = p_user_id;
END;
$BODY$;

ALTER FUNCTION public.get_user_storage(p_user_id bigint)
    OWNER TO postgres; 