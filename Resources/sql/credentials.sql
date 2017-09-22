CREATE TABLE `credentials` (
    `id` VARCHAR(255) NOT NULL,
    `component_id` VARCHAR(255) NOT NULL,
    `project_id` VARCHAR(255) NOT NULL,
    `creator` VARCHAR(255) NOT NULL,
    `data` TEXT NOT NULL,
    `authorized_for` VARCHAR(255) NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `auth_url` TEXT NULL,
    `token_url` VARCHAR(255) NULL,
    `request_token_url` VARCHAR(255) NULL,
    `app_key` VARCHAR(255) NULL,
    `app_secret` VARCHAR(255) NULL,
    `app_secret_docker` VARCHAR(255) NULL,
    PRIMARY KEY ( `component_id`, `project_id`, `id` ) )
ENGINE = InnoDB;
