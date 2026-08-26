-- Normalize element_level trigger params: use "threshold" (not "level") so
-- AchievementService applyProgress gates unlocks correctly.
UPDATE `%PREFIX%achievements` SET trigger_params = '{"element_id":1,"threshold":5}'
 WHERE `key` = 'economy_metal_mine_5' AND trigger_params LIKE '%"level"%';

UPDATE `%PREFIX%achievements` SET trigger_params = '{"element_id":1,"threshold":10}'
 WHERE `key` = 'economy_metal_mine_10' AND trigger_params LIKE '%"level"%';

UPDATE `%PREFIX%achievements` SET trigger_params = '{"element_id":124,"threshold":1}'
 WHERE `key` = 'research_astro_1' AND trigger_params LIKE '%"level"%';

UPDATE `%PREFIX%achievements` SET trigger_params = '{"element_id":124,"threshold":3}'
 WHERE `key` = 'research_astro_3' AND trigger_params LIKE '%"level"%';
