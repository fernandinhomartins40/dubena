-- Matriz de autorização (PIE R3, Agente F): prepara os atores que faltam no
-- banco-base para medir BOLA/BFLA de verdade.
--
-- Roda como OWNER (postgres) num banco CLONADO do pie_r3_base — nunca no base,
-- nunca no erp_teste, nunca em dado real. Idempotente: rodar de novo não duplica.
--
--  * Revenda B: grupo, empresa, tenant aprovado e assinatura próprios, com um
--    administrador e um operador (papéis com as MESMAS permissões dos papéis da
--    revenda A). Se B lê um objeto de A, é a fronteira entre revendas que caiu,
--    não a permissão que faltou.
--  * emp2@pie.local: usuário da revenda A com grant SÓ na empresa 2. Mede a
--    fronteira entre empresas do mesmo tenant (objeto da empresa 1).
--
-- Senha de todas as contas novas: Pie-R3-RevendaB!2026 (só banco local).
DO $$
DECLARE
  v_hash  text := '$2y$10$4TSpqFnPYBZ2Qp7yR3kBPeG056tv3Z5G/v9NZaUsUaynGkUpcwRYO';
  v_grupo bigint; v_emp bigint; v_tenant bigint; v_company bigint;
  v_admin bigint; v_oper bigint; v_emp2 bigint;
  v_role_admin bigint; v_role_oper bigint; v_m bigint; v_plano bigint;
BEGIN
  IF EXISTS (SELECT 1 FROM users WHERE email = 'adminb@pie.local') THEN
    RAISE NOTICE 'revenda B ja existe';
    RETURN;
  END IF;

  SELECT plano_id INTO v_plano FROM assinaturas WHERE empresa_id = 1;

  INSERT INTO grupos (descricao, ativo, created_at, updated_at)
    VALUES ('Revenda B (matriz de autorizacao)', true, now(), now()) RETURNING id INTO v_grupo;
  INSERT INTO empresas (grupo_id, razao_social, nome_fantasia, matriz, ativo, ownership_status, created_at, updated_at)
    VALUES (v_grupo, 'Revenda B Gas Ltda', 'Revenda B', true, true, 'OWNERSHIP_APPROVED', now(), now()) RETURNING id INTO v_emp;
  INSERT INTO tenant_accounts (legal_name, status, classified_at, classification_evidence_ref, created_at, updated_at)
    VALUES ('Revenda B (matriz)', 'ACTIVE', now(), 'pie-r3-f', now(), now()) RETURNING id INTO v_tenant;
  INSERT INTO tenant_companies (tenant_account_id, empresa_id, status, approved_at, ownership_evidence_ref, created_at, updated_at)
    VALUES (v_tenant, v_emp, 'APPROVED', now(), 'pie-r3-f', now(), now()) RETURNING id INTO v_company;
  INSERT INTO tenant_legacy_group_scopes (tenant_account_id, grupo_id, status, approved_at, evidence_ref, created_at, updated_at)
    VALUES (v_tenant, v_grupo, 'APPROVED', now(), 'pie-r3-f', now(), now());
  INSERT INTO assinaturas (empresa_id, plano_id, status, inicio, created_at, updated_at)
    VALUES (v_emp, v_plano, 'ativa', current_date, now(), now());

  -- Papéis de B com as permissões dos papéis homônimos de A.
  INSERT INTO roles (grupo_id, nome, created_at, updated_at) VALUES (v_grupo, 'Administrador', now(), now()) RETURNING id INTO v_role_admin;
  INSERT INTO permission_role (role_id, permission_id) SELECT v_role_admin, permission_id FROM permission_role WHERE role_id = 1;
  INSERT INTO roles (grupo_id, nome, created_at, updated_at) VALUES (v_grupo, 'Operador', now(), now()) RETURNING id INTO v_role_oper;
  INSERT INTO permission_role (role_id, permission_id) SELECT v_role_oper, permission_id FROM permission_role WHERE role_id = 3;

  INSERT INTO users (name, email, password, empresa_id, grupo_id, ativo, created_at, updated_at)
    VALUES ('Admin Revenda B', 'adminb@pie.local', v_hash, v_emp, v_grupo, true, now(), now()) RETURNING id INTO v_admin;
  INSERT INTO users (name, email, password, empresa_id, grupo_id, ativo, created_at, updated_at)
    VALUES ('Operador Revenda B', 'operadorb@pie.local', v_hash, v_emp, v_grupo, true, now(), now()) RETURNING id INTO v_oper;
  INSERT INTO role_user (user_id, role_id, empresa_id) VALUES (v_admin, v_role_admin, v_emp), (v_oper, v_role_oper, v_emp);

  INSERT INTO tenant_memberships (tenant_account_id, user_id, status, membership_role, approved_at, approval_evidence_ref, created_at, updated_at)
    VALUES (v_tenant, v_admin, 'ACTIVE', 'OWNER', now(), 'pie-r3-f', now(), now()) RETURNING id INTO v_m;
  INSERT INTO tenant_company_grants (tenant_membership_id, empresa_id, tenant_account_id, tenant_company_id, can_read, can_operate, approved_at, grant_evidence_ref, created_at, updated_at)
    VALUES (v_m, v_emp, v_tenant, v_company, true, true, now(), 'pie-r3-f', now(), now());
  INSERT INTO tenant_memberships (tenant_account_id, user_id, status, membership_role, approved_at, approval_evidence_ref, created_at, updated_at)
    VALUES (v_tenant, v_oper, 'ACTIVE', 'MEMBER', now(), 'pie-r3-f', now(), now()) RETURNING id INTO v_m;
  INSERT INTO tenant_company_grants (tenant_membership_id, empresa_id, tenant_account_id, tenant_company_id, can_read, can_operate, approved_at, grant_evidence_ref, created_at, updated_at)
    VALUES (v_m, v_emp, v_tenant, v_company, true, true, now(), 'pie-r3-f', now(), now());

  -- Revenda A, empresa 2 somente: Administrador (papel 1) com pivot e grant na 2.
  INSERT INTO users (name, email, password, empresa_id, grupo_id, ativo, created_at, updated_at)
    VALUES ('Admin so da empresa 2', 'emp2@pie.local', v_hash, 2, 1, true, now(), now()) RETURNING id INTO v_emp2;
  INSERT INTO role_user (user_id, role_id, empresa_id) VALUES (v_emp2, 1, 2);
  INSERT INTO tenant_memberships (tenant_account_id, user_id, status, membership_role, approved_at, approval_evidence_ref, created_at, updated_at)
    VALUES (1, v_emp2, 'ACTIVE', 'MEMBER', now(), 'pie-r3-f', now(), now()) RETURNING id INTO v_m;
  INSERT INTO tenant_company_grants (tenant_membership_id, empresa_id, tenant_account_id, tenant_company_id, can_read, can_operate, approved_at, grant_evidence_ref, created_at, updated_at)
    VALUES (v_m, 2, 1, (SELECT id FROM tenant_companies WHERE empresa_id = 2), true, true, now(), 'pie-r3-f', now(), now());

  RAISE NOTICE 'revenda B: grupo=% empresa=% tenant=% admin=% oper=% emp2=%', v_grupo, v_emp, v_tenant, v_admin, v_oper, v_emp2;
