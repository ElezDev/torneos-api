-- =============================================================================
-- TorneosApp — Esquema de dominio (MySQL 8+ / MariaDB 10.5+)
--
-- Convención de nombres:
--   • BD (columnas/tablas): snake_case  → Eloquent
--   • API JSON (request/response): camelCase → Resources / Form Requests
--   • Keys DENTRO de columnas JSON: camelCase (se exponen tal cual)
--
-- Alcance: tablas de negocio. No incluye users/sessions/cache/jobs,
-- Sanctum (personal_access_tokens) ni Spatie (roles/permissions), ya
-- cubiertas por migraciones Laravel existentes.
--
-- Multi-tenant: toda entidad de negocio lleva tenant_id (salvo catálogos
-- globales como sports). Filtrar SIEMPRE por tenant_id en queries.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Tenants (organizadores / cuentas de organización)
-- -----------------------------------------------------------------------------
CREATE TABLE tenants (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(150) NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY tenants_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membresía usuario ↔ tenant.
-- Roles concretos (organizador, arbitro, delegado) van en Spatie.
-- Si se activa Spatie Teams, usar tenant_id como team_id.
CREATE TABLE tenant_user (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    is_owner        TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY tenant_user_unique (tenant_id, user_id),
    KEY tenant_user_user_id_index (user_id),
    CONSTRAINT tenant_user_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT tenant_user_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Catálogo global de deportes (no es por tenant)
-- -----------------------------------------------------------------------------
CREATE TABLE sports (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50) NOT NULL,          -- football, basketball, volleyball
    name            VARCHAR(100) NOT NULL,
    scoring_label   VARCHAR(30) NOT NULL DEFAULT 'goals', -- goals | points
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY sports_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Sedes (opcionales, por tenant)
-- -----------------------------------------------------------------------------
CREATE TABLE venues (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    address         VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    KEY venues_tenant_id_index (tenant_id),
    CONSTRAINT venues_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Torneos
-- -----------------------------------------------------------------------------
-- format: league | knockout | groups
-- status: draft | registration | active | finished | cancelled
CREATE TABLE tournaments (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    sport_id                BIGINT UNSIGNED NOT NULL,
    name                    VARCHAR(180) NOT NULL,
    slug                    VARCHAR(180) NOT NULL,
    format                  VARCHAR(30) NOT NULL,           -- league|knockout|groups
    status                  VARCHAR(30) NOT NULL DEFAULT 'draft',
    season_label            VARCHAR(80) NULL,               -- "Apertura 2026"
    starts_on               DATE NULL,
    ends_on                 DATE NULL,
    is_public               TINYINT(1) NOT NULL DEFAULT 1,  -- vista pública sin cuenta

    -- Configuración de puntos (JSON, keys camelCase)
    -- Ej: {"win":3,"draw":1,"loss":0}
    points_config           JSON NOT NULL,

    -- Reglas de sanción (JSON, keys camelCase)
    -- Ej: {"yellowsForSuspension":2,"redDirectSuspension":true,"suspensionMatches":1}
    sanction_rules          JSON NOT NULL,

    -- Desempates en orden de prioridad (JSON array, valores camelCase)
    -- Ej: ["points","goalDifference","goalsFor","headToHead"]
    tiebreaker_rules        JSON NOT NULL,

    -- Config extra por formato (JSON, keys camelCase)
    -- league:    {"legs":1}
    -- groups:    {"groupCount":4,"advancePerGroup":2,"legs":1}
    -- knockout:  {"legs":1,"thirdPlaceMatch":false}
    format_config           JSON NULL,

    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    UNIQUE KEY tournaments_tenant_slug_unique (tenant_id, slug),
    KEY tournaments_tenant_id_index (tenant_id),
    KEY tournaments_sport_id_index (sport_id),
    KEY tournaments_status_index (status),
    CONSTRAINT tournaments_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT tournaments_sport_id_foreign
        FOREIGN KEY (sport_id) REFERENCES sports (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupos (solo formato "groups"; también útil si hay fase de grupos previa)
CREATE TABLE tournament_groups (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    tournament_id   BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(80) NOT NULL,           -- "Grupo A"
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY tournament_groups_unique (tournament_id, name),
    KEY tournament_groups_tenant_id_index (tenant_id),
    CONSTRAINT tournament_groups_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT tournament_groups_tournament_id_foreign
        FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Equipos y jugadores
-- -----------------------------------------------------------------------------
CREATE TABLE teams (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    tournament_id           BIGINT UNSIGNED NOT NULL,
    tournament_group_id     BIGINT UNSIGNED NULL,   -- nullable si no hay grupos
    name                    VARCHAR(150) NOT NULL,
    short_name              VARCHAR(20) NULL,
    logo_path               VARCHAR(255) NULL,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,
    UNIQUE KEY teams_tournament_name_unique (tournament_id, name),
    KEY teams_tenant_id_index (tenant_id),
    KEY teams_tournament_group_id_index (tournament_group_id),
    CONSTRAINT teams_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT teams_tournament_id_foreign
        FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE,
    CONSTRAINT teams_tournament_group_id_foreign
        FOREIGN KEY (tournament_group_id) REFERENCES tournament_groups (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: enabled | suspended  (recalculado al cerrar planillas)
CREATE TABLE players (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    team_id                 BIGINT UNSIGNED NOT NULL,
    first_name              VARCHAR(100) NOT NULL,
    last_name               VARCHAR(100) NOT NULL,
    jersey_number           SMALLINT UNSIGNED NULL,
    document_id             VARCHAR(50) NULL,       -- DNI/CI opcional
    birth_date              DATE NULL,
    status                  VARCHAR(30) NOT NULL DEFAULT 'enabled',
    yellow_cards_count      INT UNSIGNED NOT NULL DEFAULT 0,
    red_cards_count         INT UNSIGNED NOT NULL DEFAULT 0,
    suspension_matches_left INT UNSIGNED NOT NULL DEFAULT 0,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,
    KEY players_tenant_id_index (tenant_id),
    KEY players_team_id_index (team_id),
    KEY players_status_index (status),
    CONSTRAINT players_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT players_team_id_foreign
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Fixture / partidos
-- -----------------------------------------------------------------------------
-- status: scheduled | live | finished | postponed | cancelled
-- stage: group | league | round_of_16 | quarter | semi | final | third_place | ...
CREATE TABLE matches (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    tournament_id       BIGINT UNSIGNED NOT NULL,
    tournament_group_id BIGINT UNSIGNED NULL,
    venue_id            BIGINT UNSIGNED NULL,
    home_team_id        BIGINT UNSIGNED NULL,   -- null en byes / TBD knockout
    away_team_id        BIGINT UNSIGNED NULL,
    matchday            INT UNSIGNED NULL,       -- jornada (liga)
    round_name          VARCHAR(80) NULL,           -- "Cuartos", "Fecha 3"
    stage               VARCHAR(40) NOT NULL DEFAULT 'league',
    scheduled_at        DATETIME NULL,
    status              VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    home_score          INT UNSIGNED NULL,       -- se fija al cerrar planilla
    away_score          INT UNSIGNED NULL,
    winner_team_id      BIGINT UNSIGNED NULL,   -- útil en knockout / empates resueltos
    notes               TEXT NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    KEY matches_tenant_id_index (tenant_id),
    KEY matches_tournament_id_index (tournament_id),
    KEY matches_scheduled_at_index (scheduled_at),
    KEY matches_status_index (status),
    CONSTRAINT matches_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT matches_tournament_id_foreign
        FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE,
    CONSTRAINT matches_tournament_group_id_foreign
        FOREIGN KEY (tournament_group_id) REFERENCES tournament_groups (id) ON DELETE SET NULL,
    CONSTRAINT matches_venue_id_foreign
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT matches_home_team_id_foreign
        FOREIGN KEY (home_team_id) REFERENCES teams (id) ON DELETE SET NULL,
    CONSTRAINT matches_away_team_id_foreign
        FOREIGN KEY (away_team_id) REFERENCES teams (id) ON DELETE SET NULL,
    CONSTRAINT matches_winner_team_id_foreign
        FOREIGN KEY (winner_team_id) REFERENCES teams (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Planillas de partido
-- -----------------------------------------------------------------------------
-- Una planilla por equipo por partido.
-- status: draft | closed
CREATE TABLE match_sheets (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    match_id        BIGINT UNSIGNED NOT NULL,
    team_id         BIGINT UNSIGNED NOT NULL,
    status          VARCHAR(30) NOT NULL DEFAULT 'draft',
    closed_at       DATETIME NULL,
    closed_by       BIGINT UNSIGNED NULL,       -- users.id
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY match_sheets_match_team_unique (match_id, team_id),
    KEY match_sheets_tenant_id_index (tenant_id),
    CONSTRAINT match_sheets_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT match_sheets_match_id_foreign
        FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE,
    CONSTRAINT match_sheets_team_id_foreign
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT match_sheets_closed_by_foreign
        FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jugadores convocados (validar status != suspended al insertar/cerrar)
CREATE TABLE match_sheet_players (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    match_sheet_id      BIGINT UNSIGNED NOT NULL,
    player_id           BIGINT UNSIGNED NOT NULL,
    jersey_number       SMALLINT UNSIGNED NULL,
    is_starter          TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    UNIQUE KEY match_sheet_players_unique (match_sheet_id, player_id),
    KEY match_sheet_players_tenant_id_index (tenant_id),
    CONSTRAINT match_sheet_players_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT match_sheet_players_match_sheet_id_foreign
        FOREIGN KEY (match_sheet_id) REFERENCES match_sheets (id) ON DELETE CASCADE,
    CONSTRAINT match_sheet_players_player_id_foreign
        FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Eventos del partido (goles/puntos y tarjetas)
-- -----------------------------------------------------------------------------
-- type: goal | ownGoal | yellowCard | redCard | secondYellow
-- minute: minuto del evento (nullable si el deporte no lo usa)
CREATE TABLE match_events (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    match_id            BIGINT UNSIGNED NOT NULL,
    match_sheet_id      BIGINT UNSIGNED NOT NULL,
    team_id             BIGINT UNSIGNED NOT NULL,
    player_id           BIGINT UNSIGNED NOT NULL,
    type                VARCHAR(30) NOT NULL,
    minute              SMALLINT UNSIGNED NULL,
    notes               VARCHAR(255) NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    KEY match_events_tenant_id_index (tenant_id),
    KEY match_events_match_id_index (match_id),
    KEY match_events_player_id_index (player_id),
    KEY match_events_type_index (type),
    CONSTRAINT match_events_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT match_events_match_id_foreign
        FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE,
    CONSTRAINT match_events_match_sheet_id_foreign
        FOREIGN KEY (match_sheet_id) REFERENCES match_sheets (id) ON DELETE CASCADE,
    CONSTRAINT match_events_team_id_foreign
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT match_events_player_id_foreign
        FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Tabla de posiciones (materializada; se recalcula al cerrar planillas)
-- -----------------------------------------------------------------------------
CREATE TABLE standings (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    tournament_id       BIGINT UNSIGNED NOT NULL,
    tournament_group_id BIGINT UNSIGNED NULL,   -- null en liga única / knockout
    team_id             BIGINT UNSIGNED NOT NULL,
    played              INT UNSIGNED NOT NULL DEFAULT 0,  -- PJ
    won                 INT UNSIGNED NOT NULL DEFAULT 0,  -- PG
    drawn               INT UNSIGNED NOT NULL DEFAULT 0,  -- PE
    lost                INT UNSIGNED NOT NULL DEFAULT 0,  -- PP
    goals_for           INT UNSIGNED NOT NULL DEFAULT 0,  -- GF
    goals_against       INT UNSIGNED NOT NULL DEFAULT 0,  -- GC
    goal_difference     INT NOT NULL DEFAULT 0,           -- DG
    points              INT NOT NULL DEFAULT 0,           -- PTS
    rank_position       INT UNSIGNED NULL,
    updated_at          TIMESTAMP NULL,
    created_at          TIMESTAMP NULL,
    UNIQUE KEY standings_unique (tournament_id, team_id, tournament_group_id),
    KEY standings_tenant_id_index (tenant_id),
    KEY standings_tournament_points_index (tournament_id, points),
    CONSTRAINT standings_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT standings_tournament_id_foreign
        FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE,
    CONSTRAINT standings_tournament_group_id_foreign
        FOREIGN KEY (tournament_group_id) REFERENCES tournament_groups (id) ON DELETE CASCADE,
    CONSTRAINT standings_team_id_foreign
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Historial de sanciones aplicadas (auditoría / cumplimiento de partidos)
-- -----------------------------------------------------------------------------
-- Se genera al cerrar planilla cuando una tarjeta dispara suspensión.
CREATE TABLE player_sanctions (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    player_id           BIGINT UNSIGNED NOT NULL,
    tournament_id       BIGINT UNSIGNED NOT NULL,
    source_match_id     BIGINT UNSIGNED NOT NULL,
    reason              VARCHAR(40) NOT NULL,   -- yellowAccumulation | redCard | secondYellow
    matches_banned      INT UNSIGNED NOT NULL DEFAULT 1,
    matches_served      INT UNSIGNED NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    KEY player_sanctions_tenant_id_index (tenant_id),
    KEY player_sanctions_player_id_index (player_id),
    KEY player_sanctions_active_index (is_active),
    CONSTRAINT player_sanctions_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT player_sanctions_player_id_foreign
        FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE,
    CONSTRAINT player_sanctions_tournament_id_foreign
        FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE,
    CONSTRAINT player_sanctions_source_match_id_foreign
        FOREIGN KEY (source_match_id) REFERENCES matches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Seed mínimo de deportes (catálogo)
-- =============================================================================
INSERT INTO sports (code, name, scoring_label, is_active, created_at, updated_at) VALUES
    ('football',   'Fútbol',      'goals',  1, NOW(), NOW()),
    ('futsal',     'Fútbol sala', 'goals',  1, NOW(), NOW()),
    ('basketball', 'Básquet',     'points', 1, NOW(), NOW()),
    ('volleyball', 'Vóley',       'points', 1, NOW(), NOW()),
    ('handball',   'Handball',    'goals',  1, NOW(), NOW());

-- =============================================================================
-- Notas de diseño
-- =============================================================================
-- 1) Vista pública: filtrar tournaments WHERE is_public = 1 (sin auth).
-- 2) Stats de goleo/tarjetas: agregar desde match_events (no hace falta
--    tabla extra en v1). Índices en match_id / player_id / type alcanzan.
-- 3) Al cerrar AMBAS match_sheets de un partido:
--      - setear matches.home_score / away_score / status=finished
--      - recalcular standings del torneo (y grupo)
--      - actualizar players.yellow/red/suspension + player_sanctions
--      - decrementar suspension_matches_left de convocados sancionados
-- 4) Spatie: roles sugeridos
--      superAdmin (global), organizer, referee, delegate
--    Activar teams de Spatie con team_foreign_key = tenant_id.
-- 5) Nombre de tabla "matches" es palabra reservada en algunos contextos;
--    en Eloquent usar protected $table = 'matches'; OK en MySQL con backticks.
--
-- =============================================================================
-- Mapeo API camelCase (Resources) ← columnas snake_case
-- =============================================================================
-- tenants:          id, name, slug, isActive, createdAt, updatedAt
-- tenantUser:       id, tenantId, userId, isOwner, createdAt, updatedAt
-- sports:           id, code, name, scoringLabel, isActive, createdAt, updatedAt
-- venues:           id, tenantId, name, address, city, createdAt, updatedAt
-- tournaments:      id, tenantId, sportId, name, slug, format, status,
--                   seasonLabel, startsOn, endsOn, isPublic,
--                   pointsConfig, sanctionRules, tiebreakerRules, formatConfig,
--                   createdAt, updatedAt
-- tournamentGroups: id, tenantId, tournamentId, name, sortOrder, createdAt, updatedAt
-- teams:            id, tenantId, tournamentId, tournamentGroupId, name,
--                   shortName, logoPath, createdAt, updatedAt
-- players:          id, tenantId, teamId, firstName, lastName, jerseyNumber,
--                   documentId, birthDate, status, yellowCardsCount,
--                   redCardsCount, suspensionMatchesLeft, createdAt, updatedAt
-- matches:          id, tenantId, tournamentId, tournamentGroupId, venueId,
--                   homeTeamId, awayTeamId, matchday, roundName, stage,
--                   scheduledAt, status, homeScore, awayScore, winnerTeamId,
--                   notes, createdAt, updatedAt
-- matchSheets:      id, tenantId, matchId, teamId, status, closedAt, closedBy,
--                   createdAt, updatedAt
-- matchSheetPlayers:id, tenantId, matchSheetId, playerId, jerseyNumber,
--                   isStarter, createdAt, updatedAt
-- matchEvents:      id, tenantId, matchId, matchSheetId, teamId, playerId,
--                   type, minute, notes, createdAt, updatedAt
-- standings:        id, tenantId, tournamentId, tournamentGroupId, teamId,
--                   played, won, drawn, lost, goalsFor, goalsAgainst,
--                   goalDifference, points, rankPosition, createdAt, updatedAt
-- playerSanctions:  id, tenantId, playerId, tournamentId, sourceMatchId,
--                   reason, matchesBanned, matchesServed, isActive,
--                   createdAt, updatedAt
--
-- Enums/valores estables en API (camelCase donde aplica):
--   tournament.format:  league | knockout | groups
--   tournament.status:  draft | registration | active | finished | cancelled
--   player.status:      enabled | suspended
--   match.status:       scheduled | live | finished | postponed | cancelled
--   matchSheet.status:  draft | closed
--   matchEvent.type:    goal | ownGoal | yellowCard | redCard | secondYellow
--   sanction.reason:    yellowAccumulation | redCard | secondYellow
-- =============================================================================

