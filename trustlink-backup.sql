--
-- PostgreSQL database dump
--

\restrict yUsvJecyFZegELtNgJsr0sC9mEgal70kc1f8LKAnQy4trzdbFcFENnqu59bB1Vv

-- Dumped from database version 18.3
-- Dumped by pg_dump version 18.3

-- Started on 2026-04-04 17:59:23

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 894 (class 1247 OID 16824)
-- Name: notification_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.notification_type AS ENUM (
    'order_placed',
    'order_accepted',
    'order_completed',
    'order_cancelled',
    'review_received'
);


ALTER TYPE public.notification_type OWNER TO postgres;

--
-- TOC entry 882 (class 1247 OID 16789)
-- Name: order_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.order_status AS ENUM (
    'pending',
    'accepted',
    'completed',
    'cancelled'
);


ALTER TYPE public.order_status OWNER TO postgres;

--
-- TOC entry 885 (class 1247 OID 16798)
-- Name: payment_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.payment_status AS ENUM (
    'pending',
    'paid',
    'failed'
);


ALTER TYPE public.payment_status OWNER TO postgres;

--
-- TOC entry 897 (class 1247 OID 16836)
-- Name: product_category; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.product_category AS ENUM (
    'vegetables',
    'fruits',
    'dairy',
    'grains',
    'poultry',
    'other'
);


ALTER TYPE public.product_category OWNER TO postgres;

--
-- TOC entry 5240 (class 0 OID 0)
-- Dependencies: 897
-- Name: TYPE product_category; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TYPE public.product_category IS 'Product categories: vegetables, fruits, dairy, grains, poultry, other';


--
-- TOC entry 888 (class 1247 OID 16806)
-- Name: user_role; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.user_role AS ENUM (
    'buyer',
    'farmer',
    'service_provider',
    'admin'
);


ALTER TYPE public.user_role OWNER TO postgres;

--
-- TOC entry 891 (class 1247 OID 16816)
-- Name: verification_tier; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.verification_tier AS ENUM (
    'basic',
    'trusted',
    'premium'
);


ALTER TYPE public.verification_tier OWNER TO postgres;

