(function ($) {
	'use strict';

	function readCssColor(name, fallback) {
		var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
		return v || fallback;
	}

	function prefersReducedMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function Fireworks(canvas, tier) {
		this.canvas = canvas;
		this.ctx = canvas.getContext('2d');
		this.particles = [];
		this.bursts = tier === 'legendary' ? 6 : tier === 'epic' ? 4 : 2;
		this.duration = tier === 'legendary' ? 4000 : tier === 'epic' ? 2500 : 1500;
		this.start = Date.now();
		this.colors = [
			readCssColor('--color-accent', '#e8b923'),
			readCssColor('--color-text', '#ffffff'),
			readCssColor('--color-accent-muted', '#9fd4ff')
		];
		this.resize();
		var self = this;
		$(window).on('resize.achfw', function () { self.resize(); });
	}

	Fireworks.prototype.resize = function () {
		this.canvas.width = window.innerWidth;
		this.canvas.height = window.innerHeight;
	};

	Fireworks.prototype.burst = function () {
		var cx = this.canvas.width * (0.2 + Math.random() * 0.6);
		var cy = this.canvas.height * (0.15 + Math.random() * 0.45);
		var n = 40 + Math.floor(Math.random() * 30);
		for (var i = 0; i < n; i++) {
			var angle = (Math.PI * 2 * i) / n + Math.random() * 0.3;
			var speed = 2 + Math.random() * 5;
			this.particles.push({
				x: cx,
				y: cy,
				vx: Math.cos(angle) * speed,
				vy: Math.sin(angle) * speed,
				life: 1,
				color: this.colors[Math.floor(Math.random() * this.colors.length)]
			});
		}
	};

	Fireworks.prototype.tick = function () {
		var elapsed = Date.now() - this.start;
		var ctx = this.ctx;
		ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
		var burstInterval = this.duration / this.bursts;
		var burstIndex = Math.floor(elapsed / burstInterval);
		if (burstIndex < this.bursts && Math.floor((elapsed - 50) / burstInterval) !== burstIndex) {
			this.burst();
		}
		for (var i = this.particles.length - 1; i >= 0; i--) {
			var p = this.particles[i];
			p.x += p.vx;
			p.y += p.vy;
			p.vy += 0.06;
			p.life -= 0.018;
			if (p.life <= 0) {
				this.particles.splice(i, 1);
				continue;
			}
			ctx.globalAlpha = Math.max(0, p.life);
			ctx.fillStyle = p.color;
			ctx.beginPath();
			ctx.arc(p.x, p.y, 2.5, 0, Math.PI * 2);
			ctx.fill();
		}
		ctx.globalAlpha = 1;
		return elapsed < this.duration || this.particles.length > 0;
	};

	Fireworks.prototype.destroy = function () {
		$(window).off('resize.achfw');
	};

	function buildSparkles($root, count) {
		var $layer = $root.find('.achievement-celebration__sparkles');
		for (var i = 0; i < count; i++) {
			$('<span class="achievement-celebration__sparkle"></span>')
				.css({
					left: (10 + Math.random() * 80) + '%',
					top: (15 + Math.random() * 70) + '%',
					animationDelay: (Math.random() * 2) + 's'
				})
				.appendTo($layer);
		}
	}

	function Celebration(queue, strings) {
		this.queue = queue.slice();
		this.strings = strings || {};
		this.index = 0;
		this.$root = $('#achievement-celebration-root');
		this.fw = null;
	}

	Celebration.prototype.tierLabel = function (tier) {
		if (tier === 'legendary') return this.strings.tierLegendary || 'Legendary Achievement';
		if (tier === 'epic') return this.strings.tierEpic || 'Epic Achievement';
		return this.strings.tierNormal || 'Achievement';
	};

	Celebration.prototype.showCurrent = function () {
		var item = this.queue[this.index];
		if (!item) {
			this.close();
			return;
		}
		var tier = this.index === 0 ? item.celebration_tier : 'normal';
		var remaining = this.queue.length - this.index - 1;
		var $modal = this.$root.find('.achievement-celebration__modal');
		$modal.removeClass('achievement-celebration__modal--legendary achievement-celebration__modal--epic');
		if (tier === 'legendary') {
			$modal.addClass('achievement-celebration__modal--legendary');
		} else if (tier === 'epic') {
			$modal.addClass('achievement-celebration__modal--epic');
		}
		this.$root.find('.achievement-celebration__tier').text(this.tierLabel(tier));
		this.$root.find('.achievement-celebration__name').text(item.name);
		this.$root.find('.achievement-celebration__desc').text(item.description || '');
		var rewardText = '';
		if (item.reward_type && item.reward_type !== 'none' && item.reward_amount > 0) {
			rewardText = (this.strings.rewardPrefix || 'Reward:') + ' ' + item.reward_amount + ' ' + (item.reward_label || '');
		}
		this.$root.find('.achievement-celebration__reward').text(rewardText);
		this.$root.find('.achievement-celebration__more').text(
			remaining > 0
				? (this.strings.more || '+%s more').replace('%s', remaining)
				: ''
		);
		this.$root.find('.achievement-celebration__sparkles').empty();
		if (!prefersReducedMotion()) {
			var sparkleCount = tier === 'legendary' ? 36 : tier === 'epic' ? 28 : 20;
			buildSparkles(this.$root, sparkleCount);
			if (this.fw) {
				this.fw.destroy();
			}
			var canvas = this.$root.find('.achievement-celebration__canvas')[0];
			this.fw = new Fireworks(canvas, tier);
			var self = this;
			function loop() {
				if (!self.fw) return;
				if (self.fw.tick()) {
					requestAnimationFrame(loop);
				}
			}
			loop();
		}
	};

	Celebration.prototype.markSeen = function (id, cb) {
		$.get('game.php', { page: 'achievements', mode: 'celebrate', achievementId: id })
			.always(cb);
	};

	Celebration.prototype.advance = function () {
		var item = this.queue[this.index];
		var self = this;
		if (item && item.id) {
			this.markSeen(item.id, function () {
				self.index++;
				if (self.index >= self.queue.length) {
					self.close();
				} else {
					self.showCurrent();
				}
			});
		} else {
			self.close();
		}
	};

	Celebration.prototype.close = function () {
		if (this.fw) {
			this.fw.destroy();
			this.fw = null;
		}
		this.$root.removeClass('is-active').attr('aria-hidden', 'true');
	};

	Celebration.prototype.start = function () {
		var self = this;
		this.$root.addClass('is-active').attr('aria-hidden', 'false');
		this.showCurrent();
		this.$root.find('.achievement-celebration__btn').off('click.ach').on('click.ach', function () {
			self.advance();
		});
		$(document).off('keydown.ach').on('keydown.ach', function (e) {
			if (e.key === 'Escape') {
				self.advance();
			}
		});
	};

	window.HiveNovaAchievementCelebration = {
		init: function (data) {
			if (!data || !data.queue || !data.queue.length) {
				return;
			}
			var $root = $('#achievement-celebration-root');
			if (!$root.length) {
				return;
			}
			var c = new Celebration(data.queue, data.strings || {});
			c.start();
		}
	};
})(jQuery);
