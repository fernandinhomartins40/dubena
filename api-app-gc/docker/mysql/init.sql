-- Cria os dois bancos que o api-app-gc usa (principal + logs).
-- O MYSQL_DATABASE do compose já cria sgcm_api; aqui garantimos sgcm_logs.
CREATE DATABASE IF NOT EXISTS sgcm_api    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS sgcm_logs   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON sgcm_api.*  TO 'sgcm'@'%';
GRANT ALL PRIVILEGES ON sgcm_logs.* TO 'sgcm'@'%';
FLUSH PRIVILEGES;