END $$;

-- Dois consumidores do app na revenda A, cada um dono de pedidos DIFERENTES:
-- o consumidor 1 pedindo o pedido do consumidor 2 é o BOLA do app. A fronteira
-- SaaS espelha ClienteAuthService::garantirFronteiraSaas (grant só na empresa
-- do cliente). O token é emitido pelo runner, com a ability `role:cliente`.
DO $$
DECLARE
  v_hash text := '$2y$10$4TSpqFnPYBZ2Qp7yR3kBPeG056tv3Z5G/v9NZaUsUaynGkUpcwRYO';
  r record; v_user bigint; v_m bigint; n int := 0;
BEGIN
  IF EXISTS (SELECT 1 FROM users WHERE email = 'consumidor1@pie.local') THEN
    RETURN;
  END IF;
  FOR r IN
    SELECT DISTINCT ON (c.id) c.id, c.nome, c.empresa_id, c.grupo_id
      FROM clientes c JOIN pedidos p ON p.cliente_id = c.id
     WHERE c.empresa_id = 1 AND c.user_id IS NULL
     ORDER BY c.id LIMIT 2
  LOOP
    n := n + 1;
    INSERT INTO users (name, email, password, empresa_id, grupo_id, ativo, created_at, updated_at)
      VALUES (r.nome, 'consumidor' || n || '@pie.local', v_hash, r.empresa_id, r.grupo_id, true, now(), now()) RETURNING id INTO v_user;
    UPDATE clientes SET user_id = v_user WHERE id = r.id;
    INSERT INTO tenant_memberships (tenant_account_id, user_id, status, membership_role, approved_at, approval_evidence_ref, created_at, updated_at)
      VALUES (1, v_user, 'ACTIVE', 'MEMBER', now(), 'app:cliente:' || v_user, now(), now()) RETURNING id INTO v_m;
    INSERT INTO tenant_company_grants (tenant_membership_id, empresa_id, tenant_account_id, tenant_company_id, can_read, can_operate, approved_at, grant_evidence_ref, created_at, updated_at)
      VALUES (v_m, 1, 1, (SELECT id FROM tenant_companies WHERE empresa_id = 1), true, true, now(), 'app:cliente:' || v_user, now(), now());
    RAISE NOTICE 'consumidor% = user % cliente %', n, v_user, r.id;
  END LOOP;
END $$;
