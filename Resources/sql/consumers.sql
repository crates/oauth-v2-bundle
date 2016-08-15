CREATE TABLE `consumers` (
    `component_id` VarChar( 255 ) NOT NULL,
    `auth_url` text NOT NULL,
    `token_url` VarChar( 255 ) NOT NULL,
    `request_token_url` VarChar( 255 ) NULL,
    `app_key` VarChar( 255 ) NOT NULL,
    `app_secret` VarChar( 255 ) NOT NULL,
    `app_secret_docker` VarChar( 255 ) NOT NULL,
    `friendly_name` VarChar( 255 ) NOT NULL,
    `oauth_version` VarChar( 255 ) NOT NULL,
    CONSTRAINT `unique_component_id` UNIQUE( `component_id` ) )
ENGINE = InnoDB;
