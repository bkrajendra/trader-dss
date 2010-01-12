--
-- Name: update_gains(date, character varying, character varying, numeric); Type: FUNCTION; Schema: public; Owner: postgres
--
CREATE FUNCTION update_gains(new_date date, new_symb character varying, new_exch character varying, new_close numeric) RETURNS void
    LANGUAGE plpgsql
    AS $$
    DECLARE
        gain10 RECORD;
        gain20 RECORD;
        gain30 RECORD;
        gain50 RECORD;
        gain100 RECORD;
        gain200 RECORD;
    BEGIN
    -- Work out the gains over 10,20,30,50,100,200 days
    -- Method: We need to find the record that is 10,20,30,50,100,200 days ago so we
    --         1. select the top n records ordered by date,
    --         2. switch the order
    --         3. take the top record. It must be the one we want.
    -- We ignore the fact that there might be less than N records since if there are then what we got is the nearest that we have
    --    and will do. This is in keeping with the method used for averages etc.
    --
    -- NOTE: Work from largest to smallest date range to get the disk cache populated.
    --
    -- start with the 200 select. If we don't get any records back then the this will be the first record for this symbol
    select topN.date, topN.symb, topN.exch, topN.close into gain200 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 200) as topN order by date asc limit 1;
    IF NOT FOUND THEN
	-- no records found, must be the first record. Populate it with the symbol details and zeros and don't bother with the 20,30 etc.
	insert into gains (date, symb, exch, gain_10, gain_20, gain_30, gain_50, gain_100, gain_200) values (new_date, new_symb, new_exch, 0, 0, 0, 0, 0, 0);
    ELSE
	-- gain200
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain200.date;
	update gains set d_200 = new_date, c_200 = new_close, gain_200 = new_close - gain200.close where symb = new_symb and exch = new_exch and date = gain200.date;
	IF NOT FOUND THEN
	    insert into gains (date, symb, exch) values (gain200.date, new_symb, new_exch);
	END IF;
	-- update gains set d_200 = new_date, c_200 = new_close, gain_200 = new_close - gain200.close where symb = new_symb and exch = new_exch and date = gain200.date;
	-- gain100
	select topN.date, topN.symb, topN.exch, topN.close into gain100 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 100) as topN order by date asc limit 1;
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain100.date;
	update gains set d_100 = new_date, c_100 = new_close, gain_100 = new_close - gain100.close where symb = new_symb and exch = new_exch and date = gain100.date;
	IF NOT FOUND THEN
	    insert into gains ( date, symb, exch) values (gain100.date, new_symb, new_exch);
	END IF;
	-- update gains set d_100 = new_date, c_100 = new_close, gain_100 = new_close - gain100.close where symb = new_symb and exch = new_exch and date = gain100.date;
	-- gain50
	select topN.date, topN.symb, topN.exch, topN.close into gain50 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 50) as topN order by date asc limit 1;
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain50.date;
	update gains set d_50 = new_date, c_50 = new_close, gain_50 = new_close - gain50.close where symb = new_symb and exch = new_exch and date = gain50.date;
	IF NOT FOUND THEN
	    insert into gains (date, symb, exch) values (gain50.date, new_symb, new_exch);
	END IF;
	-- update gains set d_50 = new_date, c_50 = new_close, gain_50 = new_close - gain50.close where symb = new_symb and exch = new_exch and date = gain50.date;
	-- gain30
	select topN.date, topN.symb, topN.exch, topN.close into gain30 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 30) as topN order by date asc limit 1;
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain30.date;
	update gains set d_30 = new_date, c_30 = new_close, gain_30 = new_close - gain30.close where symb = new_symb and exch = new_exch and date = gain30.date;
	IF NOT FOUND THEN
	    insert into gains (date, symb, exch) values (gain30.date, new_symb, new_exch);
	END IF;
	-- update gains set d_30 = new_date, c_30 = new_close, gain_30 = new_close - gain30.close where symb = new_symb and exch = new_exch and date = gain30.date;
	-- gain20
	select topN.date, topN.symb, topN.exch, topN.close into gain20 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 20) as topN order by date asc limit 1;
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain20.date;
	update gains set d_20 = new_date, c_20 = new_close, gain_20 = new_close - gain20.close where symb = new_symb and exch = new_exch and date = gain20.date;
	IF NOT FOUND THEN
	    insert into gains (date, symb, exch) values (gain20.date, new_symb, new_exch);
	END IF;
	-- update gains set d_20 = new_date, c_20 = new_close, gain_20 = new_close - gain20.close where symb = new_symb and exch = new_exch and date = gain20.date;
	-- gain10
	select topN.date, topN.symb, topN.exch, topN.close into gain10 from (select date, symb, exch, close from quotes where symb = new_symb and exch = new_exch and date < new_date order by date desc limit 10) as topN order by date asc limit 1;
	-- PERFORM * from gains where symb = new_symb and exch = new_exch and date = gain10.date;
	update gains set d_10 = new_date, c_10 = new_close, gain_10 = new_close - gain10.close where symb = new_symb and exch = new_exch and date = gain10.date;
	IF NOT FOUND THEN
	    insert into gains (date, symb, exch) values (gain10.date, new_symb, new_exch);
	END IF;
	-- update gains set d_10 = new_date, c_10 = new_close, gain_10 = new_close - gain10.close where symb = new_symb and exch = new_exch and date = gain10.date;
    END IF;
    -- Work out the max/min over the same periods
    -- Work out the trading range over the same periods
END
$$;
ALTER FUNCTION public.update_gains(new_date date, new_symb character varying, new_exch character varying, new_close numeric) OWNER TO postgres;