--
-- TOC entry 247 (class 1255 OID 17178)
-- Name: generate_order_code(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.generate_order_code() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    year_part VARCHAR(4);
    sequence_part VARCHAR(6);
BEGIN
    year_part := to_char(CURRENT_DATE, 'YYYY');
    
    -- Get next sequence value for this year
    SELECT nextval('order_code_seq_' || year_part) INTO sequence_part;
    
    -- Format: TRUST-YYYY-XXXXXX (e.g., TRUST-2026-000001)
    NEW.order_code := 'TRUST-' || year_part || '-' || LPAD(sequence_part, 6, '0');
    
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.generate_order_code() OWNER TO postgres;

--
-- TOC entry 248 (class 1255 OID 17180)
-- Name: generate_order_code_simple(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.generate_order_code_simple() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.order_code := 'TRUST-' || to_char(CURRENT_DATE, 'YYYY') || '-' || LPAD(nextval('order_code_sequence')::text, 6, '0');
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.generate_order_code_simple() OWNER TO postgres;

--
-- TOC entry 5241 (class 0 OID 0)
-- Dependencies: 248
-- Name: FUNCTION generate_order_code_simple(); Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON FUNCTION public.generate_order_code_simple() IS 'Generates unique order code in format TRUST-YYYY-XXXXXX';


--
-- TOC entry 249 (class 1255 OID 17182)
-- Name: prevent_review_on_incomplete_order(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.prevent_review_on_incomplete_order() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM orders 
        WHERE id = NEW.order_id AND status = 'completed'
    ) THEN
        RAISE EXCEPTION 'Reviews can only be added for completed orders';
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.prevent_review_on_incomplete_order() OWNER TO postgres;

--
-- TOC entry 5242 (class 0 OID 0)
-- Dependencies: 249
-- Name: FUNCTION prevent_review_on_incomplete_order(); Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON FUNCTION public.prevent_review_on_incomplete_order() IS 'Ensures reviews only on completed orders';


--
-- TOC entry 246 (class 1255 OID 17174)
-- Name: update_seller_trust_score(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_seller_trust_score() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    UPDATE users
    SET trust_score = (
        SELECT ROUND(AVG(rating)::numeric, 1)
        FROM reviews
        WHERE seller_id = NEW.seller_id
    )
    WHERE id = NEW.seller_id;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_seller_trust_score() OWNER TO postgres;

--
-- TOC entry 5243 (class 0 OID 0)
-- Dependencies: 246
-- Name: FUNCTION update_seller_trust_score(); Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON FUNCTION public.update_seller_trust_score() IS 'Automatically recalculates seller trust score when reviews change';


--
-- TOC entry 245 (class 1255 OID 17168)
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 224 (class 1259 OID 16925)
-- Name: product_images; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.product_images (
    id integer NOT NULL,
    product_id integer NOT NULL,
    image_url character varying(255) NOT NULL,
    is_primary boolean DEFAULT false,
    display_order smallint DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.product_images OWNER TO postgres;

--
-- TOC entry 5244 (class 0 OID 0)
-- Dependencies: 224
-- Name: TABLE product_images; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.product_images IS 'Separate table for product images - scalable for multiple images';


--
-- TOC entry 222 (class 1259 OID 16886)
-- Name: products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.products (
    id integer NOT NULL,
    farmer_id integer NOT NULL,
    name character varying(100) NOT NULL,
    category public.product_category DEFAULT 'other'::public.product_category,
    description text,
    price numeric(10,2) NOT NULL,
    quantity integer DEFAULT 0 NOT NULL,
    unit character varying(20),
    status boolean DEFAULT true NOT NULL,
    featured boolean DEFAULT false,
    views_count integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT products_price_check CHECK ((price >= (0)::numeric)),
    CONSTRAINT products_quantity_check CHECK ((quantity >= 0)),
    CONSTRAINT products_views_count_check CHECK ((views_count >= 0))
);


ALTER TABLE public.products OWNER TO postgres;

--
-- TOC entry 5245 (class 0 OID 0)
-- Dependencies: 222
-- Name: TABLE products; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.products IS 'Products listed by farmers with stock management';


--
-- TOC entry 220 (class 1259 OID 16850)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    phone character varying(15) NOT NULL,
    email character varying(100),
    password character varying(255) NOT NULL,
    role public.user_role DEFAULT 'buyer'::public.user_role NOT NULL,
    trust_score numeric(2,1) DEFAULT 0,
    verification_tier public.verification_tier DEFAULT 'basic'::public.verification_tier NOT NULL,
    county character varying(50),
    subcounty character varying(50),
    ward character varying(50),
    profile_photo character varying(255),
    status boolean DEFAULT true NOT NULL,
    id_verified boolean DEFAULT false,
    id_verification_doc character varying(255),
    last_login_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    location character varying(255),
    admin_type character varying(20) DEFAULT NULL::character varying,
    must_change_password boolean DEFAULT false,
    reset_token character varying(64) DEFAULT NULL::character varying,
    token_expiry timestamp without time zone,
    CONSTRAINT users_trust_score_check CHECK (((trust_score >= (0)::numeric) AND (trust_score <= (5)::numeric)))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 5246 (class 0 OID 0)
-- Dependencies: 220
-- Name: TABLE users; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.users IS 'All platform users: buyers, farmers, service providers, admins';


--
-- TOC entry 239 (class 1259 OID 17153)
-- Name: active_products; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.active_products AS
 SELECT p.id,
    p.farmer_id,
    p.name,
    p.category,
    p.description,
    p.price,
    p.quantity,
    p.unit,
    p.status,
    p.featured,
    p.views_count,
    p.created_at,
    p.updated_at,
    u.name AS farmer_name,
    u.trust_score AS farmer_trust_score,
    u.verification_tier AS farmer_verification_tier,
    u.county AS farmer_county,
    u.subcounty AS farmer_subcounty,
    u.ward AS farmer_ward,
    pi.image_url AS primary_image
   FROM ((public.products p
     JOIN public.users u ON ((p.farmer_id = u.id)))
     LEFT JOIN public.product_images pi ON (((p.id = pi.product_id) AND (pi.is_primary = true))))
  WHERE ((p.status = true) AND (u.status = true));


ALTER VIEW public.active_products OWNER TO postgres;

--
-- TOC entry 5247 (class 0 OID 0)
-- Dependencies: 239
-- Name: VIEW active_products; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW public.active_products IS 'Active products with seller trust scores and primary image';


--
-- TOC entry 238 (class 1259 OID 17133)
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.activity_logs (
    id integer NOT NULL,
    user_id integer,
    action character varying(100) NOT NULL,
    ip_address inet,
    user_agent text,
    details jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.activity_logs OWNER TO postgres;

--
-- TOC entry 5248 (class 0 OID 0)
-- Dependencies: 238
-- Name: TABLE activity_logs; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.activity_logs IS 'Reserved for admin analytics and auditing';


--
-- TOC entry 237 (class 1259 OID 17132)
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.activity_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_logs_id_seq OWNER TO postgres;

--
-- TOC entry 5249 (class 0 OID 0)
-- Dependencies: 237
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- TOC entry 226 (class 1259 OID 16945)
-- Name: cart_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cart_items (
    id integer NOT NULL,
    user_id integer NOT NULL,
    product_id integer NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cart_items_quantity_check CHECK ((quantity > 0))
);


ALTER TABLE public.cart_items OWNER TO postgres;

--
-- TOC entry 5250 (class 0 OID 0)
-- Dependencies: 226
-- Name: TABLE cart_items; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.cart_items IS 'Shopping cart before checkout';


--
-- TOC entry 225 (class 1259 OID 16944)
-- Name: cart_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cart_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cart_items_id_seq OWNER TO postgres;

--
-- TOC entry 5251 (class 0 OID 0)
-- Dependencies: 225
-- Name: cart_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cart_items_id_seq OWNED BY public.cart_items.id;


--
-- TOC entry 234 (class 1259 OID 17087)
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    user_id integer NOT NULL,
    title character varying(100),
    message text NOT NULL,
    type public.notification_type,
    related_id integer,
    is_read boolean DEFAULT false NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- TOC entry 5252 (class 0 OID 0)
-- Dependencies: 234
-- Name: TABLE notifications; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.notifications IS 'In-app notifications for user events';


--
-- TOC entry 233 (class 1259 OID 17086)
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- TOC entry 5253 (class 0 OID 0)
-- Dependencies: 233
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- TOC entry 242 (class 1259 OID 17179)
-- Name: order_code_sequence; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.order_code_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_code_sequence OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 17018)
-- Name: order_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.order_items (
    id integer NOT NULL,
    order_id integer NOT NULL,
    product_id integer,
    product_name character varying(100) NOT NULL,
    quantity integer NOT NULL,
    price numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT order_items_price_check CHECK ((price >= (0)::numeric)),
    CONSTRAINT order_items_quantity_check CHECK ((quantity > 0)),
    CONSTRAINT order_items_subtotal_check CHECK ((subtotal >= (0)::numeric))
);


ALTER TABLE public.order_items OWNER TO postgres;

--
-- TOC entry 5254 (class 0 OID 0)
-- Dependencies: 230
-- Name: TABLE order_items; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.order_items IS 'Individual items per order (snapshot of product details)';


--
-- TOC entry 229 (class 1259 OID 17017)
-- Name: order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.order_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_items_id_seq OWNER TO postgres;

--
-- TOC entry 5255 (class 0 OID 0)
-- Dependencies: 229
-- Name: order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.order_items_id_seq OWNED BY public.order_items.id;


--
-- TOC entry 228 (class 1259 OID 16974)
-- Name: orders; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orders (
    id integer NOT NULL,
    order_code character varying(20) NOT NULL,
    buyer_id integer NOT NULL,
    farmer_id integer NOT NULL,
    total numeric(10,2) NOT NULL,
    location character varying(255) NOT NULL,
    instructions text,
    status public.order_status DEFAULT 'pending'::public.order_status NOT NULL,
    payment_method character varying(50),
    payment_status public.payment_status DEFAULT 'pending'::public.payment_status NOT NULL,
    delivery_fee numeric(10,2) DEFAULT 0,
    completed_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT orders_check CHECK ((buyer_id <> farmer_id)),
    CONSTRAINT orders_delivery_fee_check CHECK ((delivery_fee >= (0)::numeric)),
    CONSTRAINT orders_total_check CHECK ((total >= (0)::numeric))
);


ALTER TABLE public.orders OWNER TO postgres;

--
-- TOC entry 5256 (class 0 OID 0)
-- Dependencies: 228
-- Name: TABLE orders; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.orders IS 'Main order record with status tracking and self-order prevention';


--
-- TOC entry 240 (class 1259 OID 17158)
-- Name: order_summary; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.order_summary AS
 SELECT o.id,
    o.order_code,
    o.buyer_id,
    o.farmer_id,
    o.total,
    o.location,
    o.instructions,
    o.status,
    o.payment_method,
    o.payment_status,
    o.delivery_fee,
    o.completed_at,
    o.created_at,
    o.updated_at,
    buyer.name AS buyer_name,
    buyer.phone AS buyer_phone,
    farmer.name AS farmer_name,
    farmer.phone AS farmer_phone,
    ( SELECT count(*) AS count
           FROM public.order_items
          WHERE (order_items.order_id = o.id)) AS item_count
   FROM ((public.orders o
     JOIN public.users buyer ON ((o.buyer_id = buyer.id)))
     JOIN public.users farmer ON ((o.farmer_id = farmer.id)));


ALTER VIEW public.order_summary OWNER TO postgres;

--
-- TOC entry 5257 (class 0 OID 0)
-- Dependencies: 240
-- Name: VIEW order_summary; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW public.order_summary IS 'Order summary with buyer and seller details';


--
-- TOC entry 227 (class 1259 OID 16973)
-- Name: orders_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orders_id_seq OWNER TO postgres;

--
-- TOC entry 5258 (class 0 OID 0)
-- Dependencies: 227
-- Name: orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;


--
-- TOC entry 244 (class 1259 OID 17187)
-- Name: password_resets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_resets (
    id integer NOT NULL,
    user_id integer NOT NULL,
    token character varying(64) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.password_resets OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 17186)
-- Name: password_resets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.password_resets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.password_resets_id_seq OWNER TO postgres;

--
-- TOC entry 5259 (class 0 OID 0)
-- Dependencies: 243
-- Name: password_resets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.password_resets_id_seq OWNED BY public.password_resets.id;


--
-- TOC entry 223 (class 1259 OID 16924)
-- Name: product_images_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.product_images_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.product_images_id_seq OWNER TO postgres;

--
-- TOC entry 5260 (class 0 OID 0)
-- Dependencies: 223
-- Name: product_images_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.product_images_id_seq OWNED BY public.product_images.id;


--
-- TOC entry 221 (class 1259 OID 16885)
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_id_seq OWNER TO postgres;

--
-- TOC entry 5261 (class 0 OID 0)
-- Dependencies: 221
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- TOC entry 232 (class 1259 OID 17047)
-- Name: reviews; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reviews (
    id integer NOT NULL,
    order_id integer NOT NULL,
    seller_id integer NOT NULL,
    buyer_id integer NOT NULL,
    rating smallint NOT NULL,
    comment text,
    seller_response text,
    seller_response_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    farmer_reply text,
    farmer_replied_at timestamp without time zone,
    CONSTRAINT reviews_rating_check CHECK (((rating >= 1) AND (rating <= 5))),
    CONSTRAINT reviews_rating_check1 CHECK (((rating >= 1) AND (rating <= 5)))
);


ALTER TABLE public.reviews OWNER TO postgres;

--
-- TOC entry 5262 (class 0 OID 0)
-- Dependencies: 232
-- Name: TABLE reviews; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.reviews IS 'User reviews and ratings - core trust feature, only for completed orders';


--
-- TOC entry 231 (class 1259 OID 17046)
-- Name: reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.reviews_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.reviews_id_seq OWNER TO postgres;

--
-- TOC entry 5263 (class 0 OID 0)
-- Dependencies: 231
-- Name: reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.reviews_id_seq OWNED BY public.reviews.id;


--
-- TOC entry 241 (class 1259 OID 17163)
-- Name: seller_performance; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.seller_performance AS
 SELECT u.id AS seller_id,
    u.name AS seller_name,
    u.trust_score,
    u.verification_tier,
    count(DISTINCT o.id) AS total_orders,
    count(DISTINCT r.id) AS total_reviews,
    COALESCE(avg(r.rating), (0)::numeric) AS avg_rating,
    count(DISTINCT
        CASE
            WHEN (o.status = 'completed'::public.order_status) THEN o.id
            ELSE NULL::integer
        END) AS completed_orders
   FROM ((public.users u
     LEFT JOIN public.orders o ON ((u.id = o.farmer_id)))
     LEFT JOIN public.reviews r ON ((u.id = r.seller_id)))
  WHERE ((u.role = 'farmer'::public.user_role) AND (u.status = true))
  GROUP BY u.id, u.name, u.trust_score, u.verification_tier;


ALTER VIEW public.seller_performance OWNER TO postgres;

--
-- TOC entry 5264 (class 0 OID 0)
-- Dependencies: 241
-- Name: VIEW seller_performance; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW public.seller_performance IS 'Seller performance metrics for trust ranking';


--
-- TOC entry 236 (class 1259 OID 17111)
-- Name: user_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_sessions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.user_sessions OWNER TO postgres;

--
-- TOC entry 5265 (class 0 OID 0)
-- Dependencies: 236
-- Name: TABLE user_sessions; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.user_sessions IS 'Reserved for token-based authentication expansion';


--
-- TOC entry 235 (class 1259 OID 17110)
-- Name: user_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_sessions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_sessions_id_seq OWNER TO postgres;

--
-- TOC entry 5266 (class 0 OID 0)
-- Dependencies: 235
-- Name: user_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_sessions_id_seq OWNED BY public.user_sessions.id;


--
-- TOC entry 219 (class 1259 OID 16849)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- TOC entry 5267 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- TOC entry 4939 (class 2604 OID 17136)
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- TOC entry 4919 (class 2604 OID 16948)
-- Name: cart_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items ALTER COLUMN id SET DEFAULT nextval('public.cart_items_id_seq'::regclass);


--
-- TOC entry 4934 (class 2604 OID 17090)
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- TOC entry 4929 (class 2604 OID 17021)
-- Name: order_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items ALTER COLUMN id SET DEFAULT nextval('public.order_items_id_seq'::regclass);


--
-- TOC entry 4923 (class 2604 OID 16977)
-- Name: orders id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);


--
-- TOC entry 4941 (class 2604 OID 17190)
-- Name: password_resets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets ALTER COLUMN id SET DEFAULT nextval('public.password_resets_id_seq'::regclass);


--
-- TOC entry 4915 (class 2604 OID 16928)
-- Name: product_images id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_images ALTER COLUMN id SET DEFAULT nextval('public.product_images_id_seq'::regclass);


--
-- TOC entry 4907 (class 2604 OID 16889)
-- Name: products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- TOC entry 4931 (class 2604 OID 17050)
-- Name: reviews id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews ALTER COLUMN id SET DEFAULT nextval('public.reviews_id_seq'::regclass);


--
-- TOC entry 4937 (class 2604 OID 17114)
-- Name: user_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions ALTER COLUMN id SET DEFAULT nextval('public.user_sessions_id_seq'::regclass);


--
-- TOC entry 4896 (class 2604 OID 16853)
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- TOC entry 5231 (class 0 OID 17133)
-- Dependencies: 238
-- Data for Name: activity_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.activity_logs (id, user_id, action, ip_address, user_agent, details, created_at) FROM stdin;
1	2	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:18:49.371955
2	3	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:21:48.501407
3	4	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:24:22.108712
4	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:26:25.656527
5	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:29:51.220836
6	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:32:00.537812
7	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:33:11.67849
8	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:42:09.822515
9	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:42:13.424014
10	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:43:20.852236
11	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 12:43:24.924537
12	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:41:22.288172
13	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:41:25.652909
14	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:41:29.301017
15	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:44:11.774377
16	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:44:14.990088
17	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:58:21.861767
18	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:58:31.852195
19	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 14:58:37.469414
20	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 15:00:46.771891
21	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 15:00:53.609393
22	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 15:48:24.278723
23	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:22:13.574039
24	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:23:57.650586
25	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:24:17.600226
26	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:24:21.344471
27	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:25:10.920245
28	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 18:36:15.92989
29	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-23 19:01:31.841633
30	5	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 19:17:34.158501
31	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 19:17:55.408892
32	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 19:37:54.2504
33	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 20:11:12.137397
34	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 21:24:01.088098
35	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 21:26:42.603331
36	6	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 21:50:57.108752
37	6	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 21:51:15.910382
38	6	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 22:25:19.577102
39	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 1, "product_name": "Test Product 1774294083078"}	2026-03-23 22:28:03.298649
40	6	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 22:28:42.252243
41	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 32, "quantity": 12, "product_id": 2, "product_name": "sukuma"}	2026-03-23 22:30:02.195258
42	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 32, "quantity": 12, "product_id": 3, "product_name": "sukumaa"}	2026-03-23 22:30:34.944963
43	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 8, "quantity": 0, "product_id": 4, "product_name": "sukumah"}	2026-03-23 22:35:09.315345
44	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 6, "product_name": "Test Product 1774297278665"}	2026-03-23 23:21:18.970232
45	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-23 23:21:42.387045
46	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 87, "quantity": 80, "product_id": 7, "product_name": "suman"}	2026-03-23 23:22:18.377777
47	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 8, "product_name": "Test Product 1774297403041"}	2026-03-23 23:23:23.285268
48	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 9, "product_name": "Test Product 1774297428464"}	2026-03-23 23:23:48.571345
49	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 7, "quantity": 78, "product_id": 10, "product_name": "sukhhuma"}	2026-03-23 23:26:14.555584
50	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 7, "quantity": 78, "product_id": 11, "product_name": "sukjhhuma"}	2026-03-23 23:26:32.141821
51	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 7, "quantity": 78, "product_id": 12, "product_name": "sukjhhuuuma"}	2026-03-23 23:28:47.008709
52	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 13, "product_name": "Test Product 1774297771231"}	2026-03-23 23:29:31.343997
53	6	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 100, "quantity": 10, "product_id": 14, "product_name": "Test Product 1774297792747"}	2026-03-23 23:29:52.955805
54	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 809, "quantity": 88, "product_id": 15, "product_name": "suma"}	2026-03-24 12:35:55.036343
55	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 809, "quantity": 88, "product_id": 16, "product_name": "sumha"}	2026-03-24 12:49:43.799611
56	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 33, "quantity": 3, "product_id": 17, "product_name": "nambafu nnjje"}	2026-03-24 12:54:14.171156
57	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 111, "quantity": 1, "product_id": 18, "product_name": "sukumavcvv"}	2026-03-24 12:59:43.933084
58	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 44, "quantity": 22, "product_id": 19, "product_name": "rrere"}	2026-03-24 13:05:56.327905
59	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 4, "quantity": 455, "product_id": 20, "product_name": "gf55"}	2026-03-24 13:12:38.331042
60	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 3, "quantity": 3, "product_id": 21, "product_name": "nambafuHYH"}	2026-03-24 13:22:03.648255
61	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 3, "quantity": 34, "product_id": 22, "product_name": "sukuma3DE"}	2026-03-24 13:22:36.582421
62	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 12, "quantity": 121, "product_id": 23, "product_name": "sukumaXX"}	2026-03-24 13:31:13.761727
63	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 12, "quantity": 121, "product_id": 24, "product_name": "sukuMJMmaXX"}	2026-03-24 13:31:40.652884
64	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 43, "quantity": 32, "product_id": 25, "product_name": "FFGF"}	2026-03-24 13:35:11.407313
65	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 3, "quantity": 4, "product_id": 26, "product_name": "FFFFGF"}	2026-03-24 19:35:02.07858
66	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 3, "quantity": 4, "product_id": 27, "product_name": "FFDDFFGF"}	2026-03-24 19:35:57.500841
67	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 5, "quantity": 55, "product_id": 28, "product_name": "VGVV"}	2026-03-24 19:56:11.890761
68	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 22, "quantity": 2, "product_id": 29, "product_name": "dddc"}	2026-03-24 22:49:52.919287
69	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-24 23:32:42.929081
70	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 67, "quantity": 6543, "product_id": 30, "product_name": "vvvb"}	2026-03-24 23:33:59.432074
71	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 3, "quantity": 2, "product_id": 31, "product_name": "vvgv"}	2026-03-24 23:40:15.05404
72	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 121, "quantity": 111, "product_id": 32, "product_name": "mkmmm"}	2026-03-24 23:55:55.152424
73	5	product_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"price": 234, "quantity": 12, "product_id": 33, "product_name": "wwsws"}	2026-03-25 00:02:36.67427
74	5	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-25 00:06:38.380832
75	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 00:07:11.768294
76	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-25 00:08:23.687467
77	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 00:11:52.691213
78	7	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 00:13:14.60678
79	7	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 00:13:40.276201
80	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0	{"price": 232, "quantity": 222, "product_id": 35, "product_name": "new test image"}	2026-03-25 01:36:05.331735
81	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0	{"price": 232, "quantity": 222, "product_id": 36, "product_name": "new test imagedddd"}	2026-03-25 01:42:40.785298
82	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0	{"product_id": 36, "product_name": "new test imagedddd", "images_uploaded": 1}	2026-03-25 01:42:41.086083
83	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"price": 87, "quantity": 9, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 08:33:50.771726
84	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 37, "product_name": "TEST 2", "images_uploaded": 1}	2026-03-25 08:33:51.104299
85	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"price": 4298, "quantity": 22, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 08:42:49.229387
86	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 38, "product_name": "TEST TRUE", "images_uploaded": 1}	2026-03-25 08:42:49.544455
87	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 09:41:40.367853
88	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 09:41:42.077111
89	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 09:41:43.246067
90	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 09:41:44.753224
91	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:29:10.331577
92	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 10:29:28.007199
93	5	user_login	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"role": "farmer"}	2026-03-25 10:30:16.40449
94	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:30:48.141277
95	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:30:48.8542
96	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:30:49.080701
97	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 30, "product_name": "vvvb"}	2026-03-25 10:33:00.082546
98	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:33:33.031238
99	7	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 10:39:35.078019
100	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:09.433956
101	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:10.69571
102	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:12.5583
103	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:17.005283
104	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:17.779677
105	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:43:17.885183
106	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 10:43:19.427325
107	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 10:43:19.849668
108	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 10:43:20.059803
109	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:45:16.149042
110	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 10:45:25.701223
111	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 11:01:32.837563
112	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 1, "new_quantity": 18, "old_quantity": 19, "product_name": "TEST TRUE"}	2026-03-25 11:03:33.256794
113	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 1, "new_quantity": 18, "old_quantity": 18, "product_name": "TEST TRUE"}	2026-03-25 11:03:34.49291
114	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 3, "new_quantity": 2, "old_quantity": 1, "product_name": "vvvb"}	2026-03-25 11:13:18.543316
115	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 3, "new_quantity": 2, "old_quantity": 2, "product_name": "vvvb"}	2026-03-25 11:13:21.898529
116	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 1, "new_quantity": 17, "old_quantity": 18, "product_name": "TEST TRUE"}	2026-03-25 11:13:49.493267
117	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 1, "new_quantity": 17, "old_quantity": 17, "product_name": "TEST TRUE"}	2026-03-25 11:13:53.407002
118	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 1, "new_quantity": 19, "old_quantity": 17, "product_name": "TEST TRUE"}	2026-03-25 11:14:38.075622
119	7	cart_removed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"cart_item_id": 3, "product_name": "vvvb"}	2026-03-25 11:16:43.621679
120	7	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 60222}	2026-03-25 11:21:05.286562
121	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 1, "new_status": "accepted", "old_status": "pending", "order_code": "TRUST-2026-000001"}	2026-03-25 11:22:14.023954
122	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 1, "new_status": "completed", "old_status": "accepted", "order_code": "TRUST-2026-000001"}	2026-03-25 11:22:21.30062
123	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 11:56:36.861448
124	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 11:56:38.324717
125	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 11:56:40.16022
126	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 11:56:42.05316
127	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 29, "product_name": "dddc"}	2026-03-25 11:56:47.441373
128	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 30, "product_name": "vvvb"}	2026-03-25 11:56:55.078555
129	7	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 4524}	2026-03-25 11:57:51.59627
130	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 2, "new_status": "accepted", "old_status": "pending", "order_code": "TRUST-2026-000002"}	2026-03-25 11:59:24.904815
131	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 2, "new_status": "completed", "old_status": "accepted", "order_code": "TRUST-2026-000002"}	2026-03-25 11:59:31.392351
132	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 12:02:49.382442
133	7	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 8646}	2026-03-25 12:03:11.923056
134	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 3, "new_status": "accepted", "old_status": "pending", "order_code": "TRUST-2026-000003"}	2026-03-25 12:03:27.99938
135	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 3, "new_status": "completed", "old_status": "accepted", "order_code": "TRUST-2026-000003"}	2026-03-25 12:03:31.033501
136	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"price": 575, "quantity": 55, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 13:19:59.644387
137	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 39, "product_name": "TWO IMAGES", "images_uploaded": 5}	2026-03-25 13:20:00.711889
138	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 14:06:46.328989
139	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 14:07:01.447824
140	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 14:07:02.419915
141	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 14:07:03.444544
142	7	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 625}	2026-03-25 14:07:30.906215
143	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 4, "new_status": "accepted", "old_status": "pending", "order_code": "TRUST-2026-000004"}	2026-03-25 14:08:11.763434
144	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 4, "new_status": "completed", "old_status": "accepted", "order_code": "TRUST-2026-000004"}	2026-03-25 14:08:15.731558
145	7	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-25 16:16:54.03714
146	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 16:17:08.730322
147	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-25 16:26:25.383401
148	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-25 16:26:32.896461
149	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 36, "product_name": "new test imagedddd"}	2026-03-25 16:26:38.002522
150	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 16:29:58.468762
151	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 34, "product_name": "Test Product 2026-03-24 22:05:48"}	2026-03-25 16:35:39.816956
152	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 16:52:28.739281
153	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-25 16:52:32.584937
154	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-26 08:35:01.143776
155	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-26 08:36:15.869074
156	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 36, "product_name": "new test imagedddd"}	2026-03-26 08:36:22.681707
157	4	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 4580}	2026-03-26 08:37:19.031304
158	4	order_cancelled	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"reason": "Cancelled by customer", "order_id": 5, "order_code": "TRUST-2026-000005"}	2026-03-26 08:37:43.240202
159	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 38, "product_name": "TEST TRUE"}	2026-03-26 08:38:11.480644
160	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 37, "product_name": "TEST 2"}	2026-03-26 08:38:15.931039
161	4	order_placed	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"order_count": 1, "farmer_count": 1, "total_amount": 4435}	2026-03-26 08:38:28.269882
162	5	user_login	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"role": "farmer"}	2026-03-26 08:38:52.210513
163	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 6, "new_status": "accepted", "old_status": "pending", "order_code": "TRUST-2026-000006"}	2026-03-26 08:39:27.547548
164	5	order_status_updated	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"order_id": 6, "new_status": "completed", "old_status": "accepted", "order_code": "TRUST-2026-000006"}	2026-03-26 08:39:41.706699
165	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-26 08:58:46.447901
166	4	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 36, "product_name": "new test imagedddd"}	2026-03-26 09:16:02.327612
167	4	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 39, "product_name": "TWO IMAGES"}	2026-03-26 09:19:20.612923
168	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-26 09:57:15.481534
169	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-26 11:09:16.243159
170	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-26 11:09:19.70064
171	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-26 13:55:54.199561
172	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-26 14:47:19.318571
173	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-27 15:15:03.610479
174	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-27 15:16:57.662727
175	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 15:39:09.558528
176	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 15:41:45.507441
177	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 15:54:53.417631
178	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 15:57:48.67743
179	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 16:06:40.971834
180	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 16:06:41.228859
181	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-27 16:06:45.465704
182	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 16:07:50.527023
183	8	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	\N	2026-03-27 16:07:50.754395
184	8	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-27 16:07:53.987265
185	8	admin_created	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"name": "NAMBAFU NASIUMA CHRISPINUS", "phone": "0799908654", "new_admin_id": 9}	2026-03-27 16:40:01.58192
186	4	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-27 16:41:03.157188
187	4	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-27 16:41:03.334779
188	8	admin_promote	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"target_name": "chris", "target_user_id": 4}	2026-03-27 16:41:19.772852
189	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "admin"}	2026-03-27 16:41:47.727024
190	8	admin_demote	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"target_name": "chris", "target_user_id": 4}	2026-03-27 16:43:14.201211
191	9	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0	{"role": "admin"}	2026-03-27 22:33:52.19811
192	5	user_logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	\N	2026-03-28 08:37:29.172822
193	5	user_logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	\N	2026-03-28 08:37:29.383094
194	5	user_logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	\N	2026-03-28 08:37:38.513722
195	5	user_logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	\N	2026-03-28 08:37:38.633549
196	10	user_registered	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-28 09:15:19.145001
197	5	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "farmer"}	2026-03-28 09:18:45.102152
198	10	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-28 10:09:54.749995
199	10	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-28 10:09:54.948904
200	5	user_login	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"role": "farmer"}	2026-03-29 15:03:02.704985
201	4	user_login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"role": "buyer"}	2026-03-29 18:21:33.012178
202	4	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-03-29 18:23:09.388525
203	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"price": 70, "quantity": 67, "product_id": 40, "product_name": "product image test"}	2026-03-29 18:24:55.561917
204	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 40, "product_name": "product image test", "images_uploaded": 4}	2026-03-29 18:24:56.696921
205	7	cart_added	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 40, "product_name": "product image test"}	2026-03-30 11:30:28.205958
206	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 40, "product_name": "product image test"}	2026-03-30 11:51:23.633478
207	5	user_login	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"role": "farmer"}	2026-04-04 10:57:48.342791
208	7	cart_updated	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	{"quantity": 1, "product_id": 40, "product_name": "product image test"}	2026-04-04 11:00:32.645294
209	7	user_logout	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36	\N	2026-04-04 11:02:03.637154
210	5	product_added	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"price": 45, "quantity": 1, "product_id": 41, "product_name": "seller side adding product"}	2026-04-04 11:04:19.146865
211	5	product_images_uploaded	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 41, "product_name": "seller side adding product", "images_uploaded": 5}	2026-04-04 11:04:21.959536
212	5	product_deleted	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 15, "product_name": "suma"}	2026-04-04 12:22:35.310859
213	5	product_deleted	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0	{"product_id": 16, "product_name": "sumha"}	2026-04-04 12:22:40.9588
\.


