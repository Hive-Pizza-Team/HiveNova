ALTER TABLE `%PREFIX%config`
  ADD `season_blog_account` varchar(16) NOT NULL DEFAULT '',
  ADD `season_blog_posting_key` varchar(80) NOT NULL DEFAULT '';

ALTER TABLE `%PREFIX%season_weeks`
  ADD `blog_permlink` varchar(255) NOT NULL DEFAULT '',
  ADD `blog_trx_id` varchar(80) NOT NULL DEFAULT '';
