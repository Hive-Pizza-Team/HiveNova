CREATE TABLE `%PREFIX%dm_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `amount_spent` float DEFAULT NULL,
  `amount_received` float DEFAULT NULL,
  `item_purchased_id` int(11) DEFAULT NULL,
  `memo` varchar(1024) DEFAULT NULL
  PRIMARY KEY (`id`),
) ENGINE=InnoDB AUTO_INCREMENT=748 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci |


-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 9;