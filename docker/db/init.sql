CREATE DATABASE IF NOT EXISTS evidence_test CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
GRANT ALL PRIVILEGES ON evidence_test.* TO 'app'@'%';
CREATE DATABASE IF NOT EXISTS legacy_test CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
GRANT ALL PRIVILEGES ON legacy_test.* TO 'app'@'%';