--
-- TOC entry 5219 (class 0 OID 16945)
-- Dependencies: 226
-- Data for Name: cart_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cart_items (id, user_id, product_id, quantity, created_at, updated_at) FROM stdin;
11	7	38	1	2026-03-25 16:26:25.366393	2026-03-25 16:26:25.366393
12	7	36	1	2026-03-25 16:26:37.992106	2026-03-25 16:26:37.992106
14	7	34	1	2026-03-25 16:35:39.802979	2026-03-25 16:35:39.802979
20	4	36	1	2026-03-26 09:16:02.317349	2026-03-26 09:16:02.317349
19	4	39	2	2026-03-26 08:58:46.396606	2026-03-26 09:19:20.601413
10	7	39	1	2026-03-25 16:17:08.718736	2026-03-30 11:39:13.790919
13	7	37	1	2026-03-25 16:29:58.455857	2026-03-30 11:39:23.409062
21	7	40	2	2026-03-30 11:30:28.134719	2026-04-04 11:00:32.574021
\.


--
-- TOC entry 5227 (class 0 OID 17087)
-- Dependencies: 234
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, user_id, title, message, type, related_id, is_read, created_at) FROM stdin;
1	5	New Order Received	New order #TRUST-2026-000001 has been placed.	order_placed	1	f	2026-03-25 11:21:05.262755
2	7	Order Accepted	Your order #TRUST-2026-000001 has been accepted.	order_accepted	1	f	2026-03-25 11:22:14.021869
3	7	Order Completed	Order #TRUST-2026-000001 has been completed. Please leave a review!	order_completed	1	f	2026-03-25 11:22:21.298822
4	5	New Order Received	New order #TRUST-2026-000002 has been placed.	order_placed	2	f	2026-03-25 11:57:51.573784
5	7	Order Accepted	Your order #TRUST-2026-000002 has been accepted.	order_accepted	2	f	2026-03-25 11:59:24.901927
6	7	Order Completed	Order #TRUST-2026-000002 has been completed. Please leave a review!	order_completed	2	f	2026-03-25 11:59:31.388952
7	5	New Order Received	New order #TRUST-2026-000003 has been placed.	order_placed	3	f	2026-03-25 12:03:11.906832
8	7	Order Accepted	Your order #TRUST-2026-000003 has been accepted.	order_accepted	3	f	2026-03-25 12:03:27.997201
9	7	Order Completed	Order #TRUST-2026-000003 has been completed. Please leave a review!	order_completed	3	f	2026-03-25 12:03:31.031567
10	5	New Order Received	New order #TRUST-2026-000004 has been placed.	order_placed	4	f	2026-03-25 14:07:30.892454
11	7	Order Accepted	Your order #TRUST-2026-000004 has been accepted.	order_accepted	4	f	2026-03-25 14:08:11.761336
12	7	Order Completed	Order #TRUST-2026-000004 has been completed. Please leave a review!	order_completed	4	f	2026-03-25 14:08:15.729604
13	5	New Order Received	New order #TRUST-2026-000005 has been placed.	order_placed	5	f	2026-03-26 08:37:19.013127
14	5	Order Cancelled	Order #TRUST-2026-000005 has been cancelled. Reason: Cancelled by customer	order_cancelled	5	f	2026-03-26 08:37:43.240202
15	5	New Order Received	New order #TRUST-2026-000006 has been placed.	order_placed	6	f	2026-03-26 08:38:28.25418
16	4	Order Accepted	Your order #TRUST-2026-000006 has been accepted.	order_accepted	6	f	2026-03-26 08:39:27.545362
17	4	Order Completed	Order #TRUST-2026-000006 has been completed. Please leave a review!	order_completed	6	f	2026-03-26 08:39:41.705219
\.


