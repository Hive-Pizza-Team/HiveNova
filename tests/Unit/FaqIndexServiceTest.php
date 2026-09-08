<?php

use HiveNova\Core\FaqIndexService;
use PHPUnit\Framework\TestCase;

class FaqIndexServiceTest extends TestCase
{
	public function testBuildsCategoriesWithQuestionLinks(): void
	{
		$questions = [
			1 => [
				'category' => 'Tips for Beginners',
				1 => [
					'title' => 'Step 1 - Your First Buildings',
					'body'  => '<p>Build.</p>',
				],
				2 => [
					'title' => 'Step 2 - Your Next Buildings',
					'body'  => '<p>Expand.</p>',
				],
			],
			2 => [
				'category' => 'Advanced Combat',
				1 => [
					'title' => 'Raid',
					'body'  => '<p>Raid.</p>',
				],
			],
		];

		$index = FaqIndexService::fromQuestions($questions);

		$this->assertCount(2, $index);
		$this->assertSame(1, $index[0]['categoryId']);
		$this->assertSame('Tips for Beginners', $index[0]['category']);
		$this->assertSame([
			['categoryId' => 1, 'questionId' => 1, 'title' => 'Step 1 - Your First Buildings'],
			['categoryId' => 1, 'questionId' => 2, 'title' => 'Step 2 - Your Next Buildings'],
		], $index[0]['questions']);
		$this->assertSame(2, $index[1]['categoryId']);
		$this->assertSame('Advanced Combat', $index[1]['category']);
		$this->assertSame([
			['categoryId' => 2, 'questionId' => 1, 'title' => 'Raid'],
		], $index[1]['questions']);
	}

	public function testSkipsNonNumericKeysAndRowsWithoutTitles(): void
	{
		$questions = [
			'intro' => ['category' => 'Not a category id'],
			3 => [
				'category' => 'Moons',
				'note' => ['title' => 'Should skip string key'],
				1 => ['title' => 'Moons', 'body' => '<p>Moon.</p>'],
				2 => ['body' => 'missing title'],
				3 => ['title' => ''],
			],
		];

		$index = FaqIndexService::fromQuestions($questions);

		$this->assertCount(1, $index);
		$this->assertSame(3, $index[0]['categoryId']);
		$this->assertSame('Moons', $index[0]['category']);
		$this->assertSame([
			['categoryId' => 3, 'questionId' => 1, 'title' => 'Moons'],
		], $index[0]['questions']);
	}

	public function testEmptyOrInvalidInputReturnsEmptyList(): void
	{
		$this->assertSame([], FaqIndexService::fromQuestions(null));
		$this->assertSame([], FaqIndexService::fromQuestions('questions'));
		$this->assertSame([], FaqIndexService::fromQuestions([]));
	}

	public function testAcceptsNumericStringKeys(): void
	{
		$questions = [
			'4' => [
				'category' => 'Pizzabits',
				'1' => ['title' => 'Pizzabits'],
			],
		];

		$index = FaqIndexService::fromQuestions($questions);

		$this->assertSame(4, $index[0]['categoryId']);
		$this->assertSame(1, $index[0]['questions'][0]['questionId']);
		$this->assertSame('Pizzabits', $index[0]['questions'][0]['title']);
	}
}
