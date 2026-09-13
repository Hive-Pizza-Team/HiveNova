-- Speed battle-hall ORDER BY units for universe listings.
ALTER TABLE `%PREFIX%topkb`
	ADD KEY `universe_units` (`universe`, `units`);