--
-- TOC entry 5223 (class 0 OID 17018)
-- Dependencies: 230
-- Data for Name: order_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.order_items (id, order_id, product_id, product_name, quantity, price, subtotal, created_at) FROM stdin;
1	1	38	TEST TRUE	14	4298.00	60172.00	2026-03-25 11:21:05.262755
2	2	29	dddc	1	22.00	22.00	2026-03-25 11:57:51.573784
3	2	30	vvvb	1	67.00	67.00	2026-03-25 11:57:51.573784
4	2	37	TEST 2	1	87.00	87.00	2026-03-25 11:57:51.573784
5	2	38	TEST TRUE	1	4298.00	4298.00	2026-03-25 11:57:51.573784
6	3	38	TEST TRUE	2	4298.00	8596.00	2026-03-25 12:03:11.906832
7	4	39	TWO IMAGES	1	575.00	575.00	2026-03-25 14:07:30.892454
8	5	36	new test imagedddd	1	232.00	232.00	2026-03-26 08:37:19.013127
9	5	38	TEST TRUE	1	4298.00	4298.00	2026-03-26 08:37:19.013127
10	6	38	TEST TRUE	1	4298.00	4298.00	2026-03-26 08:38:28.25418
11	6	37	TEST 2	1	87.00	87.00	2026-03-26 08:38:28.25418
\.


