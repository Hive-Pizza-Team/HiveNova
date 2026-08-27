-- Encrypt-at-rest for Hive WIFs: widen columns for enc:v1 blobs.
ALTER TABLE `%PREFIX%config`
  MODIFY `hive_inactive_memo_active_key` varchar(255) NOT NULL DEFAULT '',
  MODIFY `hive_social_memo_memo_key` varchar(255) NOT NULL DEFAULT '',
  MODIFY `season_wallet_active_key` varchar(255) NOT NULL DEFAULT '',
  MODIFY `season_blog_posting_key` varchar(255) NOT NULL DEFAULT '';
