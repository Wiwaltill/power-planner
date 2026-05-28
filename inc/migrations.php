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
}