--
-- TOC entry 5221 (class 0 OID 16974)
-- Dependencies: 228
-- Data for Name: orders; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orders (id, order_code, buyer_id, farmer_id, total, location, instructions, status, payment_method, payment_status, delivery_fee, completed_at, created_at, updated_at) FROM stdin;
1	TRUST-2026-000001	7	5	60222.00	KIKAI	GOOD	completed	cash	pending	50.00	2026-03-25 11:22:21.292686	2026-03-25 11:21:05.262755	2026-03-25 11:22:21.292686
2	TRUST-2026-000002	7	5	4524.00	KIKAI FRF	GOOFFFF	completed	cash	pending	50.00	2026-03-25 11:59:31.380839	2026-03-25 11:57:51.573784	2026-03-25 11:59:31.380839
3	TRUST-2026-000003	7	5	8646.00	HHHYH NN	NJJJNJN	completed	cash	pending	50.00	2026-03-25 12:03:31.025327	2026-03-25 12:03:11.906832	2026-03-25 12:03:31.025327
4	TRUST-2026-000004	7	5	625.00	WEEEEWW	HELLLO ITS GOD	completed	cash	pending	50.00	2026-03-25 14:08:15.721263	2026-03-25 14:07:30.892454	2026-03-25 14:08:15.721263
5	TRUST-2026-000005	4	5	4580.00	nnbbnb.	green one	cancelled	cash	pending	50.00	\N	2026-03-26 08:37:19.013127	2026-03-26 08:37:43.240202
6	TRUST-2026-000006	4	5	4435.00	nnnn		completed	cash	pending	50.00	2026-03-26 08:39:41.699268	2026-03-26 08:38:28.25418	2026-03-26 08:39:41.699268
\.


--
-- TOC entry 5234 (class 0 OID 17187)
-- Dependencies: 244
-- Data for Name: password_resets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_resets (id, user_id, token, expires_at, created_at) FROM stdin;
5	10	1fd656cf78fe879a8aeedb728ca19807e7808dec56016bcf696bb1de9b137430	2026-03-28 11:14:14	2026-03-28 10:14:14.574472
\.


--
-- TOC entry 5217 (class 0 OID 16925)
-- Dependencies: 224
-- Data for Name: product_images; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.product_images (id, product_id, image_url, is_primary, display_order, created_at) FROM stdin;
1	36	product_36_1774392160_0.jpg	f	0	2026-03-25 01:42:41.069393
2	37	product_37_1774416830_0.jpg	t	0	2026-03-25 08:33:51.085847
3	38	product_38_1774417369_0.jpg	t	0	2026-03-25 08:42:49.52479
4	39	product_39_1774433999_0.jpg	t	0	2026-03-25 13:19:59.92935
5	39	product_39_1774433999_1.jpg	f	1	2026-03-25 13:20:00.130139
6	39	product_39_1774434000_2.webp	f	2	2026-03-25 13:20:00.354806
7	39	product_39_1774434000_3.jpg	f	3	2026-03-25 13:20:00.630685
8	39	product_39_1774434000_4.jpg	f	4	2026-03-25 13:20:00.69834
9	40	product_40_1774797895_0.jpeg	t	0	2026-03-29 18:24:56.168711
10	40	product_40_1774797896_1.jpg	f	1	2026-03-29 18:24:56.254007
11	40	product_40_1774797896_2.jpg	f	2	2026-03-29 18:24:56.449751
12	40	product_40_1774797896_3.webp	f	3	2026-03-29 18:24:56.683059
13	41	product_41_1775289859_0.png	t	0	2026-04-04 11:04:19.7916
14	41	product_41_1775289859_1.png	f	1	2026-04-04 11:04:20.474169
15	41	product_41_1775289860_2.png	f	2	2026-04-04 11:04:21.076849
16	41	product_41_1775289861_3.png	f	3	2026-04-04 11:04:21.617033
17	41	product_41_1775289861_4.png	f	4	2026-04-04 11:04:21.944817
\.


