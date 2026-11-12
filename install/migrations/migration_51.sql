-- Public marketing host for canonical / hreflang / og:url (#644).
-- Editable under Admin → Server configuration.
ALTER TABLE `%PREFIX%config`
  ADD `seo_canonical_origin` varchar(255) NOT NULL DEFAULT 'https://moonsiege.com/' AFTER `game_name`;
