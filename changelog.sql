-- 2021-03-17
ALTER TABLE subscriptions CHANGE country country CHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL AFTER email;
ALTER TABLE subscriptions CHANGE phone phone VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER zip_code;