--
-- TOC entry 5215 (class 0 OID 16886)
-- Dependencies: 222
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.products (id, farmer_id, name, category, description, price, quantity, unit, status, featured, views_count, created_at, updated_at) FROM stdin;
2	6	sukuma	vegetables	11	32.00	12	kg	t	f	0	2026-03-23 22:30:02.188388	2026-03-23 22:30:02.188388
3	6	sukumaa	vegetables	11	32.00	12	kg	t	f	0	2026-03-23 22:30:34.93791	2026-03-23 22:30:34.93791
4	6	sukumah	vegetables	7	8.00	0	kg	t	f	0	2026-03-23 22:35:09.308955	2026-03-23 22:35:09.308955
5	6	Test Product 2026-03-23 21:17:12	vegetables	Test product description	100.00	10	kg	t	f	0	2026-03-23 23:17:12.792529	2026-03-23 23:17:12.792529
6	6	Test Product 1774297278665	vegetables	Test product from debug page	100.00	10	kg	t	f	0	2026-03-23 23:21:18.948533	2026-03-23 23:21:18.948533
7	6	suman	vegetables	jj	87.00	80	kg	t	f	0	2026-03-23 23:22:18.352517	2026-03-23 23:22:18.352517
8	6	Test Product 1774297403041	vegetables	Test product from debug page	100.00	10	kg	t	f	0	2026-03-23 23:23:23.262471	2026-03-23 23:23:23.262471
9	6	Test Product 1774297428464	vegetables	Test product from debug page	100.00	10	kg	t	f	0	2026-03-23 23:23:48.544435	2026-03-23 23:23:48.544435
10	6	sukhhuma	vegetables	uu	7.00	78	kg	t	f	0	2026-03-23 23:26:14.509689	2026-03-23 23:26:14.509689
11	6	sukjhhuma	vegetables	uu	7.00	78	kg	t	f	0	2026-03-23 23:26:32.115895	2026-03-23 23:26:32.115895
12	6	sukjhhuuuma	vegetables	uu	7.00	78	kg	t	f	0	2026-03-23 23:28:46.986533	2026-03-23 23:28:46.986533
13	6	Test Product 1774297771231	vegetables	Test product from debug page	100.00	10	kg	t	f	0	2026-03-23 23:29:31.320423	2026-03-23 23:29:31.320423
17	5	nambafu nnjje	vegetables	3	33.00	3	kg	t	f	0	2026-03-24 12:54:14.149629	2026-03-24 12:54:14.149629
18	5	sukumavcvv	vegetables	1	111.00	1	bunch	t	f	0	2026-03-24 12:59:43.903907	2026-03-24 12:59:43.903907
19	5	rrere	vegetables	2	44.00	22	kg	t	f	0	2026-03-24 13:05:56.306004	2026-03-24 13:05:56.306004
20	5	gf55	vegetables	5	4.00	455	kg	t	f	0	2026-03-24 13:12:38.305197	2026-03-24 13:12:38.305197
22	5	sukuma3DE	vegetables	FF	3.00	34	kg	t	f	0	2026-03-24 13:22:36.559172	2026-03-24 13:22:36.559172
24	5	sukuMJMmaXX	vegetables	SS	12.00	121	kg	t	f	0	2026-03-24 13:31:40.632398	2026-03-24 13:31:40.632398
25	5	FFGF	vegetables	21QW	43.00	32	kg	t	f	0	2026-03-24 13:35:11.386306	2026-03-24 13:35:11.386306
26	5	FFFFGF	vegetables	VB	3.00	4	kg	t	f	0	2026-03-24 19:35:02.042101	2026-03-24 19:35:02.042101
27	5	FFDDFFGF	vegetables	VB	3.00	4	kg	t	f	0	2026-03-24 19:35:57.4845	2026-03-24 19:35:57.4845
28	5	VGVV	vegetables	GG	5.00	55	kg	t	f	0	2026-03-24 19:56:11.746601	2026-03-24 19:56:11.746601
31	5	vvgv	vegetables	tfds	3.00	2	kg	t	f	0	2026-03-24 23:40:15.032612	2026-03-24 23:40:15.032612
33	5	wwsws	vegetables	nbv	234.00	12	kg	t	f	0	2026-03-25 00:02:36.652605	2026-03-25 00:02:36.652605
34	5	Test Product 2026-03-24 22:05:48	vegetables	Test product description	100.00	10	kg	t	f	0	2026-03-25 00:05:48.283648	2026-03-25 00:05:48.283648
35	5	new test image	vegetables	2321	232.00	222	kg	t	f	0	2026-03-25 01:36:05.293016	2026-03-25 01:36:05.293016
29	5	dddc	vegetables	2	22.00	1	kg	t	f	0	2026-03-24 22:49:52.80587	2026-03-25 11:57:51.573784
14	6	Test Product 1774297792747	vegetables	Test product from debug page	100.00	10	kg	t	f	3	2026-03-23 23:29:52.93314	2026-04-04 10:54:47.733307
1	6	Test Product 1774294083078	vegetables	Test product from debug page	100.00	10	kg	t	f	1	2026-03-23 22:28:03.177224	2026-03-25 12:07:36.558258
32	5	mkmmm	vegetables		121.00	111	kg	t	f	1	2026-03-24 23:55:55.117977	2026-03-25 12:15:22.696253
40	5	product image test	fruits	product image test	70.00	67	liter	t	f	31	2026-03-29 18:24:55.425915	2026-04-04 11:38:24.523424
36	5	new test imagedddd	vegetables	2321	232.00	222	kg	t	f	0	2026-03-25 01:42:40.743696	2026-04-04 12:14:52.725444
41	5	seller side adding product	fruits	yhgfds	100.00	1	kg	t	f	0	2026-04-04 11:04:19.052539	2026-04-04 12:15:04.372594
39	5	TWO IMAGES	vegetables	55	575.00	54	liter	t	f	38	2026-03-25 13:19:59.616652	2026-04-04 12:20:36.747295
21	5	nambafuHYH	vegetables		3.00	3	kg	t	f	1	2026-03-24 13:22:03.62229	2026-03-25 13:51:12.544462
15	5	suma	vegetables	uu	809.00	88	bunch	f	f	0	2026-03-24 12:35:54.985273	2026-04-04 12:22:35.302671
16	5	sumha	vegetables	uu	809.00	88	bunch	f	f	0	2026-03-24 12:49:43.776292	2026-04-04 12:22:40.94963
38	5	TEST TRUE	vegetables	VCVVVV	4298.00	4	bunch	t	f	9	2026-03-25 08:42:49.206678	2026-04-04 12:37:25.544937
23	5	sukumaXX	vegetables	SS	12.00	121	kg	t	f	1	2026-03-24 13:31:13.719859	2026-03-29 17:47:32.104381
37	5	TEST 2	vegetables	656GH	87.00	7	kg	t	f	2	2026-03-25 08:33:50.660192	2026-03-29 18:00:21.213821
30	5	vvvb	fruits	33	67.00	6542	kg	t	f	3	2026-03-24 23:33:59.405656	2026-03-30 12:23:35.536961
\.


--
-- TOC entry 5225 (class 0 OID 17047)
-- Dependencies: 232
-- Data for Name: reviews; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reviews (id, order_id, seller_id, buyer_id, rating, comment, seller_response, seller_response_at, created_at, updated_at, farmer_reply, farmer_replied_at) FROM stdin;
1	1	5	7	5	GOOD	\N	\N	2026-03-25 11:40:58.058728	2026-03-25 11:40:58.058728	\N	\N
2	2	5	7	5	YOU GOOD JOB	\N	\N	2026-03-25 12:00:03.956496	2026-03-25 12:00:03.956496	\N	\N
3	3	5	7	5	FPPPI	\N	\N	2026-03-25 12:04:06.774479	2026-03-25 12:04:06.774479	\N	\N
4	4	5	7	4	IMPROVE ON CAMERA QUALITY	\N	\N	2026-03-25 14:08:49.777087	2026-03-25 16:13:30.341895	greate	2026-03-25 16:13:30.341895
5	6	5	4	1	good stock i love this	\N	\N	2026-03-26 08:40:21.150648	2026-03-26 08:41:41.10587	making this improvenments soon  farmer reply	2026-03-26 08:41:41.10587
\.


--
-- TOC entry 5229 (class 0 OID 17111)
-- Dependencies: 236
-- Data for Name: user_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.user_sessions (id, user_id, token, expires_at, created_at) FROM stdin;
\.


