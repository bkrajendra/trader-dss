--
-- Name: indicators; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE indicators
(
 date date NOT NULL, -- The date of the data
 symb character varying(10) NOT NULL, -- The symbol code used by Finance::Quote
 exch character varying(6) NOT NULL, -- The Exchange code
 wpr_10 numeric(9,2), -- Williams %R calculated over 10 days.
 wpr_20 numeric(9,2), -- Williams %R calculated over 20 days.
 wpr_30 numeric(9,2), -- Williams %R calculated over 30 days.
 wpr_50 numeric(9,2), -- Williams %R calculated over 50 days.
 wpr_100 numeric(9,2), -- Williams %R calculated over 100 days.
 wpr_200 numeric(9,2), -- Williams %R calculated over 200 days.
 mapr_10 numeric(9,2),
 mapr_20 numeric(9,2),
 mapr_30 numeric(9,2),
 mapr_50 numeric(9,2),
 mapr_100 numeric(9,2),
 mapr_200 numeric(9,2)
)
WITHOUT OIDS;
ALTER TABLE indicators OWNER TO postgres;
ALTER TABLE public.indicators OWNER TO postgres;
ALTER TABLE ONLY indicators ADD CONSTRAINT indicators_pkey PRIMARY KEY (date, symb, exch);
COMMENT ON COLUMN indicators.date IS 'The date of the data';
COMMENT ON COLUMN indicators.symb IS 'The symbol code used by Finance::Quote';
COMMENT ON COLUMN indicators.exch IS 'The Exchange code';
COMMENT ON COLUMN indicators.wpr_10 IS 'Williams %R calculated over 10 days.';
COMMENT ON COLUMN indicators.wpr_20 IS 'Williams %R calculated over 20 days.';
COMMENT ON COLUMN indicators.wpr_30 IS 'Williams %R calculated over 30 days.';
COMMENT ON COLUMN indicators.wpr_50 IS 'Williams %R calculated over 50 days.';
COMMENT ON COLUMN indicators.wpr_100 IS 'Williams %R calculated over 100 days.';
COMMENT ON COLUMN indicators.wpr_200 IS 'Williams %R calculated over 200 days.';
