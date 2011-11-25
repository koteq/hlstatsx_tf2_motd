ALTER TABLE `hlstats_PlayerUniqueIds`
ADD COLUMN `avatar`  varchar(255) NULL AFTER `merge`,
ADD COLUMN `avatarmedium`  varchar(255) NULL AFTER `avatar`,
ADD COLUMN `avatarfull`  varchar(255) NULL AFTER `avatarmedium`;