--
-- TOC entry 5213 (class 0 OID 16850)
-- Dependencies: 220
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, name, phone, email, password, role, trust_score, verification_tier, county, subcounty, ward, profile_photo, status, id_verified, id_verification_doc, last_login_at, created_at, updated_at, location, admin_type, must_change_password, reset_token, token_expiry) FROM stdin;
2	chris	0712345678	\N	$2y$12$.D5LnBxl2eUyIjxyvfS5n.dnSCD6iz68TkpWOHaRoGD4HvcUeTuE2	buyer	0.0	basic	\N	\N	\N	\N	t	f	\N	2026-03-23 12:18:49.38497	2026-03-23 12:18:49	2026-03-23 12:18:49.38497	uthiru	\N	f	\N	\N
3	chris	0712345679	\N	$2y$12$lNJdhzg41WQZ.fvsFkoQIOGlHMTr61jm5FTLh17XkoUFp3uoqC3ii	buyer	0.0	basic	\N	\N	\N	\N	t	f	\N	2026-03-23 12:21:48.503884	2026-03-23 12:21:48	2026-03-23 12:21:48.503884	uthiru	\N	f	\N	\N
4	chris	0712345671	\N	$2y$12$n7PYYSc4cRxS9vMZNIaeGuXechnO.miiJPZQdqjcy09/llgSrePIm	buyer	0.0	basic	\N	\N	\N	\N	t	f	\N	2026-03-29 18:21:51.549343	2026-03-23 12:24:22	2026-03-29 18:21:51.549343	uthiru	\N	f	\N	\N
6	farmer	0722000001	\N	$2y$12$oT2uZT8B2iAGFkyN1dDWyexPtN3N3DwgaouwzkanzeqjuCV8yC8Sa	farmer	0.0	basic	\N	\N	\N	\N	t	f	\N	2026-03-23 22:56:14.840239	2026-03-23 21:50:57	2026-03-23 22:56:14.840239	0722000000	\N	f	\N	\N
9	NAMBAFU NASIUMA CHRISPINUS	0799908654	\N	$2y$12$RICai.Fvc0qX/goACz8wo.obuBCS5wtAioAV7ObuWhdestOv6UoSW	admin	0.0	basic	\N	\N	\N	\N	t	f	\N	2026-04-04 10:55:27.306472	2026-03-27 16:40:01.575068	2026-04-04 10:55:27.306472	\N	admin	f	\N	\N
5	pinu	0722000000	\N	$2y$12$0Mqt5kEVvP8NfHzXicIcPuq3gHVTvZkd.Gosqnyshk1uH3akgVCu.	farmer	4.0	basic	\N	\N	\N	\N	t	t	\N	2026-04-04 10:57:48.334296	2026-03-23 19:17:34	2026-04-04 10:57:48.334296	0712345671	\N	f	\N	\N
7	buyer	0793472206	\N	$2y$12$ANtCG.B.AiGyMKp0SXnp7u8mJ4bYAuUDwbwWIX84zowdUxGywMcam	buyer	0.0	basic	\N	\N	\N	\N	t	t	\N	2026-04-04 11:02:14.59869	2026-03-25 00:13:14	2026-04-04 11:02:14.59869	0722000000	\N	f	\N	\N
8	Admin User	0700000000	\N	$2y$12$4boU4ry7DghiESLv3WkF8OOeT0Bi3vrGzMSOYuUtL3HPSHOsfMxuG	admin	0.0	premium	\N	\N	\N	\N	t	t	\N	2026-03-27 16:07:59.130409	2026-03-26 09:56:26.051934	2026-03-27 16:07:59.130409	\N	founder	f	\N	\N
1	Admin User	admin	user@example.com	ADMIN_PLACEHOLDER	admin	0.0	premium	\N	\N	\N	\N	t	t	\N	\N	2026-03-22 16:23:15.019186	2026-03-28 08:54:48.122905	\N	founder	f	\N	\N
10	pinus@gmail.com	0711111111	nambafuchrispinus@gmail.com	$2y$10$fz4yd3pMkrf9Rc276HTLsOYDlDCCTLma6sTE5oSK10D4/uQ7z5GiG	buyer	3.0	basic	\N	\N	\N	\N	t	f	\N	2026-03-28 09:15:54.12452	2026-03-28 09:15:19.134199	2026-03-28 10:18:30.945095	fgdv	\N	t	\N	\N
\.


--
-- TOC entry 5268 (class 0 OID 0)
-- Dependencies: 237
-- Name: activity_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.activity_logs_id_seq', 213, true);


--
-- TOC entry 5269 (class 0 OID 0)
-- Dependencies: 225
-- Name: cart_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cart_items_id_seq', 21, true);


--
-- TOC entry 5270 (class 0 OID 0)
-- Dependencies: 233
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 17, true);


--
-- TOC entry 5271 (class 0 OID 0)
-- Dependencies: 242
-- Name: order_code_sequence; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.order_code_sequence', 6, true);


--
-- TOC entry 5272 (class 0 OID 0)
-- Dependencies: 229
-- Name: order_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.order_items_id_seq', 11, true);


--
-- TOC entry 5273 (class 0 OID 0)
-- Dependencies: 227
-- Name: orders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orders_id_seq', 6, true);


--
-- TOC entry 5274 (class 0 OID 0)
-- Dependencies: 243
-- Name: password_resets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.password_resets_id_seq', 5, true);


--
-- TOC entry 5275 (class 0 OID 0)
-- Dependencies: 223
-- Name: product_images_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.product_images_id_seq', 17, true);


--
-- TOC entry 5276 (class 0 OID 0)
-- Dependencies: 221
-- Name: products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.products_id_seq', 41, true);


--
-- TOC entry 5277 (class 0 OID 0)
-- Dependencies: 231
-- Name: reviews_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.reviews_id_seq', 5, true);


--
-- TOC entry 5278 (class 0 OID 0)
-- Dependencies: 235
-- Name: user_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_sessions_id_seq', 1, false);


--
-- TOC entry 5279 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 10, true);


--
-- TOC entry 5029 (class 2606 OID 17143)
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 4986 (class 2606 OID 16958)
-- Name: cart_items cart_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4988 (class 2606 OID 16960)
-- Name: cart_items cart_items_user_id_product_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_user_id_product_id_key UNIQUE (user_id, product_id);


--
-- TOC entry 5020 (class 2606 OID 17100)
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5005 (class 2606 OID 17033)
-- Name: order_items order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4999 (class 2606 OID 16999)
-- Name: orders orders_order_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_order_code_key UNIQUE (order_code);


--
-- TOC entry 5001 (class 2606 OID 16997)
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- TOC entry 5036 (class 2606 OID 17197)
-- Name: password_resets password_resets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_pkey PRIMARY KEY (id);


--
-- TOC entry 4984 (class 2606 OID 16936)
-- Name: product_images product_images_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_images
    ADD CONSTRAINT product_images_pkey PRIMARY KEY (id);


--
-- TOC entry 4980 (class 2606 OID 16909)
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- TOC entry 5012 (class 2606 OID 17065)
-- Name: reviews reviews_order_id_buyer_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_order_id_buyer_id_key UNIQUE (order_id, buyer_id);


--
-- TOC entry 5014 (class 2606 OID 17063)
-- Name: reviews reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_pkey PRIMARY KEY (id);


--
-- TOC entry 5025 (class 2606 OID 17121)
-- Name: user_sessions user_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_pkey PRIMARY KEY (id);


--
-- TOC entry 5027 (class 2606 OID 17123)
-- Name: user_sessions user_sessions_token_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_token_key UNIQUE (token);


--
-- TOC entry 4965 (class 2606 OID 16876)
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 4967 (class 2606 OID 16874)
-- Name: users users_phone_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_phone_key UNIQUE (phone);


--
-- TOC entry 4969 (class 2606 OID 16872)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 4989 (class 1259 OID 16972)
-- Name: idx_cart_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cart_product ON public.cart_items USING btree (product_id);


--
-- TOC entry 4990 (class 1259 OID 16971)
-- Name: idx_cart_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cart_user ON public.cart_items USING btree (user_id);


--
-- TOC entry 5030 (class 1259 OID 17150)
-- Name: idx_logs_action; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_logs_action ON public.activity_logs USING btree (action);


--
-- TOC entry 5031 (class 1259 OID 17151)
-- Name: idx_logs_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_logs_created ON public.activity_logs USING btree (created_at);


--
-- TOC entry 5032 (class 1259 OID 17152)
-- Name: idx_logs_details; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_logs_details ON public.activity_logs USING gin (details);


--
-- TOC entry 5033 (class 1259 OID 17149)
-- Name: idx_logs_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_logs_user ON public.activity_logs USING btree (user_id);


--
-- TOC entry 5015 (class 1259 OID 17108)
-- Name: idx_notifications_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_created ON public.notifications USING btree (created_at);


--
-- TOC entry 5016 (class 1259 OID 17107)
-- Name: idx_notifications_read; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_read ON public.notifications USING btree (is_read);


--
-- TOC entry 5017 (class 1259 OID 17106)
-- Name: idx_notifications_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_user ON public.notifications USING btree (user_id);


--
-- TOC entry 5018 (class 1259 OID 17109)
-- Name: idx_notifications_user_unread; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_user_unread ON public.notifications USING btree (user_id) WHERE (is_read = false);


--
-- TOC entry 5002 (class 1259 OID 17044)
-- Name: idx_order_items_order; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_order_items_order ON public.order_items USING btree (order_id);


--
-- TOC entry 5003 (class 1259 OID 17045)
-- Name: idx_order_items_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_order_items_product ON public.order_items USING btree (product_id);


--
-- TOC entry 4991 (class 1259 OID 17010)
-- Name: idx_orders_buyer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_buyer ON public.orders USING btree (buyer_id);


--
-- TOC entry 4992 (class 1259 OID 17016)
-- Name: idx_orders_buyer_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_buyer_status ON public.orders USING btree (buyer_id, status);


--
-- TOC entry 4993 (class 1259 OID 17014)
-- Name: idx_orders_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_code ON public.orders USING btree (order_code);


