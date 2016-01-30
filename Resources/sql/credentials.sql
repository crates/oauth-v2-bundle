CREATE TABLE `credentials` (
    `id` VarChar( 255 ) NOT NULL,
    `creator` VarChar( 255 ) NOT NULL,
    `data` Text NOT NULL,
    `authorized_for` VarChar( 255 ) NULL,
    `project_id` VarChar( 255 ) NOT NULL,
    `component_id` VarChar( 255 ) NOT NULL,
    PRIMARY KEY ( `component_id`, `project_id`, `id` ) )
ENGINE = InnoDB;
