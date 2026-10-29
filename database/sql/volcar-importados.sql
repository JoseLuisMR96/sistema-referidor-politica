-- ===========================================================================
-- Vuelca imported_people -> public_registrations   (para phpMyAdmin)
--
--   Municipio  : 685 VILLAVICENCIO (departamento 16 META), residencia y voto
--   Género     : NR  (la columna es NOT NULL y el origen no trae el dato)
--   Documento  : CC
--   Estado     : pendiente
--   Referidor  : aleatorio entre MV31PAS1, FAM7CCQI y PHPZKJW7
--
-- Correr UN BLOQUE A LA VEZ, en orden. No pegues todo junto.
-- Probado en MariaDB 10.4 con STRICT_TRANS_TABLES.
-- ===========================================================================


-- ###########################################################################
-- BLOQUE 0 — (OPCIONAL, UNA SOLA VEZ) MARCAR VOLCADOS ANTERIORES.
--
-- Requiere las migraciones 2026_10_11_000000_add_origen_to_public_registrations
-- y 2026_10_12_000000_create_tenants_and_add_tenant_id.
-- Los volcados hechos antes de esa migración quedaron con origen='formulario'.
-- Mismo criterio que el bloque 4: cédula del padrón + uno de los 3 códigos.
-- Corre el SELECT, confirma el número y luego el UPDATE.
-- ###########################################################################

-- SELECT COUNT(*) AS se_marcarian
-- FROM public_registrations p
-- WHERE p.document_type = 'CC'
--   AND p.origen = 'formulario'
--   AND p.ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
--   AND EXISTS (SELECT 1 FROM imported_people i
--               WHERE i.document_number = p.document_number);

-- UPDATE public_registrations p
-- SET p.origen = 'importado'
-- WHERE p.document_type = 'CC'
--   AND p.origen = 'formulario'
--   AND p.ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
--   AND EXISTS (SELECT 1 FROM imported_people i
--               WHERE i.document_number = p.document_number);


-- ###########################################################################
-- BLOQUE 1 — VERIFICAR.  Obligatorio: no sigas si algo no cuadra.
-- ###########################################################################

-- 1.0) TENANT. Todo el script trabaja con UN tenant (candidato), identificado
--      por su slug: 'tenant-inicial'. Si es otro, reemplaza ese slug en TODO
--      el archivo (aparece en cada sentencia porque phpMyAdmin no conserva
--      variables @ entre ejecuciones). Debe devolver 1 fila.
SELECT id, nombre, slug FROM tenants WHERE slug = 'tenant-inicial';

-- 1.a) Debe devolver EXACTAMENTE 3 filas.
--      Si devuelve menos, el INSERT del bloque 2 NO falla: reparte entre los
--      que existan, o inserta 0 filas, sin avisar de nada.
--      Las 3 deben ser del tenant: si alguna tiene otro tenant_id, PARA.
SELECT id, code, name, is_active, tenant_id
FROM referrers
WHERE code IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
  AND tenant_id = (SELECT id FROM tenants WHERE slug = 'tenant-inicial');

-- 1.b) Debe devolver VILLAVICENCIO / META.
SELECT m.id, m.nombre, d.id AS depto_id, d.nombre AS depto
FROM municipios m
JOIN departamentos d ON d.id = m.departamento_id
WHERE m.id = 685;

-- 1.c) Cuántas se insertarían (las que aún no están). Anota este número.
SELECT COUNT(*) AS por_insertar
FROM imported_people i
WHERE i.tenant_id = (SELECT id FROM tenants WHERE slug = 'tenant-inicial')
  AND NOT EXISTS (
    SELECT 1 FROM public_registrations p
    WHERE p.tenant_id = i.tenant_id
      AND p.document_type = 'CC'
      AND p.document_number = i.document_number
);


-- ###########################################################################
-- BLOQUE 2 — EL VOLCADO.
--
-- Es UNA sola sentencia: en InnoDB entra completa o no entra nada, así que no
-- hace falta envolverla en una transacción. Para deshacerla, bloque 4.
-- ###########################################################################