--
-- TOC entry 4994 (class 1259 OID 17013)
-- Name: idx_orders_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_created ON public.orders USING btree (created_at);


--
-- TOC entry 4995 (class 1259 OID 17011)
-- Name: idx_orders_farmer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_farmer ON public.orders USING btree (farmer_id);


--
-- TOC entry 4996 (class 1259 OID 17015)
-- Name: idx_orders_payment_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_payment_status ON public.orders USING btree (payment_status);


--
-- TOC entry 4997 (class 1259 OID 17012)
-- Name: idx_orders_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_status ON public.orders USING btree (status);


--
-- TOC entry 5034 (class 1259 OID 17203)
-- Name: idx_password_resets_token; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_password_resets_token ON public.password_resets USING btree (token);


--
-- TOC entry 4981 (class 1259 OID 16943)
-- Name: idx_product_images_primary; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_product_images_primary ON public.product_images USING btree (product_id) WHERE (is_primary = true);


--
-- TOC entry 4982 (class 1259 OID 16942)
-- Name: idx_product_images_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_product_images_product ON public.product_images USING btree (product_id);


--
-- TOC entry 4970 (class 1259 OID 16917)
-- Name: idx_products_category; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_category ON public.products USING btree (category);


--
-- TOC entry 4971 (class 1259 OID 16920)
-- Name: idx_products_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_created ON public.products USING btree (created_at);


--
-- TOC entry 4972 (class 1259 OID 16915)
-- Name: idx_products_farmer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_farmer ON public.products USING btree (farmer_id);


--
-- TOC entry 4973 (class 1259 OID 16921)
-- Name: idx_products_featured; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_featured ON public.products USING btree (featured) WHERE (featured = true);


--
-- TOC entry 4974 (class 1259 OID 16916)
-- Name: idx_products_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_name ON public.products USING btree (name);


--
-- TOC entry 4975 (class 1259 OID 16919)
-- Name: idx_products_price; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_price ON public.products USING btree (price);


--
-- TOC entry 4976 (class 1259 OID 16922)
-- Name: idx_products_search; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_search ON public.products USING gin (to_tsvector('english'::regconfig, (name)::text));


--
-- TOC entry 4977 (class 1259 OID 16923)
-- Name: idx_products_search_combo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_search_combo ON public.products USING btree (category, price, created_at) WHERE (status = true);


--
-- TOC entry 4978 (class 1259 OID 16918)
-- Name: idx_products_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_status ON public.products USING btree (status);


--
-- TOC entry 5006 (class 1259 OID 17082)
-- Name: idx_reviews_buyer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reviews_buyer ON public.reviews USING btree (buyer_id);


--
-- TOC entry 5007 (class 1259 OID 17084)
-- Name: idx_reviews_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reviews_created ON public.reviews USING btree (created_at);


--
-- TOC entry 5008 (class 1259 OID 17083)
-- Name: idx_reviews_rating; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reviews_rating ON public.reviews USING btree (rating);


--
-- TOC entry 5009 (class 1259 OID 17081)
-- Name: idx_reviews_seller; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reviews_seller ON public.reviews USING btree (seller_id);


--
-- TOC entry 5010 (class 1259 OID 17085)
-- Name: idx_reviews_seller_rating; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reviews_seller_rating ON public.reviews USING btree (seller_id, rating);


--
-- TOC entry 5021 (class 1259 OID 17130)
-- Name: idx_sessions_expires; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sessions_expires ON public.user_sessions USING btree (expires_at);


--
-- TOC entry 5022 (class 1259 OID 17129)
-- Name: idx_sessions_token; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sessions_token ON public.user_sessions USING btree (token);


--
-- TOC entry 5023 (class 1259 OID 17131)
-- Name: idx_sessions_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sessions_user ON public.user_sessions USING btree (user_id);


--
-- TOC entry 4956 (class 1259 OID 16881)
-- Name: idx_users_county; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_county ON public.users USING btree (county);


--
-- TOC entry 4957 (class 1259 OID 16884)
-- Name: idx_users_county_ward; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_county_ward ON public.users USING btree (county, ward);


--
-- TOC entry 4958 (class 1259 OID 16877)
-- Name: idx_users_phone; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_phone ON public.users USING btree (phone);


--
-- TOC entry 4959 (class 1259 OID 16878)
-- Name: idx_users_role; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_role ON public.users USING btree (role);


--
-- TOC entry 4960 (class 1259 OID 16879)
-- Name: idx_users_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_status ON public.users USING btree (status);


--
-- TOC entry 4961 (class 1259 OID 16880)
-- Name: idx_users_trust_score; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_trust_score ON public.users USING btree (trust_score);


--
-- TOC entry 4962 (class 1259 OID 16882)
-- Name: idx_users_verification_tier; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_verification_tier ON public.users USING btree (verification_tier);


--
-- TOC entry 4963 (class 1259 OID 16883)
-- Name: idx_users_ward; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_ward ON public.users USING btree (ward);


--
-- TOC entry 5054 (class 2620 OID 17173)
-- Name: cart_items trigger_cart_items_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_cart_items_updated_at BEFORE UPDATE ON public.cart_items FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 5055 (class 2620 OID 17181)
-- Name: orders trigger_orders_generate_code; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_orders_generate_code BEFORE INSERT ON public.orders FOR EACH ROW WHEN ((new.order_code IS NULL)) EXECUTE FUNCTION public.generate_order_code_simple();


--
-- TOC entry 5056 (class 2620 OID 17171)
-- Name: orders trigger_orders_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_orders_updated_at BEFORE UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 5053 (class 2620 OID 17170)
-- Name: products trigger_products_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_products_updated_at BEFORE UPDATE ON public.products FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 5057 (class 2620 OID 17183)
-- Name: reviews trigger_reviews_check_order_status; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_reviews_check_order_status BEFORE INSERT ON public.reviews FOR EACH ROW EXECUTE FUNCTION public.prevent_review_on_incomplete_order();


--
-- TOC entry 5058 (class 2620 OID 17177)
-- Name: reviews trigger_reviews_update_trust_after_delete; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_reviews_update_trust_after_delete AFTER DELETE ON public.reviews FOR EACH ROW EXECUTE FUNCTION public.update_seller_trust_score();


--
-- TOC entry 5059 (class 2620 OID 17175)
-- Name: reviews trigger_reviews_update_trust_after_insert; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_reviews_update_trust_after_insert AFTER INSERT ON public.reviews FOR EACH ROW EXECUTE FUNCTION public.update_seller_trust_score();


--
-- TOC entry 5060 (class 2620 OID 17176)
-- Name: reviews trigger_reviews_update_trust_after_update; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_reviews_update_trust_after_update AFTER UPDATE ON public.reviews FOR EACH ROW WHEN ((old.rating IS DISTINCT FROM new.rating)) EXECUTE FUNCTION public.update_seller_trust_score();


--
-- TOC entry 5061 (class 2620 OID 17172)
-- Name: reviews trigger_reviews_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_reviews_updated_at BEFORE UPDATE ON public.reviews FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 5052 (class 2620 OID 17169)
-- Name: users trigger_users_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_users_updated_at BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 5050 (class 2606 OID 17144)
-- Name: activity_logs activity_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- TOC entry 5039 (class 2606 OID 16966)
-- Name: cart_items cart_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- TOC entry 5040 (class 2606 OID 16961)
-- Name: cart_items cart_items_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5048 (class 2606 OID 17101)
-- Name: notifications notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5043 (class 2606 OID 17034)
-- Name: order_items order_items_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- TOC entry 5044 (class 2606 OID 17039)
-- Name: order_items order_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- TOC entry 5041 (class 2606 OID 17000)
-- Name: orders orders_buyer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_buyer_id_fkey FOREIGN KEY (buyer_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- TOC entry 5042 (class 2606 OID 17005)
-- Name: orders orders_farmer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_farmer_id_fkey FOREIGN KEY (farmer_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- TOC entry 5051 (class 2606 OID 17198)
-- Name: password_resets password_resets_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5038 (class 2606 OID 16937)
-- Name: product_images product_images_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_images
    ADD CONSTRAINT product_images_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- TOC entry 5037 (class 2606 OID 16910)
-- Name: products products_farmer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_farmer_id_fkey FOREIGN KEY (farmer_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- TOC entry 5045 (class 2606 OID 17076)
-- Name: reviews reviews_buyer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_buyer_id_fkey FOREIGN KEY (buyer_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- TOC entry 5046 (class 2606 OID 17066)
-- Name: reviews reviews_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- TOC entry 5047 (class 2606 OID 17071)
-- Name: reviews reviews_seller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_seller_id_fkey FOREIGN KEY (seller_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- TOC entry 5049 (class 2606 OID 17124)
-- Name: user_sessions user_sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


-- Completed on 2026-04-04 17:59:23

--
-- PostgreSQL database dump complete
--

\unrestrict yUsvJecyFZegELtNgJsr0sC9mEgal70kc1f8LKAnQy4trzdbFcFENnqu59bB1Vv

