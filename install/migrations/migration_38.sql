-- Special ship rebalance: 217/219 capacity & costs, 220 cost, 217/220 tech gates.

UPDATE `%PREFIX%vars` SET
  `cost901` = 120000,
  `cost902` = 80000,
  `cost903` = 20000,
  `capacity` = 2500000
WHERE `elementID` = 217;

UPDATE `%PREFIX%vars` SET
  `cost901` = 1500000,
  `cost902` = 900000,
  `cost903` = 300000,
  `capacity` = 5000000
WHERE `elementID` = 219;

UPDATE `%PREFIX%vars` SET
  `cost901` = 8000000,
  `cost902` = 8000000,
  `cost903` = 4000000
WHERE `elementID` = 220;

DELETE FROM `%PREFIX%vars_requirements` WHERE `elementID` IN (217, 220);

INSERT INTO `%PREFIX%vars_requirements` (`elementID`, `requireID`, `requireLevel`) VALUES
(217, 111, 10),
(217, 21, 15),
(217, 114, 10),
(217, 110, 14),
(217, 117, 15),
(220, 21, 15),
(220, 114, 10),
(220, 118, 8),
(220, 199, 3);
