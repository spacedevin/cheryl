DROP TABLE IF EXISTS `cheryl_permission`;

CREATE TABLE `cheryl_permission` (
  `id_user_permission` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(40) DEFAULT NULL,
  `permission` varchar(40) DEFAULT NULL,
  `allow` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_user_permission`),
  KEY `user` (`user`),
  CONSTRAINT `cheryl_permission_ibfk_1` FOREIGN KEY (`user`) REFERENCES `cheryl_user` (`user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `cheryl_permission` (`id_user_permission`, `user`, `permission`, `allow`)
VALUES
	(1,'admin','all',1);

DROP TABLE IF EXISTS `cheryl_user`;

CREATE TABLE `cheryl_user` (
  `id_user` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(40) DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `cheryl_user` (`id_user`, `user`, `hash`)
VALUES
	(1,'admin','47c09b8ded207f3d0ef946ddd241fe009fee3527');

