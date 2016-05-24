DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id_user` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id_user`, `username`, `password_hash`)
VALUES
	(1,'admin','$2y$10$LQhNZMW3E66NPcTUkCPDyenq3Pn5mJJyZmIVRWzOrIP/2c7HrtodC');



DROP TABLE IF EXISTS `permission`;

CREATE TABLE `permission` (
  `id_user_permission` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `permission` varchar(40) DEFAULT NULL,
  `allow` tinyint(1) NOT NULL DEFAULT '1',
  `id_user` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_user_permission`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `permission_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO `permission` (`id_user_permission`, `id_user`, `permission`, `allow`)
VALUES
	(1,1,'all',1);