INSERT INTO public_registrations
    (tenant_id, full_name, document_type, document_number, age, gender,
     residence_municipality, voting_municipality,
     residence_municipality_id, voting_municipality_id,
     phone, referrer_id, ref_code_used, status, origen,
     created_by_user_id, created_at, updated_at)
SELECT
    s.tenant_id,
    s.full_name,
    'CC',
    s.document_number,
    NULL,                              -- age: el origen no lo trae
    'NR',
    m.nombre,                          -- se guarda el texto además del id:
    m.nombre,                          -- el dashboard usa ambos
    m.id,
    m.id,
    NULLIF(TRIM(s.phone), ''),         -- '' -> NULL
    r.id,
    r.code,
    'pendiente',
    'importado',                       -- origen: no vino del formulario
    s.created_by_user_id,
    NOW(),
    NOW()
FROM (
    SELECT
        i.tenant_id,
        i.full_name,
        i.document_number,
        i.phone,
        i.created_by_user_id,
        FLOOR(RAND() * (SELECT COUNT(*) FROM referrers
                        WHERE code IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
                          AND tenant_id = (SELECT id FROM tenants WHERE slug = 'tenant-inicial'))) AS pick
    FROM imported_people i
    WHERE i.tenant_id = (SELECT id FROM tenants WHERE slug = 'tenant-inicial')
      AND NOT EXISTS (
        SELECT 1 FROM public_registrations p
        WHERE p.tenant_id = i.tenant_id
          AND p.document_type = 'CC'
          AND p.document_number = i.document_number
    )
    -- Este LIMIT absurdo NO es un descuido: impide que el optimizador fusione
    -- la subconsulta con la de afuera. Si se fusiona, RAND() se reevalúa en
    -- cada comparación del JOIN y una fila puede emparejarse con 0 o con
    -- varios referidores, alterando el total insertado. No lo quites.
    LIMIT 18446744073709551615
) AS s
JOIN (
    SELECT id, code, ROW_NUMBER() OVER (ORDER BY id) - 1 AS rn
    FROM referrers
    WHERE code IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
      AND tenant_id = (SELECT id FROM tenants WHERE slug = 'tenant-inicial')
) AS r ON r.rn = s.pick
JOIN municipios m ON m.id = 685;


-- ###########################################################################
-- BLOQUE 3 — COMPROBAR EL RESULTADO.
-- ###########################################################################

-- Debe sumar el número que anotaste en 1.c, repartido entre los 3 códigos.
SELECT ref_code_used, COUNT(*) AS filas
FROM public_registrations
WHERE ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
GROUP BY ref_code_used;

-- Ninguna debe haber quedado sin municipio ni con género vacío.
SELECT
    COUNT(*)                                                   AS total,
    SUM(voting_municipality_id IS NULL)                        AS sin_municipio,
    SUM(gender IS NULL OR gender = '')                         AS sin_genero,
    SUM(referrer_id IS NULL)                                   AS sin_referidor
FROM public_registrations
WHERE document_type = 'CC'
  AND ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7');


-- ###########################################################################
-- BLOQUE 4 — DESHACER (solo si algo salió mal).
--
-- Borra únicamente lo que insertó este volcado: cédulas que vienen de
-- imported_people y quedaron atribuidas a esos tres códigos.
-- Corre primero el SELECT y confirma el número antes de correr el DELETE.
-- ###########################################################################

-- SELECT COUNT(*) AS se_borrarian
-- FROM public_registrations p
-- WHERE p.document_type = 'CC'
--   AND p.ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
--   AND EXISTS (SELECT 1 FROM imported_people i
--               WHERE i.document_number = p.document_number);

-- DELETE p FROM public_registrations p
-- WHERE p.document_type = 'CC'
--   AND p.ref_code_used IN ('MV31PAS1', 'FAM7CCQI', 'PHPZKJW7')
--   AND EXISTS (SELECT 1 FROM imported_people i
--               WHERE i.document_number = p.document_number);
