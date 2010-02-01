--
-- Name: gains; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--
CREATE TABLE gains (
    date date NOT NULL,
    symb character varying(10) NOT NULL,
    exch character varying(6) NOT NULL,
    d_10 date,
    c_10 numeric(9,2),
    gain_10 numeric(9,2),
    d_20 date,
    c_20 numeric(9,2),
    gain_20 numeric(9,2),
    d_30 date,
    c_30 numeric(9,2),
    gain_30 numeric(9,2),
    d_50 date,
    c_50 numeric(9,2),
    gain_50 numeric(9,2),
    d_100 date,
    c_100 numeric(9,2),
    gain_100 numeric(9,2),
    d_200 date,
    c_200 numeric(9,2),
    gain_200 numeric(9,2)
);
ALTER TABLE public.gains OWNER TO postgres;
ALTER TABLE ONLY gains ADD CONSTRAINT gains_pkey PRIMARY KEY (date, symb, exch);
COMMENT ON COLUMN gains.date IS 'Date this row refers to.';
COMMENT ON COLUMN gains.symb IS 'symbol of the commodity being traded';
COMMENT ON COLUMN gains.exch IS 'Exchange code';
