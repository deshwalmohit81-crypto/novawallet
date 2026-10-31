CREATE TABLE IF NOT EXISTS networks (
    chain_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    short_name VARCHAR(20) NOT NULL,
    currency_symbol VARCHAR(12) NOT NULL,
    explorer_url VARCHAR(255) NOT NULL,
    rpc_url VARCHAR(500) NULL,
    explorer_api_url VARCHAR(500) NULL,
    explorer_api_key VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    last_health_check DATETIME NULL,
    last_health_ok TINYINT(1) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    chain_id BIGINT UNSIGNED NOT NULL,
    contract_address CHAR(42) NOT NULL,
    name VARCHAR(80) NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    decimals TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_chain_contract (chain_id, contract_address),
    CONSTRAINT fk_tokens_network FOREIGN KEY (chain_id) REFERENCES networks (chain_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key CHAR(64) NOT NULL PRIMARY KEY,
    window_started DATETIME NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_rate_limit_expiry (window_started)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO networks (chain_id, name, short_name, currency_symbol, explorer_url, sort_order) VALUES
(1, 'Ethereum', 'ETH', 'ETH', 'https://etherscan.io', 1),
(56, 'BNB Smart Chain', 'BNB', 'BNB', 'https://bscscan.com', 2),
(137, 'Polygon', 'POL', 'POL', 'https://polygonscan.com', 3),
(42161, 'Arbitrum One', 'ARB', 'ETH', 'https://arbiscan.io', 4),
(10, 'Optimism', 'OP', 'ETH', 'https://optimistic.etherscan.io', 5),
(8453, 'Base', 'BASE', 'ETH', 'https://basescan.org', 6),
(43114, 'Avalanche C-Chain', 'AVAX', 'AVAX', 'https://snowtrace.io', 7)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Demo data is intentionally empty: no fabricated wallets, balances, or transactions are seeded.
