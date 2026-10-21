-- #!mysql

-- #{ replay
    -- #{ init
        CREATE TABLE IF NOT EXISTS replays (
            id VARCHAR(36) PRIMARY KEY,
            timestamp BIGINT NOT NULL,
            duration INT NOT NULL,
            game_details TEXT NOT NULL,
            data LONGBLOB NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    -- #=}

    -- #{ save
        -- # :id string
        -- # :timestamp int
        -- # :duration int
        -- # :game_details string
        -- # :data string
        INSERT INTO replays (id, timestamp, duration, game_details, data)
        VALUES (:id, :timestamp, :duration, :game_details, :data)
        ON DUPLICATE KEY UPDATE
            timestamp = :timestamp,
            duration = :duration,
            game_details = :game_details,
            data = :data;
    -- #=}

    -- #{ load
        -- # :id string
        SELECT id, timestamp, duration, game_details, data FROM replays WHERE id = :id;
    -- #=}

    -- #{ delete
        -- # :id string
        DELETE FROM replays WHERE id = :id;
    -- #=}

    -- #{ fetch
        -- # :id string
        SELECT id, timestamp, duration, game_details FROM replays WHERE id = :id;
    -- #=}

    -- #{ fetch_all
        SELECT id, timestamp, duration, game_details FROM replays ORDER BY timestamp DESC;
    -- #=}
-- #=}
