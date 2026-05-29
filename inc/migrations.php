<?php
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}
function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}
function ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = db();


    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(50) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO schema_migrations (version) VALUES ('1.6.1')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_shares (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_project_share (project_id, user_id),
        INDEX idx_project_share_project (project_id),
        INDEX idx_project_share_user (user_id),
        CONSTRAINT fk_project_share_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_project_share_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_brands (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_device_brand_user_name (user_id, name),
        INDEX idx_device_brand_user (user_id),
        CONSTRAINT fk_device_brand_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_device_category_user_name (user_id, name),
        INDEX idx_device_category_user (user_id),
        CONSTRAINT fk_device_category_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS device_connectors (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_device_connector_user_name (user_id, name),
        INDEX idx_device_connector_user (user_id),
        CONSTRAINT fk_device_connector_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (table_exists($pdo, 'devices')) {
        $cols = [
            'brand' => "ALTER TABLE devices ADD brand VARCHAR(190) DEFAULT ''",
            'category' => "ALTER TABLE devices ADD category VARCHAR(120) DEFAULT ''",
            'power_w' => "ALTER TABLE devices ADD power_w INT UNSIGNED NOT NULL DEFAULT 0",
            'voltage_v' => "ALTER TABLE devices ADD voltage_v INT UNSIGNED NOT NULL DEFAULT 230",
            'connector' => "ALTER TABLE devices ADD connector VARCHAR(120) DEFAULT ''",
            'notes' => "ALTER TABLE devices ADD notes TEXT NULL"
        ];
        foreach ($cols as $col => $sql) if (!column_exists($pdo, 'devices', $col)) $pdo->exec($sql);
    }
    if (table_exists($pdo, 'circuits') && !column_exists($pdo, 'circuits', 'amp_limit')) {
        $pdo->exec("ALTER TABLE circuits ADD amp_limit DECIMAL(8,2) NOT NULL DEFAULT 16.00");
    }
    if (table_exists($pdo, 'projects')) {
        if (!column_exists($pdo, 'projects', 'public_share_token')) {
            $pdo->exec("ALTER TABLE projects ADD public_share_token VARCHAR(96) NULL");
        }
        if (!column_exists($pdo, 'projects', 'public_share_enabled')) {
            $pdo->exec("ALTER TABLE projects ADD public_share_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }
        try { $pdo->exec("CREATE UNIQUE INDEX idx_projects_public_share_token ON projects (public_share_token)"); } catch (Throwable $e) {}
    }

    if (table_exists($pdo, 'projects')) {
        if (!column_exists($pdo, 'projects', 'deleted_at')) $pdo->exec("ALTER TABLE projects ADD deleted_at DATETIME NULL");
        if (!column_exists($pdo, 'projects', 'deleted_by')) $pdo->exec("ALTER TABLE projects ADD deleted_by INT UNSIGNED NULL");
        if (!column_exists($pdo, 'projects', 'archived_at')) $pdo->exec("ALTER TABLE projects ADD archived_at DATETIME NULL");
        if (!column_exists($pdo, 'projects', 'public_share_expires_at')) $pdo->exec("ALTER TABLE projects ADD public_share_expires_at DATETIME NULL");
        if (!column_exists($pdo, 'projects', 'public_share_password_hash')) $pdo->exec("ALTER TABLE projects ADD public_share_password_hash VARCHAR(255) NULL");
    }
    if (table_exists($pdo, 'devices') && !column_exists($pdo, 'devices', 'deleted_at')) {
        $pdo->exec("ALTER TABLE devices ADD deleted_at DATETIME NULL");
    }
    if (table_exists($pdo, 'project_shares') && !column_exists($pdo, 'project_shares', 'permission')) {
        $pdo->exec("ALTER TABLE project_shares ADD permission ENUM('view','edit','manage') NOT NULL DEFAULT 'view'");
    }

    if (table_exists($pdo, 'projects')) {
        if (!column_exists($pdo, 'projects', 'status')) {
            $pdo->exec("ALTER TABLE projects ADD status ENUM('planning','approved','setup','live','completed') NOT NULL DEFAULT 'planning'");
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_tags (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        color VARCHAR(40) NOT NULL DEFAULT 'secondary',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_project_tag_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_tag_map (
        project_id INT UNSIGNED NOT NULL,
        tag_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (project_id, tag_id),
        CONSTRAINT fk_project_tag_map_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_project_tag_map_tag FOREIGN KEY (tag_id) REFERENCES project_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (table_exists($pdo, 'project_activity')) {
        $pdo->exec("DROP TABLE project_activity");
    }
}
