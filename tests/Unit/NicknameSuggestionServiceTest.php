<?php

declare(strict_types=1);

use HiveNova\Core\NicknameSuggestionService;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\RegisterValidation;
use PHPUnit\Framework\TestCase;

class NicknameSuggestionServiceTest extends TestCase
{
	public function testGenerateCandidateUsesAdjectiveNounAndTwoDigits(): void
	{
		$calls = [0, 1, 7]; // adj index, noun index, digit 0-99
		$service = new NicknameSuggestionService(
			static function (int $min, int $max) use (&$calls): int {
				$value = array_shift($calls);
				self::assertNotNull($value);
				self::assertGreaterThanOrEqual($min, $value);
				self::assertLessThanOrEqual($max, $value);
				return $value;
			},
			static fn (): bool => false
		);

		$adjectives = NicknameSuggestionService::adjectives();
		$nouns = NicknameSuggestionService::nouns();
		$name = $service->generateCandidate();

		$this->assertSame($adjectives[0].$nouns[1].'07', $name);
		$this->assertMatchesRegularExpression('/^[A-Za-z]+[0-9]{2}$/', $name);
		$this->assertTrue(NicknameSuggestionService::isValidCandidate($name));
	}

	public function testSuggestSkipsTakenNamesAndReturnsAvailable(): void
	{
		$adjectives = NicknameSuggestionService::adjectives();
		$nouns = NicknameSuggestionService::nouns();
		$taken = $adjectives[0].$nouns[0].'00';
		$available = $adjectives[1].$nouns[1].'01';

		$sequence = [
			[0, 0, 0],   // CosmicComet00 (taken)
			[1, 1, 1],   // LunarNebula01 (available)
		];
		$service = new NicknameSuggestionService(
			$this->sequenceRandom($sequence),
			static function (string $username, int $universeId) use ($taken): bool {
				self::assertSame(1, $universeId);
				return $username === $taken;
			}
		);

		$this->assertSame($available, $service->suggest(1));
	}

	public function testSuggestFallsBackToLastCandidateWhenAllCollide(): void
	{
		$adjectives = NicknameSuggestionService::adjectives();
		$nouns = NicknameSuggestionService::nouns();
		$attempts = [];
		for ($i = 0; $i < NicknameSuggestionService::MAX_ATTEMPTS; $i++) {
			$attempts[] = [0, 0, $i % 100];
		}
		$last = $adjectives[0].$nouns[0].sprintf('%02d', (NicknameSuggestionService::MAX_ATTEMPTS - 1) % 100);

		$service = new NicknameSuggestionService(
			$this->sequenceRandom($attempts),
			static fn (): bool => true
		);

		$this->assertSame($last, $service->suggest(1));
	}

	public function testWordListsProduceOnlyValidAsciiUsernames(): void
	{
		foreach (NicknameSuggestionService::adjectives() as $adj) {
			$this->assertMatchesRegularExpression('/^[A-Za-z]+$/', $adj);
		}
		foreach (NicknameSuggestionService::nouns() as $noun) {
			$this->assertMatchesRegularExpression('/^[A-Za-z]+$/', $noun);
		}

		foreach (NicknameSuggestionService::adjectives() as $adj) {
			foreach (NicknameSuggestionService::nouns() as $noun) {
				foreach ([0, 9, 42, 99] as $digits) {
					$name = $adj.$noun.sprintf('%02d', $digits);
					$this->assertTrue(
						NicknameSuggestionService::isValidCandidate($name),
						$name.' should pass register validation'
					);
					$this->assertNull(RegisterValidation::usernameErrorKey($name));
					$this->assertTrue((bool) PlayerUtil::isNameValid($name));
					$this->assertLessThanOrEqual(25, strlen($name));
					$this->assertGreaterThanOrEqual(3, strlen($name));
				}
			}
		}
	}

	public function testIsValidCandidateRejectsBadShapes(): void
	{
		$this->assertFalse(NicknameSuggestionService::isValidCandidate(''));
		$this->assertFalse(NicknameSuggestionService::isValidCandidate('ab'));
		$this->assertFalse(NicknameSuggestionService::isValidCandidate('bad name'));
		$this->assertFalse(NicknameSuggestionService::isValidCandidate('bad@name'));
		$this->assertFalse(NicknameSuggestionService::isValidCandidate(str_repeat('n', 26)));
	}

	public function testSuggestDoesNotQueryTakenWhenUniverseInvalid(): void
	{
		$called = false;
		$service = new NicknameSuggestionService(
			static fn (int $min, int $max): int => $min,
			static function () use (&$called): bool {
				$called = true;
				return true;
			}
		);

		$name = $service->suggest(0);
		$this->assertFalse($called);
		$this->assertTrue(NicknameSuggestionService::isValidCandidate($name));
	}

	/**
	 * @param list<list<int>> $triples adj, noun, digit each call cycle of three random_int invocations
	 * @return callable(int, int): int
	 */
	private function sequenceRandom(array $triples): callable
	{
		$flat = [];
		foreach ($triples as $triple) {
			array_push($flat, $triple[0], $triple[1], $triple[2]);
		}

		return static function (int $min, int $max) use (&$flat): int {
			$value = array_shift($flat);
			if ($value === null) {
				return $min;
			}
			self::assertGreaterThanOrEqual($min, $value);
			self::assertLessThanOrEqual($max, $value);

			return $value;
		};
	}
}
